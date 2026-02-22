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
$table_name = $wpdb->base_prefix . 'silent_witness_logs';

// Drop the custom table.
$table = esc_sql( $table_name );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

// Clean up the file offset tracking option.
delete_site_option( 'silent_witness_log_offset' );
