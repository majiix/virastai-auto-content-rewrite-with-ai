# Project Overview: Virastai Auto Content Rewrite With AI

A WordPress plugin that connects to OpenRouter to rewrite or translate WooCommerce product and post content automatically in the background.

## Tech Stack
- PHP (WordPress coding standards)
- jQuery & WordPress core Javascript APIs
- Custom CSS styles
- OpenRouter API integration

## Dependencies
- WordPress core (version 5.8 or higher)
- WooCommerce (optional, for product content type handling)
- An active OpenRouter account and API key

## System Architecture
1. **Entrypoint**: Bootstraps the plugin, registers constants, hooks save_post events to clear skipped/failed status flags on edited posts, and schedules background cron tasks.
2. **Rewriter Engine**: Connects to the external OpenRouter endpoint to perform content updates. Supports a JSON schema mode, skipped content flags for empty inputs, and a 3-attempt retry limiter to avoid infinite loop calls.
3. **Cron Manager**: Handles periodic batch execution. Validates config presence, queries target post types randomly, and monitors execution runtime to abort the batch loop early if nearing PHP max_execution_time limits.
4. **Admin Panel**: Manages API credentials, dynamic model retrieval, prompt options, Reset to Default templates, and tabbed settings pages (General, Automation, Content, Title, Logs, Help).
5. **Sandbox Review**: Displays generated content inside a metabox for preview before saving changes to the live fields. Access to see and use the metabox is restricted based on configured Post Types, Allowed User Roles, and Post Statuses. Supports selective generation of title, content, or excerpt, includes individual copy-to-clipboard buttons, and dynamically displays control actions based on content presence.
6. **Statistics**: Monitors rewriting progress on the settings sidebar, featuring inline trash buttons to reset progress or clear sandbox content directly.
7. **Database Logger**: Tracks all system events, API requests (with API token headers and bodies masked for security), API responses, and errors inside an exclusive database table. Automatically manages retention cleanup via a daily cron event. Avoids table existence checks on every request.
8. **Uninstall Hook**: Triggers uninstallation cleanup. Clears background cron schedules unconditionally, and conditionally deletes database options, custom logging tables, and post metadata (including retry counters) based on the configured settings option.

## Verification Commands
- Check PHP syntax:
  `php -l virastai-auto-content-rewrite-with-ai.php`
  `php -l includes/class-virastai-acr-*.php`
