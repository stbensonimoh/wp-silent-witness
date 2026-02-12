<?php
/**
 * Plugin Name: WP Silent Witness
 * Description: Zero-cost, high-performance error trapping and de-duplication for WordPress.
 * Version: 1.0.4
 * Author: Benson Imoh
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP_Silent_Witness Class
 *
 * Intercepts PHP errors and exceptions, de-duplicates them using hashes,
 * and stores them in a custom database table for easy analysis and export.
 * Multisite Aware: Uses a single global table (wp_silent_witness_logs) for the whole network.
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
        // MULTISITE FIX: Use base_prefix to ensure a single global table across the network
        $this->table = $wpdb->base_prefix . 'silent_witness_logs';

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
        // Use a site-independent way to check readiness for multisite
        if ( ! is_multisite() && get_transient( 'silent_witness_db_ready' ) ) return;
        if ( is_multisite() && get_site_transient( 'silent_witness_db_ready' ) ) return;

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

        if ( is_multisite() ) {
            set_site_transient( 'silent_witness_db_ready', true, DAY_IN_SECONDS );
        } else {
            set_transient( 'silent_witness_db_ready', true, DAY_IN_SECONDS );
        }
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
        $context = $this->get_request_context();

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $this->table (hash, type, message, file, line, context) 
             VALUES (%s, %s, %s, %s, %d, %s)
             ON DUPLICATE KEY UPDATE count = count + 1, last_seen = NOW()",
            $hash, $type, substr( $message, 0, 2000 ), $clean_file, $line, $context
        ));
    }

    private function get_request_context() {
        $context = [
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'user_id' => 0,
            'blog_id' => get_current_blog_id()
        ];
        if ( function_exists( 'get_current_user_id' ) ) {
            $context['user_id'] = get_current_user_id();
        }
        return json_encode( $context );
    }

    private function map_error_type( $errno ) {
        $types = [
            E_ERROR => 'ERROR', E_WARNING => 'WARNING', E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE', E_DEPRECATED => 'DEPRECATED', E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING', E_USER_NOTICE => 'USER_NOTICE'
        ];
        return $types[$errno] ?? 'UNKNOWN (' . $errno . ')';
    }

    public function cli_command( $args ) {
        global $wpdb;
        $action = $args[0] ?? 'list';

        if ( 'destroy' === $action ) {
            if ( ! isset( $args[1] ) || '--yes' !== $args[1] ) {
                WP_CLI::error( "This will delete all logs and the database table. Use: wp silent-witness destroy --yes" );
            }
            $wpdb->query( "DROP TABLE IF EXISTS $this->table" );
            if ( is_multisite() ) {
                delete_site_transient( 'silent_witness_db_ready' );
            } else {
                delete_transient( 'silent_witness_db_ready' );
            }
            WP_CLI::success( "Database table dropped and logs destroyed." );
            return;
        }

        if ( 'export' === $action ) {
            $results = $wpdb->get_results( "SELECT * FROM $this->table ORDER BY last_seen DESC" );
            if ( empty( $results ) ) {
                WP_CLI::log( "No errors captured yet in $this->table" );
                echo "[]";
            } else {
                echo json_encode( $results, JSON_PRETTY_PRINT );
            }
        } elseif ( 'clear' === $action ) {
            $wpdb->query( "TRUNCATE TABLE $this->table" );
            WP_CLI::success( "Logs cleared." );
        } elseif ( 'test' === $action ) {
            WP_CLI::log( "Triggering test error on Blog ID: " . get_current_blog_id() );
            trigger_error( 'Silent Witness Diagnostic Test', E_USER_WARNING );
            
            $check = $wpdb->get_var( $wpdb->prepare( "SELECT count FROM $this->table WHERE type = %s", 'USER_WARNING' ) );
            if ( $check ) {
                WP_CLI::success( "Test error captured! (Total USER_WARNING count: $check). Run 'wp silent-witness export' to see it." );
            } else {
                WP_CLI::error( "Test error triggered but NOT captured. Table: $this->table. Last DB Error: " . $wpdb->last_error );
            }
        } elseif ( 'check-db' === $action ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table ) );
            if ( $table_exists ) {
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table" );
                WP_CLI::success( "Global table '$this->table' exists with $count records." );
            } else {
                WP_CLI::error( "Global table '$this->table' does NOT exist." );
            }
        } else {
            WP_CLI::error( "Usage: wp silent-witness [export|clear|destroy|test|check-db]" );
        }
    }
}

WP_Silent_Witness::init();
