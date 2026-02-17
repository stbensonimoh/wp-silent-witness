<?php
/**
 * WP Silent Witness Uninstall
 *
 * This file is called when the plugin is deleted.
 * It wipes all data and the custom database table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'silent_witness_logs';

// Drop the custom table
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Clean up the file offset tracking option
delete_site_option( 'silent_witness_log_offset' );
