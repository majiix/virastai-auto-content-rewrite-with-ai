<?php
/**
 * Plugin Name: Virastai Auto Content Rewrite With AI
 * Plugin URI:  https://wordpress.org/plugins/virastai-auto-content-rewrite-with-ai
 * Description: Automatically rewrite, translate, and optimize WordPress posts, pages, and post types in the background or manually using Large Language Models.
 * Version:     1.5.2
 * Author:      micromax
 * Text Domain: virastai-auto-content-rewrite-with-ai
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants with 'VIRASTAI_ACR_' prefix.
define( 'VIRASTAI_ACR_VERSION', '1.5.2' );
define( 'VIRASTAI_ACR_PATH', plugin_dir_path( __FILE__ ) );
define( 'VIRASTAI_ACR_URL', plugin_dir_url( __FILE__ ) );

// Include Classes.
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-logger.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-rewriter.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-admin.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-admin-sidebar.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-metabox.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-cron.php';

/**
 * Run table creation on plugin activation.
 */
function virastai_acr_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-virastai-acr-logger.php';
	Virastai_Acr_Logger::create_table();
}
register_activation_hook( __FILE__, 'virastai_acr_activate' );

/**
 * Initialize the plugin.
 */
function virastai_acr_init() {
	// Initialize Logger table and hooks.
	Virastai_Acr_Logger::init();

	// Initialize Rewriter Engine.
	$rewriter = new Virastai_Acr_Rewriter();

	// Initialize Admin Settings.
	if ( is_admin() ) {
		new Virastai_Acr_Admin();
		// Initialize Metabox logic (runs in admin context).
		new Virastai_Acr_Metabox();
	}

	// Initialize Cron Job.
	new Virastai_Acr_Cron( $rewriter );
}
add_action( 'plugins_loaded', 'virastai_acr_init' );

/**
 * Cleanup on deactivation.
 */
function virastai_acr_deactivate() {
	wp_clear_scheduled_hook( 'virastai_acr_process_content_event' );
	wp_clear_scheduled_hook( 'virastai_acr_daily_cleanup_event' );
}
register_deactivation_hook( __FILE__, 'virastai_acr_deactivate' );

/**
 * Clear skipped or failed generation metadata when post content is updated.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function virastai_acr_clear_skipped_status( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( empty( trim( $post->post_content ) ) ) {
		return;
	}

	$keys = array(
		'_virastai_acr_title_generated',
		'_virastai_acr_content_generated',
		'_virastai_acr_excerpt_generated',
		'_virastai_acr_retry_count',
	);

	foreach ( $keys as $key ) {
		$val = get_post_meta( $post_id, $key, true );
		if ( 'skipped' === $val || 'failed' === $val ) {
			delete_post_meta( $post_id, $key );
		}
	}
}
add_action( 'save_post', 'virastai_acr_clear_skipped_status', 10, 2 );
