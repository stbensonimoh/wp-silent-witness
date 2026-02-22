<?php
/**
 * WP Silent Witness Uninstall
 *
 * This file is called when the plugin is deleted.
 * It wipes all data and the custom database table.
 *
 * @package WP_Silent_Witness
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'silent_witness_logs';

// Drop the custom table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `%1$s`', $table_name ) );

// Clean up the file offset tracking option.
delete_site_option( 'silent_witness_log_offset' );
