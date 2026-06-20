<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Virastai_Auto_Content_Rewrite_With_AI
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Clear Scheduled Cron Events.
wp_clear_scheduled_hook( 'virastai_acr_process_content_event' );
wp_clear_scheduled_hook( 'virastai_acr_daily_cleanup_event' );

// Check if user allowed deleting options and data on uninstall.
$virastai_acr_delete_data = get_option( 'virastai_acr_delete_on_uninstall', 0 );
if ( ! $virastai_acr_delete_data ) {
	return;
}

// 2. Delete Plugin Options.
$virastai_options_to_delete = array(
	'virastai_acr_openrouter_api_key',
	'virastai_acr_ai_model',
	'virastai_acr_enable_logging',
	'virastai_acr_log_retention',
	'virastai_acr_cron_enabled',
	'virastai_acr_cron_interval',
	'virastai_acr_cron_batch_size',
	'virastai_acr_allowed_post_types',
	'virastai_acr_allowed_post_statuses',
	'virastai_acr_base_prompt_template',
	'virastai_acr_enable_content_rewrite',
	'virastai_acr_content_skip_incomplete',
	'virastai_acr_preserve_images',
	'virastai_acr_enable_formatting',
	'virastai_acr_prompt_template',
	'virastai_acr_enable_excerpt_rewrite',
	'virastai_acr_excerpt_skip_incomplete',
	'virastai_acr_excerpt_prompt_template',
	'virastai_acr_enable_title_rewrite',
	'virastai_acr_title_skip_incomplete',
	'virastai_acr_remove_title_html',
	'virastai_acr_title_prompt_template',
	'virastai_acr_enable_sandbox_mode',
	'virastai_acr_allowed_roles',
	'virastai_acr_delete_on_uninstall',
);

foreach ( $virastai_options_to_delete as $virastai_option_name ) {
	delete_option( $virastai_option_name );
}

// 3. Delete Post Meta Data.
delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true );
delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true );
delete_metadata( 'post', 0, '_virastai_acr_excerpt_generated', '', true );
delete_metadata( 'post', 0, '_virastai_acr_sandbox_title', '', true );
delete_metadata( 'post', 0, '_virastai_acr_sandbox_content', '', true );
delete_metadata( 'post', 0, '_virastai_acr_sandbox_excerpt', '', true );

// 4. Delete Model Cache Transient.
delete_transient( 'virastai_acr_models_cache' );

// 5. Drop Database Table.
global $wpdb;
$virastai_table_name = esc_sql( $wpdb->prefix . 'virastai_acr_logs' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS $virastai_table_name" );
