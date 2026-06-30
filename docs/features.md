# Project Features

## Automation and Content Updates
- Automatically rewrites WooCommerce product data, general post types, and excerpts.
- Background execution using WordPress Cron to process items in batch.
- Randomized batch query sorting to prevent background processes from sticking on empty posts.
- Aborts background executions cleanly and logs nothing if the OpenRouter API Key configuration is empty.
- Limits background processing batch runtimes to prevent script timeouts and HTTP 500 crashes on slow connections.
- Implements skipped and failed post metadata flags to prevent infinite background request loops on empty posts or persistently failing API calls.
- Monitors post updates via `save_post` hooks to automatically reset skipped and failed flags when content is updated.

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
- Features an "Apply Generations" action button at the bottom of the generated content fields (visible when any sandbox content is generated, persisting after page reload) to overwrite the main post title, content, and excerpt in the database and reload the page with the newly applied fields.
- Provides individual clipboard copy buttons next to each sandbox field (Title, Content, Excerpt) with inline success indicators, hiding the "Clear Generated" button dynamically when nothing is generated.

## Dashboard Statistics
- Displays total counts, completed titles, completed content, completed excerpts, percentage bars, and sandbox drafts statistics (titles, content, and excerpts pending review).
- Features inline trash icon buttons next to the processed progress counts and sandbox drafts counts to reset or clear specific segments directly from the sidebar.
- Supports bulk reset action (Reset ALL Progress) inside the "Automation" configuration tab.
- Clicking Clear Generated inside the post metabox deletes sandbox post meta and resets title/content completion progress flags, while preserving excerpt completion statistics.

## Security Protection
- Validates CSRF tokens (nonces) across settings pages and bulk operations.
- Verifies post-editing capabilities before responding to backend AJAX requests.
- Restricts visibility, access, asset loading, and generation AJAX actions for the Sandbox metabox based on configured Post Types, Allowed User Roles, and Post Statuses options.

## User Interface Refinements
- Implements a modern tabbed layout for settings sections (General, Automation, Content, Title, Help).
- Includes a dedicated Help tab providing a guide on OpenRouter API Key setup, model ID selection, background cron automation details, and sandbox review mode workflow.
- Masked API Key text fields with custom show/hide toggles.
- Dynamic fetching and caching (1 hour) of all OpenRouter model IDs, rendered as a select option.
- Added interactive Reset to Default buttons for Base Context, Content Instruction, and Title Instruction settings fields with custom rotate-on-hover micro-animations.
- Persistent tab selections matching URL hashes and referrer query parameters, so reloading the page or clicking "Save Settings" restores the previously open tab automatically.
- Renders Allowed User Roles, Post Types, Post Statuses, and Enable Sandbox Mode settings under the "General" configuration tab.
- Features a perfectly vertically middle-aligned eye visibility toggle button for the API key input.

## Exclusive Database Logging
- Writes all system events, errors, API requests, and API responses directly to a dedicated database table (wp_virastai_acr_logs).
- Optimizes logger performance by running table check/creation logic on plugin activation instead of every request.
- Supports automatic token masking for security when logging authorization headers.
- Displays logs in a paginated, responsive HTML table structure with dynamic database queries limit of 20 logs per page to minimize server load.
- Displays details of long log messages in a responsive, glassmorphic modal overlay instead of inline toggles.
- Extracts, prettifies, and color-code syntax highlights JSON block components inside the log details modal (identifying keys, strings, numbers, booleans, and null values).
- Features a dedicated clipboard copy button inside the modal to easily copy a specific log's content, in addition to the page-wide clipboard copy, AJAX refresh, and secure clear actions.
- Includes a retention cleanup daemon that purges records older than X days periodically.

## Data Lifecycle and Uninstallation
- Adds a danger-styled "Delete Data on Uninstall" option under the General configuration tab to permanently delete all logs, registered options, cache transients, and sandbox meta values upon deleting the plugin.
- Integrates a conditional data cleanup hook in uninstall.php that preserves data by default, executing cleanup queries only if the danger settings checkbox has been explicitly checked.
