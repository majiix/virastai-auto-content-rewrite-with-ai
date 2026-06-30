# Technical Implementation Plan: Resilient Background Processing and Error Prevention

This document describes the technical details for modifying the plugin's cron, database, and logic files to prevent timeouts, 500 errors, and database bloat.

## System Architecture Updates
1. **Request Lifecycle**: Remove the database check `SHOW TABLES` from `plugins_loaded` hook. The logger table must only be checked and created during plugin activation.
2. **Cron Processing**:
   - Add early exit checking for empty settings config.
   - Limit execution time using standard PHP time functions to break out of processing loops before timeouts occur.
3. **Skipping / Failure Lifecycle**:
   - Set intermediate metadata states: `'skipped'` for posts that do not meet processing conditions, and `'failed'` for posts that persistently fail API connectivity or parsing.
   - Clear these flags during `save_post` hooks when the posts are edited.

## Component Structure & Code Modifications

### 1. `virastai-auto-content-rewrite-with-ai.php`
Register the post save hook to clear skipped or failed statuses when content is added:
```php
add_action( 'save_post', 'virastai_acr_clear_skipped_status', 10, 2 );

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
```

### 2. `includes/class-virastai-acr-logger.php`
Modify the `init()` method:
```php
	public static function init() {
		// Schedule daily cleanup.
		if ( ! wp_next_scheduled( 'virastai_acr_daily_cleanup_event' ) ) {
			wp_schedule_event( time(), 'daily', 'virastai_acr_daily_cleanup_event' );
		}
		add_action( 'virastai_acr_daily_cleanup_event', array( __CLASS__, 'clean_old_logs' ) );
	}
```

### 3. `includes/class-virastai-acr-cron.php`
Update `virastai_acr_run_batch_process()`:
```php
		$api_key = get_option( 'virastai_acr_openrouter_api_key', '' );
		if ( empty( trim( $api_key ) ) ) {
			return; // Avoid runs and logs bloat if credentials are unset.
		}
```
Add loop timeout safety check using:
```php
		$start_time = microtime( true );
		$max_time   = (int) ini_get( 'max_execution_time' );
		if ( $max_time <= 0 ) {
			$max_time = 30;
		}
		$time_limit = $max_time - 5;
		if ( $time_limit < 10 ) {
			$time_limit = 10;
		}
```

### 4. `includes/class-virastai-acr-rewriter.php`
- Handle `$post` query fallback inside `virastai_acr_generate_content()`.
- Record `'skipped'` if `empty( trim( $current_content ) )` is true.
- Record retry counts on failure; transition to `'failed'` status if attempt limit (3) is exceeded.

### 5. Cleanup hooks in `class-virastai-acr-admin.php`, `class-virastai-acr-metabox.php`, and `uninstall.php`
- Clean `_virastai_acr_retry_count` meta key on uninstall, progress reset, or manual sandbox clearing.

## Verification
- Syntactic check of all PHP files.
- Manual verification of cron logic and execution time limiting.
