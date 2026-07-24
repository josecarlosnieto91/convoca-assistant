<?php
/**
 * Uninstall handler for Convoca Assistant.
 *
 * Cleans up all plugin data: options, transients, custom tables, index files.
 *
 * @package Convoca\Assistant
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/* ── Options ──────────────────────────────────────── */
$convoca_assistant_options = array(
	'convoca_assistant_version',
	'convoca_assistant_db_version',
	'convoca_assistant_settings',
	'convoca_assistant_synonyms',
	'convoca_assistant_stop_words',
	'convoca_assistant_index_hash',
	'convoca_assistant_maintenance',
	'convoca_assistant_index_schema',
	'convoca_assistant_index_generated',
);

foreach ( $convoca_assistant_options as $convoca_assistant_option ) {
	delete_option( $convoca_assistant_option );
}

/* ── Transients ───────────────────────────────────── */
delete_transient( 'convoca_assistant_index_dirty' );
delete_transient( 'convoca_assistant_index_debounce' );

/* ── Scheduled hooks ──────────────────────────────── */
wp_clear_scheduled_hook( 'convoca_assistant_regenerate' );
wp_clear_scheduled_hook( 'convoca_assistant_log_cleanup' );

/* ── Custom table ─────────────────────────────────── */
global $wpdb;
$convoca_assistant_table = $wpdb->prefix . 'convoca_assistant_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$convoca_assistant_table}" );

/* ── Index files ──────────────────────────────────── */
$convoca_assistant_upload_dir = wp_upload_dir();
$convoca_assistant_index_dir  = $convoca_assistant_upload_dir['basedir'] . '/convoca-assistant/';

if ( is_dir( $convoca_assistant_index_dir ) ) {
	$convoca_assistant_files = glob( $convoca_assistant_index_dir . 'index.*' );
	if ( is_array( $convoca_assistant_files ) ) {
		foreach ( $convoca_assistant_files as $convoca_assistant_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_file_delete
			unlink( $convoca_assistant_file );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_rmdir
	@rmdir( $convoca_assistant_index_dir );
}
