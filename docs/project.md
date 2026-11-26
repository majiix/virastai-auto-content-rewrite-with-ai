# Project Overview: Virastai Auto Content Rewrite With AI

## Overview
Virastai Auto Content Rewrite With AI (v1.7.1) is an enterprise-grade WordPress plugin designed to automatically rewrite, translate, and optimize posts, pages, and custom post types using Large Language Models across 26 supported AI providers (including OpenRouter, OpenAI Direct, Google Gemini Direct, Cohere, Groq, Cloudflare Workers AI, and Custom OpenAI-Compatible / Self-Hosted Ollama).

## Tech Stack & Architecture
- **Language**: PHP 8.0+
- **Platform**: WordPress 6.0+
- **Frontend**: Vanilla JavaScript (ES6+), jQuery, CSS3
- **External APIs**: 26 configurable AI provider endpoints via `Virastai_Acr_Providers` (OpenAI-compatible `chat/completions`, Google Gemini `generateContent`, Cohere v2 `chat`, Cloudflare Workers AI)
- **Background Execution**: WordPress Cron (`wp_schedule_single_event`, `wp_schedule_event`), AJAX polling
- **Architecture**: Modular object-oriented PHP architecture:
  - `virastai-auto-content-rewrite-with-ai.php`: Plugin bootstrap, lifecycle hooks, component instantiation.
  - `includes/class-virastai-acr-providers.php`: Multi-provider registry, credential/model resolution, model discovery, and protocol request/response adapters.
  - `includes/class-virastai-acr-rewriter.php`: LLM API communication and rewriting engine.
  - `includes/class-virastai-acr-admin.php`: Settings page, tab rendering, admin assets, logs viewer.
  - `includes/class-virastai-acr-cron.php`: General background scheduled rewriter engine.
  - `includes/class-virastai-acr-metabox.php`: Post-level sandbox preview, generation, and manual review metabox.
  - `includes/class-virastai-acr-logger.php`: Custom MySQL logging table and log management.

## Current Features
1. **General Settings**:
   - Multi-provider AI selector supporting 26 providers with per-provider API key and Model ID persistence (plus backward-compatible OpenRouter fallback).
   - Hybrid Model ID selector: dynamic `/models` fetching with curated defaults and custom Model ID text input override.
   - Conditional provider fields for Cloudflare Account ID and Custom/Ollama Base URL (HTTPS or localhost Ollama with optional API key).
2. **Standard Automation**:
   - Background WP-Cron scheduler running at configurable intervals (1m, 5m, 15m, 30m, hourly, daily).
   - Post type and post status filtering.
   - Batch size controls to prevent server timeouts.
3. **Content & Title Rewrite Options**:
   - Independent toggles for Content, Title, and Excerpt rewriting.
   - Custom prompt instructions and placeholder support (`{post_title}`, `{post_content}`).
   - HTML preservation, image stripping/preservation controls.
4. **Sandbox Review Mode**:
   - Non-destructive drafts stored in post meta for manual review before overwriting live post content.
5. **System Logging & Observability**:
   - Dedicated database logging table with pagination, copy-to-clipboard, and modal inspection.
   - High-performance log modal viewer with structured section formatting and non-blocking rendering.
   - Failures and warnings (e.g. empty content, disabled options, API errors, timeouts) are always recorded to eliminate silent failures, even when verbose logging is turned off.
6. **Security & Background Capability Handling**:
   - Automatic user context switching during WP-Cron and background automation: switches temporarily to the post author or an administrator so core hooks and security firewalls (like NinjaFirewall) recognize authorized capabilities, then restores initial context.

## Verification Commands
Run unit tests from the plugin root directory:
```bash
php tests/test-providers.php
php tests/test-rewriter-force.php
php tests/test-rewriter-capability.php
php tests/test-rewriter-atomic-meta.php
```

Lint PHP files for syntax correctness:
```bash
php -l virastai-auto-content-rewrite-with-ai.php
php -l includes/class-virastai-acr-rewriter.php
php -l includes/class-virastai-acr-admin.php
```
