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

// 1. Clear Scheduled Cron Event.
$virastai_timestamp = wp_next_scheduled( 'virastai_acr_process_content_event' );
if ( $virastai_timestamp ) {
	wp_unschedule_event( $virastai_timestamp, 'virastai_acr_process_content_event' );
}
wp_clear_scheduled_hook( 'virastai_acr_process_content_event' );

// 2. Delete Plugin Options.
$virastai_options_to_delete = array(
	'virastai_acr_openrouter_api_key',
	'virastai_acr_ai_model',
	'virastai_acr_enable_logging',
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
	'virastai_acr_enable_title_rewrite',
	'virastai_acr_title_skip_incomplete',
	'virastai_acr_remove_title_html',
	'virastai_acr_title_prompt_template',
	'virastai_acr_enable_sandbox_mode',
);

foreach ( $virastai_options_to_delete as $virastai_option_name ) {
	delete_option( $virastai_option_name );
}

// 3. Delete Post Meta Data.
delete_metadata( 'post', 0, '_virastai_acr_content_generated', '', true );
delete_metadata( 'post', 0, '_virastai_acr_title_generated', '', true );
delete_metadata( 'post', 0, '_virastai_acr_sandbox_title', '', true );
delete_metadata( 'post', 0, '_virastai_acr_sandbox_content', '', true );

// 4. Delete Model Cache Transient.
delete_transient( 'virastai_acr_models_cache' );
