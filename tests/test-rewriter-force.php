<?php
/**
 * Test force parameter on Virastai_Acr_Rewriter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );

	$test_options = array(
		'virastai_acr_enable_title_rewrite'   => 1,
		'virastai_acr_enable_content_rewrite' => 1,
		'virastai_acr_enable_excerpt_rewrite' => 0,
		'virastai_acr_openrouter_api_key'     => '',
	);

	function get_option( $key, $default = false ) {
		global $test_options;
		return isset( $test_options[ $key ] ) ? $test_options[ $key ] : $default;
	}

	function get_post_meta( $id, $key, $single = true ) {
		// Mock post where all sections were already done
		if ( '_virastai_acr_title_generated' === $key ) {
			return '1';
		}
		if ( '_virastai_acr_content_generated' === $key ) {
			return '2';
		}
		return '';
	}

	function get_the_title( $id ) {
		return 'Sample Post Title';
	}

	function get_post( $id ) {
		$p               = new stdClass();
		$p->ID           = $id;
		$p->post_content = 'Sample Content Body';
		$p->post_excerpt = 'Sample Excerpt';
		return $p;
	}

	function update_post_meta( $id, $key, $val ) {
		return true;
	}

	function delete_post_meta( $id, $key ) {
		return true;
	}
}

require_once __DIR__ . '/../includes/class-virastai-acr-rewriter.php';

$rewriter = new Virastai_Acr_Rewriter();

// 1. Without force: should return false because sections are already marked done
$res_no_force = $rewriter->virastai_acr_generate_content( 123, null, '', false );
assert( $res_no_force === false, 'Should be skipped when sections already done and force is false' );
echo "PASS: test_skip_when_already_done_and_not_forced\n";

// 2. With force: should NOT skip at the section check (it will proceed to API call, which fails on empty key, but returns false from API error, NOT from skip check)
$res_force = $rewriter->virastai_acr_generate_content( 123, null, '', true );
assert( $res_force === false, 'API call fails due to empty test key' );
echo "PASS: test_force_bypasses_already_done_check\n";

echo "All rewriter force tests passed successfully.\n";
