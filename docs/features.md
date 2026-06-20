# Project Features

## Automation and Content Updates
- Automatically rewrites WooCommerce product data and general post types.
- Background execution using WordPress Cron to process items in batch.
- Deterministic batch sorting (orders by ID ascending) for reliable database lookup performance.

## Text Generation and Formatting
- Sends custom templates (Base Context, Title Instruction, Content Instruction) to OpenRouter.
- Utilizes API JSON mode configuration to guarantee structured response packages.
- Parses markdown-style headers, bold text, and italic tags to produce clean HTML.
- Extracts and preserves images from original content, appending them to the generated output.

## Sandbox Review Mode
- Stores updates in dedicated preview metadata fields instead of replacing live columns directly.
- Renders an interactive review box on post edit pages.
- Allows editing and manual regeneration of draft fields.
- Features pulsing skeleton loader elements while generating preview drafts.

## Dashboard Statistics
- Displays total counts, completed titles, completed content, and percentage bars.
- Supports bulk reset actions for titles, content, sandbox revisions, and general options.

## Security Protection
- Validates CSRF tokens (nonces) across settings pages and bulk operations.
- Verifies post-editing capabilities before responding to backend AJAX requests.

## User Interface Refinements
- Implements a modern tabbed layout for settings sections (API, Automation, Content, Title).
- Masked API Key text fields with custom show/hide toggles.
- Dynamic fetching and caching (1 hour) of all OpenRouter model IDs, rendered as a select option.
