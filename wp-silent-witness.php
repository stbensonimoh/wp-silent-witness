<?php
/**
 * Plugin Name: WP Silent Witness
 * Description: Zero-cost, high-performance error trapping and de-duplication for WordPress.
 * Version: 1.0.5
 * Author: Benson Imoh
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP_Silent_Witness Class
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
        $this->table = $wpdb->base_prefix . 'silent_witness_logs';

        $this->maybe_create_table();

        set_error_handler( [ $this, 'handle_error' ] );
        set_exception_handler( [ $this, 'handle_exception' ] );
        register_shutdown_function( [ $this, 'handle_shutdown' ] );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'silent-witness', [ $this, 'cli_command' ] );
        }
    }

    private function maybe_create_table() {
        global $wpdb;
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table ) );
        if ( $table_exists ) return;

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
    }

    public function handle_error( $errno, $errstr, $errfile, $errline ) {
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
        $clean_file = str_replace( ABSPATH, '', $file );
        $hash = md5( $type . $message . $clean_file . $line );
        
        // LOG TO SYSLOG/ERROR_LOG FOR DEBUGGING
        error_log("Silent Witness attempting to log: $type in $clean_file:$line");

        $res = $wpdb->query( $wpdb->prepare(
            "INSERT INTO $this->table (hash, type, message, file, line, context) 
             VALUES (%s, %s, %s, %s, %d, %s)
             ON DUPLICATE KEY UPDATE count = count + 1, last_seen = NOW()",
            $hash, $type, substr( $message, 0, 2000 ), $clean_file, $line, json_encode(['blog_id' => get_current_blog_id()])
        ));

        if ( false === $res ) {
            error_log("Silent Witness SQL Error: " . $wpdb->last_error);
        }
    }

    private function map_error_type( $errno ) {
        $types = [E_ERROR=>'ERROR', E_WARNING=>'WARNING', E_PARSE=>'PARSE', E_NOTICE=>'NOTICE', E_DEPRECATED=>'DEPRECATED', E_USER_ERROR=>'USER_ERROR', E_USER_WARNING=>'USER_WARNING'];
        return $types[$errno] ?? 'UNKNOWN';
    }

    public function cli_command( $args ) {
        global $wpdb;
        $action = $args[0] ?? 'list';

        if ( 'destroy' === $action ) {
            $wpdb->query( "DROP TABLE IF EXISTS $this->table" );
            WP_CLI::success( "Table dropped." );
        } elseif ( 'export' === $action ) {
            $results = $wpdb->get_results( "SELECT * FROM $this->table ORDER BY last_seen DESC" );
            echo json_encode( $results ?: [], JSON_PRETTY_PRINT );
        } elseif ( 'test' === $action ) {
            WP_CLI::log( "Table name: $this->table" );
            trigger_error( 'Diagnostic Test', E_USER_WARNING );
            
            // Bypass the handler and insert directly to verify DB connectivity
            $wpdb->query( $wpdb->prepare( "INSERT INTO $this->table (hash, type, message, file, line) VALUES (%s, %s, %s, %s, %d) ON DUPLICATE KEY UPDATE count = count + 1", md5('direct-test'), 'TEST', 'Direct DB Test', 'cli', 0 ) );
            
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table" );
            WP_CLI::success( "Test completed. Total rows in DB: $count" );
        } else {
            WP_CLI::error( "Usage: wp silent-witness [export|destroy|test]" );
        }
    }
}
WP_Silent_Witness::init();
