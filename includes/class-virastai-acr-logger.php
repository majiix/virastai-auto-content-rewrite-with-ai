<?php
/**
 * Class Virastai_Acr_Logger
 *
 * Handles database logging for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virastai_Acr_Logger {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'virastai_acr_logs';
	}

	/**
	 * Initialize DB table and daily cleanup scheduling.
	 */
	public static function init() {
		self::create_table();

		// Schedule daily cleanup.
		if ( ! wp_next_scheduled( 'virastai_acr_daily_cleanup_event' ) ) {
			wp_schedule_event( time(), 'daily', 'virastai_acr_daily_cleanup_event' );
		}
		add_action( 'virastai_acr_daily_cleanup_event', array( __CLASS__, 'clean_old_logs' ) );
	}

	/**
	 * Create the exclusive logs table.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table already exists to avoid redundant dbDelta calls.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			type varchar(50) NOT NULL,
			message longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log a message to the database table.
	 *
	 * @param string $type    The type of log (e.g., info, error, api_request, api_response).
	 * @param string $message The message or structured data.
	 */
	public static function log( $type, $message ) {
		$logging_enabled = get_option( 'virastai_acr_enable_logging', 0 );
		if ( ! $logging_enabled ) {
			return;
		}

		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );

		// Sanitize or json encode if array/object.
		if ( ! is_string( $message ) ) {
			$message = wp_json_encode( $message );
		}

		// Mask authorization keys/API tokens for security.
		$message = preg_replace( '/"Authorization"\s*:\s*"Bearer\s+[^"]+"/', '"Authorization":"Bearer [MASKED]"', $message );
		$message = preg_replace( '/Authorization:\s*Bearer\s+[^\r\n]+/', 'Authorization: Bearer [MASKED]', $message );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			array(
				'type'       => sanitize_text_field( $type ),
				'message'    => $message,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Get logs from the database table.
	 *
	 * @param int $limit  Max logs to retrieve.
	 * @param int $offset Offset.
	 * @return array
	 */
	public static function get_logs( $limit = 500, $offset = 0 ) {
		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, type, message FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Count total logs in the database.
	 *
	 * @return int
	 */
	public static function count_logs() {
		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Clear all logs from the database table.
	 */
	public static function clear_logs() {
		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "TRUNCATE TABLE $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Cleanup logs older than the specified retention days.
	 */
	public static function clean_old_logs() {
		$retention_days = (int) get_option( 'virastai_acr_log_retention', 7 );
		if ( $retention_days <= 0 ) {
			return; // Keep logs indefinitely.
		}

		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );
		$cutoff_time = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $retention_days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff_time
			)
		);
	}

	/**
	 * Helper to get all logs formatted as a single string.
	 *
	 * @return string
	 */
	public static function get_logs_formatted() {
		$logs = self::get_logs();
		if ( empty( $logs ) ) {
			return __( 'No logs recorded yet.', 'virastai-auto-content-rewrite-with-ai' );
		}

		$formatted = '';
		foreach ( $logs as $log ) {
			$formatted .= sprintf(
				"[%s] [%s] %s\n",
				$log['created_at'],
				strtoupper( $log['type'] ),
				$log['message']
			);
		}

		return $formatted;
	}
}
