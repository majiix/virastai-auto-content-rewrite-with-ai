<?php
/**
 * Plugin Name: Virastai Auto Content Rewrite With AI
 * Plugin URI:  https://wordpress.org/plugins/virastai-auto-content-rewrite-with-ai
 * Description: Connects to OpenRouter (Large Language Models) to rewrite or translate WooCommerce product content automatically.
 * Version:     1.5.0
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
define( 'VIRASTAI_ACR_VERSION', '1.5.0' );
define( 'VIRASTAI_ACR_PATH', plugin_dir_path( __FILE__ ) );
define( 'VIRASTAI_ACR_URL', plugin_dir_url( __FILE__ ) );

// Include Classes.
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-rewriter.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-admin.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-admin-sidebar.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-metabox.php';
require_once VIRASTAI_ACR_PATH . 'includes/class-virastai-acr-cron.php';

/**
 * Initialize the plugin.
 */
function virastai_acr_init() {
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
}
register_deactivation_hook( __FILE__, 'virastai_acr_deactivate' );
