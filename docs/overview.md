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
1. **Entrypoint**: Bootstraps the plugin, registers constants, and schedules background cron tasks.
2. **Rewriter Engine**: Connects to the external endpoint to perform content updates. Supports a JSON schema mode to guarantee parsed results contain title, body, and/or excerpt text.
3. **Cron Manager**: Handles periodic batch execution. Queries the database randomly to process posts without bottlenecking on empty ones.
4. **Admin Panel**: Manages API credentials, dynamic model retrieval, prompt options, Reset to Default templates, and tabbed settings pages.
5. **Sandbox Review**: Displays generated content inside a metabox for preview before saving changes to the live fields. Supports selective regeneration of title, content, or excerpt.
6. **Statistics & Actions**: Monitors rewriting progress on the settings sidebar, supporting bulk reset actions.
7. **Database Logger**: Tracks all system events, API requests (with API token headers and bodies masked for security), API responses, and errors inside an exclusive database table. Automatically manages retention cleanup via a daily cron event.

## Verification Commands
- Check PHP syntax:
  `php -l virastai-auto-content-rewrite-with-ai.php`
  `php -l includes/class-virastai-acr-*.php`
