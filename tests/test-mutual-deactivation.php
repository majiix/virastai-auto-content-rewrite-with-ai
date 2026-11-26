<?php
/**
 * Unit tests for conflict-free mutual auto-deactivation between Free and Pro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
	define( 'VIRASTAI_ACR_VERSION', '1.7.0' );

	$deactivated_plugins = array();
	$mock_active_plugins = array();

	function register_activation_hook( $file, $callback ) {}
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
	function get_option( $key, $default = false ) {
		global $mock_active_plugins;
		if ( 'active_plugins' === $key ) {
			return $mock_active_plugins;
		}
		return $default;
	}
	function is_multisite() {
		return false;
	}
	function is_network_admin() {
		return false;
	}
	function deactivate_plugins( $plugins, $silent = false, $network_wide = false ) {
		global $deactivated_plugins;
		$deactivated_plugins = array_merge( $deactivated_plugins, (array) $plugins );
	}

	if ( ! class_exists( 'Virastai_Acr_Logger' ) ) {
		class Virastai_Acr_Logger {
			public static function create_table() {}
		}
	}
}

// Including Free main file while VIRASTAI_ACR_VERSION is already defined must not redeclare anything.
require_once __DIR__ . '/../virastai-auto-content-rewrite-with-ai.php';
assert( function_exists( 'virastai_acr_free_activate' ), 'Free activation hook function must exist' );

$mock_active_plugins = array(
	'virastai-auto-content-rewrite-with-ai-pro/virastai-auto-content-rewrite-with-ai-pro.php',
	'akismet/akismet.php',
);
$deactivated_plugins = array();
virastai_acr_free_activate();
assert(
	$deactivated_plugins === array( 'virastai-auto-content-rewrite-with-ai-pro/virastai-auto-content-rewrite-with-ai-pro.php' ),
	'Free activation must deactivate Pro plugin only'
);
echo "PASS: test_free_activation_deactivates_pro_without_conflict\n";
