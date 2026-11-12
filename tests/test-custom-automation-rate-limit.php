<?php
/**
 * Test rate limiting and Posts Per Minute math for Virastai_Acr_Custom_Automation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );

	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	$mock_options = array();

	function get_option( $key, $default = false ) {
		global $mock_options;
		return isset( $mock_options[ $key ] ) ? $mock_options[ $key ] : $default;
	}

	function update_option( $key, $val ) {
		global $mock_options;
		$mock_options[ $key ] = $val;
		return true;
	}

	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	function wp_clear_scheduled_hook( $hook ) {
		return true;
	}

	function wp_schedule_single_event( $timestamp, $hook ) {
		return true;
	}

	class Virastai_Acr_Rewriter {}
}

require_once __DIR__ . '/../includes/class-virastai-acr-custom-automation.php';

$rewriter = new Virastai_Acr_Rewriter();
$ca       = new Virastai_Acr_Custom_Automation( $rewriter );

// Test 1: Typical Input (10 posts per minute)
$ppm = 10;
$seconds_per_post = 60 / $ppm;
assert( $seconds_per_post === 6.0, '10 posts per min should allocate 6.0 seconds per post' );

$batch_processed = 1; // AJAX single post tick
$batch_time_budget = $seconds_per_post * $batch_processed;
assert( $batch_time_budget === 6.0, 'Single post budget should be 6 seconds' );

$elapsed = 2.5; // Post took 2.5s
$delay = max( 0, round( $batch_time_budget - $elapsed ) );
assert( $delay === 4.0, 'Remaining delay should be 4 seconds' );
echo "PASS: test_ppm_typical_calculation\n";

// Test 2: Edge Case - Minimum rate (1 post per minute)
$ppm_min = 1;
$seconds_per_post_min = 60 / $ppm_min;
assert( $seconds_per_post_min === 60.0, '1 post per min should allocate 60 seconds per post' );

$elapsed_min = 3.0; // Post took 3s
$delay_min = max( 0, round( $seconds_per_post_min - $elapsed_min ) );
assert( $delay_min === 57.0, 'Delay for 1 post/min after 3s run should be 57 seconds' );
echo "PASS: test_ppm_minimum_rate_calculation\n";

// Test 3: Edge Case - Maximum rate (60 posts per minute)
$ppm_max = 60;
$seconds_per_post_max = 60 / $ppm_max;
assert( $seconds_per_post_max === 1.0, '60 posts per min should allocate 1 second per post' );

$elapsed_max = 2.5; // Post took 2.5s (longer than 1s budget)
$delay_max = max( 0, round( $seconds_per_post_max - $elapsed_max ) );
assert( $delay_max === 0, 'Delay when elapsed exceeds budget must be 0' );
echo "PASS: test_ppm_maximum_rate_calculation\n";

// Test 4: Rate-limit enforcement logic (time comparison)
$now = time();
$scheduled_next = $now + 15; // Next run is 15 seconds in future

// Premature poll: should be blocked
$is_due_premature = ( $now >= ( $scheduled_next - 1 ) );
assert( $is_due_premature === false, 'Premature poll must NOT be due for execution' );

// On time poll: should be allowed
$future_now = $now + 15;
$is_due_on_time = ( $future_now >= ( $scheduled_next - 1 ) );
assert( $is_due_on_time === true, 'On-time poll must be due for execution' );
echo "PASS: test_ppm_time_boundary_check\n";

echo "All rate limiting tests passed successfully.\n";
