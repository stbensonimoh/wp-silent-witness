<?php
/**
 * Plugin Name: WP Silent Witness
 * Description: Zero-cost, high-performance log ingestion and de-duplication for WordPress.
 * Version: 2.0.0
 * Author: Benson Imoh
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP_Silent_Witness Class (v2.0.0 - The Ingestor)
 * 
 * Instead of trapping errors in real-time (which is prone to interference),
 * this version tails the 'debug.log' file, de-duplicates entries, and 
 * stores them in a custom table.
 */
class WP_Silent_Witness {
    private static $instance = null;
    private $table;
    private $log_path;

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->base_prefix . 'silent_witness_logs';
        $this->log_path = WP_CONTENT_DIR . '/debug.log';

        $this->maybe_create_table();

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

    /**
     * Ingest the debug.log file using an offset pointer for efficiency.
     */
    public function ingest() {
        if ( ! file_exists( $this->log_path ) ) {
            return "Log file not found at $this->log_path";
        }

        $handle = fopen( $this->log_path, 'r' );
        if ( ! $handle ) return "Could not open log file.";

        $last_offset = (int) get_site_option( 'silent_witness_log_offset', 0 );
        $file_size = filesize( $this->log_path );

        // Handle Log Rotation/Truncation
        if ( $file_size < $last_offset ) {
            $last_offset = 0;
        }

        fseek( $handle, $last_offset );

        $ingested_count = 0;
        while ( ( $line = fgets( $handle ) ) !== false ) {
            if ( $this->process_line( $line ) ) {
                $ingested_count++;
            }
        }

        update_site_option( 'silent_witness_log_offset', ftell( $handle ) );
        fclose( $handle );

        return $ingested_count;
    }

    /**
     * Parse a standard WordPress error log line.
     * Format: [Timestamp] PHP Level: Message in File on line X
     */
    private function process_line( $line ) {
        // Regex to extract Type, Message, File, and Line
        // Example: [12-Feb-2026 08:00:00 UTC] PHP Warning:  Undefined variable $x in /path/file.php on line 10
        $pattern = '/^\[[^\]]+\] PHP ([^:]+):  (.+?) in (.+?) on line (\d+)/';
        
        if ( preg_match( $pattern, $line, $matches ) ) {
            $this->store_log( 
                trim( $matches[1] ), 
                trim( $matches[2] ), 
                trim( $matches[3] ), 
                (int) $matches[4] 
            );
            return true;
        }
        return false;
    }

    private function store_log( $type, $message, $file, $line ) {
        global $wpdb;
        $clean_file = str_replace( ABSPATH, '', $file );
        $hash = md5( $type . $message . $clean_file . $line );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $this->table (hash, type, message, file, line) 
             VALUES (%s, %s, %s, %s, %d) 
             ON DUPLICATE KEY UPDATE count = count + 1, last_seen = NOW()",
            $hash, $type, substr( $message, 0, 2000 ), $clean_file, $line
        ));
    }

    public function cli_command( $args ) {
        global $wpdb;
        $action = $args[0] ?? 'list';

        if ( 'ingest' === $action ) {
            WP_CLI::log( "Ingesting new entries from debug.log..." );
            $count = $this->ingest();
            if ( is_numeric( $count ) ) {
                WP_CLI::success( "Ingested $count new de-duplicated entries." );
            } else {
                WP_CLI::error( $count );
            }
        } elseif ( 'export' === $action ) {
            $results = $wpdb->get_results( "SELECT * FROM $this->table ORDER BY last_seen DESC" );
            echo json_encode( $results ?: [], JSON_PRETTY_PRINT );
        } elseif ( 'clear' === $action ) {
            $wpdb->query( "TRUNCATE TABLE $this->table" );
            update_site_option( 'silent_witness_log_offset', 0 );
            WP_CLI::success( "Database and offset pointer cleared." );
        } elseif ( 'destroy' === $action ) {
            if ( ! isset( $args[1] ) || '--yes' !== $args[1] ) {
                WP_CLI::error( "Use: wp silent-witness destroy --yes" );
            }
            $wpdb->query( "DROP TABLE IF EXISTS $this->table" );
            delete_site_option( 'silent_witness_log_offset' );
            WP_CLI::success( "Table and state destroyed." );
        } else {
            WP_CLI::error( "Usage: wp silent-witness [ingest|export|clear|destroy]" );
        }
    }
}

WP_Silent_Witness::init();
