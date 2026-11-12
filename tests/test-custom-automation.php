<?php
/**
 * Unit tests for Virastai_Acr_Custom_Automation.
 */

// Mock WordPress functions if running standalone via CLI
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );

	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	function get_option( $key, $default = false ) {
		return $default;
	}

	function update_option( $key, $val ) {
		return true;
	}

	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}

	class Virastai_Acr_Rewriter {}

	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			public $found_posts = 0;
			public function __construct( $args = array() ) {
				if ( isset( $args['meta_query'] ) ) {
					$this->found_posts = 2;
				} else {
					$this->found_posts = 5;
				}
			}
		}
	}
}

require_once __DIR__ . '/../includes/class-virastai-acr-custom-automation.php';

class Test_Virastai_Acr_Custom_Automation {

	private $ca;

	public function setUp() {
		$rewriter = new Virastai_Acr_Rewriter();
		$this->ca = new Virastai_Acr_Custom_Automation( $rewriter );
	}

	public function test_calculate_counts_logic() {
		// When include_already_rewritten is false
		$counts = $this->ca->calculate_counts( '2026-01-01', '2026-06-30', array( 'post' ), false );
		assert( $counts['total_in_range'] === 5, 'Total in range should be 5' );
		assert( $counts['unrewritten'] === 2, 'Unrewritten should be 2' );
		assert( $counts['already_rewritten'] === 3, 'Already rewritten should be 3' );
		assert( $counts['will_be_rewritten'] === 2, 'Will be rewritten should be 2 when false' );

		// When include_already_rewritten is true
		$counts_force = $this->ca->calculate_counts( '2026-01-01', '2026-06-30', array( 'post' ), true );
		assert( $counts_force['will_be_rewritten'] === 5, 'Will be rewritten should be 5 when true' );
		assert( $counts_force['already_rewritten'] === 3, 'Already rewritten should remain 3' );

		// Invalid date range
		$counts_invalid = $this->ca->calculate_counts( '2026-09-01', '2026-01-01', array( 'post' ), false );
		assert( $counts_invalid['will_be_rewritten'] === 0, 'Invalid range should return 0' );
		echo "PASS: test_calculate_counts_logic\n";
	}

	public function test_build_query_args_typical() {
		$params = array(
			'start_date'                => '2026-01-01',
			'end_date'                  => '2026-06-30',
			'post_types'                => array( 'post', 'product' ),
			'include_already_rewritten' => false,
			'processed_ids'             => array( 101, 102 ),
		);

		$args = $this->ca->build_query_args( $params, 5 );

		assert( $args['post_type'] === array( 'post', 'product' ), 'Post types mismatch' );
		assert( $args['posts_per_page'] === 5, 'Posts per page mismatch' );
		assert( $args['post__not_in'] === array( 101, 102 ), 'Excluded IDs mismatch' );
		assert( isset( $args['date_query'][0] ), 'Date query missing' );
		assert( $args['date_query'][0]['after'] === '2026-01-01 00:00:00', 'After date mismatch' );
		assert( $args['date_query'][0]['before'] === '2026-06-30 23:59:59', 'Before date mismatch' );
		assert( $args['date_query'][0]['column'] === 'post_date', 'Date column should be post_date' );
		assert( isset( $args['meta_query'] ), 'Meta query should exist when include_already_rewritten is false' );
		echo "PASS: test_build_query_args_typical\n";
	}

	public function test_build_query_args_force_rewrite_edge_case() {
		$params = array(
			'start_date'                => '2026-05-01',
			'end_date'                  => '2026-05-10',
			'post_types'                => array( 'post' ),
			'include_already_rewritten' => true,
		);

		$args = $this->ca->build_query_args( $params, 10 );

		assert( ! isset( $args['meta_query'] ), 'Meta query should NOT exist when include_already_rewritten is true' );
		echo "PASS: test_build_query_args_force_rewrite_edge_case\n";
	}

	public function test_build_query_args_empty_defaults() {
		$params = array();
		$args   = $this->ca->build_query_args( $params, -1 );

		assert( $args['post_type'] === array( 'post' ), 'Default post type should be post' );
		assert( $args['fields'] === 'ids', 'Limit -1 should request ids field' );
		assert( isset( $args['date_query'][0] ), 'Default date query must be present' );
		echo "PASS: test_build_query_args_empty_defaults\n";
	}

	public function test_build_query_args_date_normalization() {
		$params = array(
			'start_date' => '01/28/2022',
			'end_date'   => '12/31/2022',
		);
		$args = $this->ca->build_query_args( $params, 5 );
		assert( $args['date_query'][0]['after'] === '2022-01-28 00:00:00', 'Normalized start date mismatch' );
		assert( $args['date_query'][0]['before'] === '2022-12-31 23:59:59', 'Normalized end date mismatch' );
		echo "PASS: test_build_query_args_date_normalization\n";
	}

	public function test_state_defaults() {
		$state = $this->ca->get_state();
		assert( isset( $state['posts_per_minute'] ), 'posts_per_minute must be present in defaults' );
		assert( $state['posts_per_minute'] === 5, 'posts_per_minute default should be 5' );
		assert( $state['status'] === 'idle', 'status default should be idle' );
		assert( isset( $state['failed_ids'] ) && is_array( $state['failed_ids'] ), 'failed_ids must be array in defaults' );
		echo "PASS: test_state_defaults\n";
	}

	public function runAll() {
		$this->setUp();
		$this->test_state_defaults();
		$this->test_calculate_counts_logic();
		$this->test_build_query_args_typical();
		$this->test_build_query_args_force_rewrite_edge_case();
		$this->test_build_query_args_empty_defaults();
		$this->test_build_query_args_date_normalization();
		echo "All tests passed successfully.\n";
	}
}

$tester = new Test_Virastai_Acr_Custom_Automation();
$tester->runAll();
