<?php
/**
 * Plugin Name: WP Silent Witness
 * Plugin URI:  https://github.com/stbensonimoh/wp-silent-witness
 * Description: Zero-cost, high-performance log ingestion and de-duplication for WordPress.
 * Version:     2.0.1
 * Author:      Benson Imoh
 * Author URI:  https://stbensonimoh.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-silent-witness
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP_Silent_Witness Class (v2.0.1 - The Background Ingestor)
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
		$this->table    = $wpdb->base_prefix . 'silent_witness_logs';
		$this->log_path = WP_CONTENT_DIR . '/debug.log';

		$this->maybe_create_table();

		// Load plugin textdomain.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Register cron hook.
		add_action( 'silent_witness_cron_ingest', [ $this, 'ingest' ] );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( 'silent_witness_cron_ingest' ) ) {
			wp_schedule_event( time(), 'quarterly', 'silent_witness_cron_ingest' );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'silent-witness', [ $this, 'cli_command' ] );
		}
	}

	/**
	 * Load the plugin textdomain for translation.
	 *
	 * @since 2.0.1
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-silent-witness', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	private function maybe_create_table() {
		global $wpdb;
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );
		if ( $table_exists ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $this->table (
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

	public function ingest() {
		if ( ! file_exists( $this->log_path ) ) {
			/* translators: %s: Path to the log file. */
			return sprintf( __( 'Log file not found at %s', 'wp-silent-witness' ), $this->log_path );
		}

		$handle = fopen( $this->log_path, 'r' );
		if ( ! $handle ) {
			return __( 'Could not open log file.', 'wp-silent-witness' );
		}

		$last_offset = (int) get_site_option( 'silent_witness_log_offset', 0 );
		$file_size   = filesize( $this->log_path );

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

	private function process_line( $line ) {
		$pattern = '/^\[[^\]]+\] PHP ([^:]+):  (.+?) in (.+?) on line (\d+)/';
		if ( preg_match( $pattern, $line, $matches ) ) {
			$this->store_log( trim( $matches[1] ), trim( $matches[2] ), trim( $matches[3] ), (int) $matches[4] );
			return true;
		}
		return false;
	}

	private function store_log( $type, $message, $file, $line ) {
		global $wpdb;
		$clean_file = str_replace( ABSPATH, '', $file );
		$hash       = md5( $type . $message . $clean_file . $line );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $this->table (hash, type, message, file, line) 
             VALUES (%s, %s, %s, %s, %d) 
             ON DUPLICATE KEY UPDATE count = count + 1, last_seen = NOW()",
				$hash,
				$type,
				substr( $message, 0, 2000 ),
				$clean_file,
				$line
			)
		);
	}

	public function cli_command( $args ) {
		global $wpdb;
		$action = $args[0] ?? 'list';

		if ( 'ingest' === $action ) {
			WP_CLI::log( __( 'Ingesting new entries...', 'wp-silent-witness' ) );
			$count = $this->ingest();
			if ( is_numeric( $count ) ) {
				/* translators: %d: Number of ingested entries. */
				WP_CLI::success( sprintf( _n( 'Ingested %d new entry.', 'Ingested %d new entries.', $count, 'wp-silent-witness' ), $count ) );
			} else {
				WP_CLI::error( $count );
			}
		} elseif ( 'export' === $action ) {
			$results = $wpdb->get_results( "SELECT * FROM $this->table ORDER BY last_seen DESC" );
			echo json_encode( $results ?: [], JSON_PRETTY_PRINT );
		} elseif ( 'clear' === $action ) {
			$wpdb->query( "TRUNCATE TABLE $this->table" );
			update_site_option( 'silent_witness_log_offset', 0 );
			WP_CLI::success( __( 'Cleared.', 'wp-silent-witness' ) );
		} elseif ( 'destroy' === $action ) {
			if ( ! isset( $args[1] ) || '--yes' !== $args[1] ) {
				WP_CLI::error( __( 'Use: wp silent-witness destroy --yes', 'wp-silent-witness' ) );
			}
			wp_clear_scheduled_hook( 'silent_witness_cron_ingest' );
			$wpdb->query( "DROP TABLE IF EXISTS $this->table" );
			delete_site_option( 'silent_witness_log_offset' );
			WP_CLI::success( __( 'Destroyed.', 'wp-silent-witness' ) );
		} else {
			WP_CLI::error( __( 'Usage: wp silent-witness [ingest|export|clear|destroy]', 'wp-silent-witness' ) );
		}
	}
}

// Add custom schedule if it doesn't exist.
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['quarterly'] = [
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'wp-silent-witness' ),
		];
		return $schedules;
	}
);

WP_Silent_Witness::init();
