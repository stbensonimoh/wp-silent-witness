<?php
/**
 * Plugin Name: WP Silent Witness
 * Description: Zero-cost, high-performance error trapping and de-duplication for WordPress.
 * Version: 1.0.0
 * Author: Benson Imoh
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP_Silent_Witness Class
 *
 * Intercepts PHP errors and exceptions, de-duplicates them using hashes,
 * and stores them in a custom database table for easy analysis and export.
 */
class WP_Silent_Witness {
    private static $instance = null;
    private $table;

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'silent_witness_logs';

        // Initialize schema (lightweight check via transient)
        $this->maybe_create_table();

        // Register handlers
        set_error_handler( [ $this, 'handle_error' ] );
        set_exception_handler( [ $this, 'handle_exception' ] );
        register_shutdown_function( [ $this, 'handle_shutdown' ] );

        // WP-CLI integration
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'silent-witness', [ $this, 'cli_command' ] );
        }
    }

    private function maybe_create_table() {
        if ( get_transient( 'silent_witness_db_ready' ) ) return;

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table (
            hash CHAR(32) NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            file VARCHAR(255) NOT NULL,
            line INT UNSIGNED NOT NULL,
            count INT UNSIGNED DEFAULT 1,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            context TEXT,
            PRIMARY KEY (hash),
            INDEX idx_last_seen (last_seen)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        set_transient( 'silent_witness_db_ready', true, DAY_IN_SECONDS );
    }

    public function handle_error( $errno, $errstr, $errfile, $errline ) {
        if ( ! ( error_reporting() & $errno ) ) return false;
        $this->log( $this->map_error_type( $errno ), $errstr, $errfile, $errline );
        return false;
    }

    public function handle_exception( $exception ) {
        $this->log( 'EXCEPTION', $exception->getMessage(), $exception->getFile(), $exception->getLine() );
    }

    public function handle_shutdown() {
        $error = error_get_last();
        if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ] ) ) {
            $this->log( 'FATAL', $error['message'], $error['file'], $error['line'] );
        }
    }

    private function log( $type, $message, $file, $line ) {
        global $wpdb;

        // Strip ABSPATH for cleaner logs and portability
        $clean_file = str_replace( ABSPATH, '', $file );
        
        // Generate de-duplication hash
        $hash = md5( $type . $message . $clean_file . $line );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $this->table (hash, type, message, file, line, context) 
             VALUES (%s, %s, %s, %s, %d, %s)
             ON DUPLICATE KEY UPDATE count = count + 1, last_seen = NOW()",
            $hash, $type, substr( $message, 0, 2000 ), $clean_file, $line, $this->get_request_context()
        ));
    }

    private function get_request_context() {
        return json_encode([
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'user_id' => get_current_user_id() ?: 0
        ]);
    }

    private function map_error_type( $errno ) {
        $types = [
            E_ERROR => 'ERROR', E_WARNING => 'WARNING', E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE', E_DEPRECATED => 'DEPRECATED', E_USER_ERROR => 'USER_ERROR'
        ];
        return $types[$errno] ?? 'UNKNOWN';
    }

    public function cli_command( $args ) {
        if ( "destroy" === $action ) {
            if ( ! isset( $args[1] ) || "--yes" !== $args[1] ) {
                WP_CLI::error( "This will delete all logs and the database table. Use: wp silent-witness destroy --yes" );
            }
            $wpdb->query( "DROP TABLE IF EXISTS $this->table" );
            delete_transient( "silent_witness_db_ready" );
            WP_CLI::success( "Database table dropped and logs destroyed." );
            return;
        }
        global $wpdb;
        $action = $args[0] ?? 'list';

        if ( 'export' === $action ) {
            $results = $wpdb->get_results( "SELECT * FROM $this->table ORDER BY last_seen DESC" );
            echo json_encode( $results, JSON_PRETTY_PRINT );
        } elseif ( 'clear' === $action ) {
            $wpdb->query( "TRUNCATE TABLE $this->table" );
            WP_CLI::success( "Logs cleared." );
        } else {
            WP_CLI::error( "Usage: wp silent-witness [export|clear]" );
        }
    }
}

WP_Silent_Witness::init();
