# Specification: Resilient Background Processing and Error Prevention

## 1. Overview
This specification details the robustness, error prevention, and performance enhancement requirements for the Virastai Auto Content Rewrite With AI WordPress plugin. The main goal is to ensure the plugin operates without creating resource bottlenecks, database bloat, PHP timeouts, or HTTP 500 errors on the site.

## 2. User Scenarios
- **Scenario A (Missing configuration)**: A site administrator activates the plugin but does not supply an API key. Background cron jobs should exit silently and cleanly without generating database error logs or repeating API connection checks.
- **Scenario B (API Downtime or Key Expiry)**: The site runs background rewrites, but OpenRouter returns persistent error codes or the network is down. The system must prevent infinite API retries on the same posts during subsequent cron execution batches.
- **Scenario C (Unprocessed/Empty Posts)**: Posts that are empty or skipped due to "Skip Incomplete Posts" must not be repeatedly processed in every cron run. The system must mark them as skipped/handled, but dynamically clear this status if they are updated with actual content.
- **Scenario D (Timeouts on Heavy Batches)**: A cron run processes a batch of posts. Due to slow API responses, the cumulative execution time approaches the PHP server's maximum execution limit. The system must detect this and break the batch loop cleanly before a PHP execution timeout (HTTP 500) occurs.
- **Scenario E (System Overhead)**: On every public frontend or backend request, the plugin must not execute table existence checking queries or register redundant schedule events, ensuring zero performance regression.

## 3. Requirements

### 3.1 Verification of API Config
- The cron runner must verify that the OpenRouter API Key is non-empty before initiating any query or loop. If empty, the cron process must exit immediately.

### 3.2 Resilience and Fault Tolerance (Retry Limiter)
- Tracks failed API/decoding attempts on a per-post basis using a dedicated metadata field.
- Limits retries to 3 attempts. Upon reaching 3 failed attempts, the post's status is marked as `'failed'` for the requested fields to exclude it from subsequent cron runs.
- Reset the failure count when:
  - The post is saved/updated by the user.
  - The sandbox metadata is cleared via the metabox or reset options.
  - A manual generation is successful.

### 3.3 Safe Handling of Skipped Empty Posts
- If a post is skipped because it is empty (and "Skip Incomplete Posts" option is enabled), it must be marked as `'skipped'` on the corresponding fields.
- When a post is saved and its content is updated to a non-empty string, any `'skipped'` or `'failed'` flags must be automatically removed, enabling background reprocessing.

### 3.4 Runtime Timeout Safeguard
- During batch cron processing, the execution time must be monitored.
- Compare elapsed execution time against the server's `max_execution_time` (with a default fallback if unset).
- If the elapsed time is within a 5-second safety threshold of the maximum execution time, stop the batch processing loop and log an informational message.

### 3.5 Database Performance Optimization
- Remove table existence checks from request-level initialization. The table must be created once on plugin activation.

## 4. Success Criteria
- No PHP warnings, notices, or fatal errors during cron runs.
- Zero infinite retry loops on failing API calls or empty posts.
- Zero database logs spamming under error/missing-key conditions.
- No HTTP 500 execution timeouts during batch processes on slow API connections.
- Verification checks (e.g. `php -l`) pass with no issues.
