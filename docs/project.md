# Project Overview: Virastai Auto Content Rewrite With AI

## Overview
Virastai Auto Content Rewrite With AI (v1.6.0) is an enterprise-grade WordPress plugin designed to automatically rewrite, translate, and optimize posts, pages, and custom post types using Large Language Models via the OpenRouter API.

## Tech Stack & Architecture
- **Language**: PHP 8.0+
- **Platform**: WordPress 6.0+
- **Frontend**: Vanilla JavaScript (ES6+), jQuery, CSS3
- **External APIs**: OpenRouter API (`https://openrouter.ai/api/v1/chat/completions`)
- **Background Execution**: WordPress Cron (`wp_schedule_single_event`, `wp_schedule_event`), AJAX polling
- **Architecture**: Modular object-oriented PHP architecture:
  - `virastai-auto-content-rewrite-with-ai.php`: Plugin bootstrap, lifecycle hooks, component instantiation.
  - `includes/class-virastai-acr-rewriter.php`: LLM API communication and rewriting engine.
  - `includes/class-virastai-acr-admin.php`: Settings page, tab rendering, admin assets, logs viewer.
  - `includes/class-virastai-acr-custom-automation.php`: Date-range automated rewrite runner, cron scheduler, AJAX lifecycle handlers.
  - `includes/class-virastai-acr-cron.php`: General background scheduled rewriter engine.
  - `includes/class-virastai-acr-metabox.php`: Post-level sandbox preview, generation, and manual review metabox.
  - `includes/class-virastai-acr-logger.php`: Custom MySQL logging table and log management.

## Current Features
1. **General Settings**:
   - OpenRouter API key management with masked input and visibility toggling.
   - Dynamic model fetching from OpenRouter with offline fallbacks.
2. **Standard Automation**:
   - Background WP-Cron scheduler running at configurable intervals (1m, 5m, 15m, 30m, hourly, daily).
   - Post type and post status filtering.
   - Batch size controls to prevent server timeouts.
3. **Custom Automation (New)**:
   - Dedicated admin tab for date-range targeted rewrites.
   - Date range selector filtering by publication date (`post_date`) with automatic ISO `Y-m-d` date normalization.
   - Clear job scope indicator on status card displaying the exact date range of the active or completed job.
   - Throughput rate control (configurable posts per minute between 1 and 60).
   - Granular post type selection and optional force rewrite toggle with complete overwrite support.
   - Dynamic real-time preview counts badge (displaying how many posts will be rewritten vs already rewritten, updated via AJAX on parameter changes).
   - Interactive lifecycle controls: Start, Pause, and Stop.
   - One-click "Retry Failed" control to reset and requeue failed or skipped posts automatically with meta cleanup.
   - Live AJAX progress tracker with percentage readout, active post indicators, and metric counters.
   - Dual execution engine: responsive in-browser AJAX ticks paired with background WP-Cron runner and transient lock protection.
4. **Content & Title Rewrite Options**:
   - Independent toggles for Content, Title, and Excerpt rewriting.
   - Custom prompt instructions and placeholder support (`{post_title}`, `{post_content}`).
   - HTML preservation, image stripping/preservation controls.
5. **Sandbox Review Mode**:
   - Non-destructive drafts stored in post meta for manual review before overwriting live post content.
6. **System Logging & Observability**:
   - Dedicated database logging table with pagination, copy-to-clipboard, and modal inspection.
   - High-performance log modal viewer with structured section formatting and non-blocking rendering.
   - Failures and warnings (e.g. empty content, disabled options, API errors, timeouts) are always recorded to eliminate silent failures, even when verbose logging is turned off.
7. **Security & Background Capability Handling**:
   - Automatic user context switching during WP-Cron and background automation: switches temporarily to the post author or an administrator so core hooks and security firewalls (like NinjaFirewall) recognize authorized capabilities, then restores initial context.

## Verification Commands
Run unit tests from the plugin root directory:
```bash
php tests/test-custom-automation.php
php tests/test-rewriter-force.php
php tests/test-rewriter-capability.php
php tests/test-custom-automation-rate-limit.php
php tests/test-rewriter-atomic-meta.php
```

Lint PHP files for syntax correctness:
```bash
php -l virastai-auto-content-rewrite-with-ai.php
php -l includes/class-virastai-acr-custom-automation.php
php -l includes/class-virastai-acr-rewriter.php
php -l includes/class-virastai-acr-admin.php
```
