# Project Features

## Automation and Content Updates
- Automatically rewrites WooCommerce product data, general post types, and excerpts.
- Background execution using WordPress Cron to process items in batch.
- Randomized batch query sorting to prevent background processes from sticking on empty posts.

## Text Generation and Formatting
- Sends custom templates (Base Context, Title Instruction, Content Instruction, Excerpt Instruction) to OpenRouter.
- Utilizes API JSON mode configuration to guarantee structured response packages.
- Enforces strict instructions preventing conversational thinking and prefix intros in API responses.
- Parses markdown-style headers, bold text, and italic tags to produce clean HTML.
- Extracts and preserves images from original content, appending them to the generated output.

## Sandbox Review Mode
- Stores updates in dedicated preview metadata fields instead of replacing live columns directly.
- Renders an interactive review box on post edit pages.
- Allows editing and manual generation of draft fields.
- Features pulsing skeleton loader elements while generating preview drafts.
- Supports selective field generation, updating only the clicked field while keeping others visible.
- Features a primary Violet-to-Rose gradient "Generate All" button (when multiple target fields are enabled) to generate all draft sections in a single background request.
- Validates if the post is unsaved or in a new auto-draft state prior to triggering any field generation, prompting the user to save changes first to align API outputs with current edits.
- Features an "Apply Generations" action button at the bottom of the generated content fields (visible only after a successful process run) to overwrite the main post title, content, and excerpt in the database and reload the page with the newly applied fields.

## Dashboard Statistics
- Displays total counts, completed titles, completed content, completed excerpts, percentage bars, and sandbox drafts statistics (titles, content, and excerpts pending review).
- Supports bulk reset actions (Reset ALL Progress, Reset Titles, Reset Content, Reset Excerpts) relocated inside the "Automation & Targeting" configuration tab.
- Clicking Clear Generated inside the post metabox deletes sandbox post meta and resets title/content completion progress flags, while preserving excerpt completion statistics.

## Security Protection
- Validates CSRF tokens (nonces) across settings pages and bulk operations.
- Verifies post-editing capabilities before responding to backend AJAX requests.

## User Interface Refinements
- Implements a modern tabbed layout for settings sections (API, Automation, Content, Title).
- Masked API Key text fields with custom show/hide toggles.
- Dynamic fetching and caching (1 hour) of all OpenRouter model IDs, rendered as a select option.
- Added interactive Reset to Default buttons for Base Context, Content Instruction, and Title Instruction settings fields with custom rotate-on-hover micro-animations.
- Persistent tab selections matching URL hashes and referrer query parameters, so reloading the page or clicking "Save Settings" restores the previously open tab automatically.

## Exclusive Database Logging
- Writes all system events, errors, API requests, and API responses directly to a dedicated database table (wp_virastai_acr_logs).
- Supports automatic token masking for security when logging authorization headers.
- Displays logs in a paginated, responsive HTML table structure with dynamic database queries limit of 20 logs per page to minimize server load.
- Displays details of long log messages in a responsive, glassmorphic modal overlay instead of inline toggles.
- Extracts, prettifies, and color-code syntax highlights JSON block components inside the log details modal (identifying keys, strings, numbers, booleans, and null values).
- Features a dedicated clipboard copy button inside the modal to easily copy a specific log's content, in addition to the page-wide clipboard copy, AJAX refresh, and secure clear actions.
- Includes a retention cleanup daemon that purges records older than X days periodically.
