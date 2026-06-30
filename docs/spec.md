# Specification: Resilient Background Processing and Design/Delight Overhaul

## 1. Overview
This specification details the robustness, error prevention, performance enhancement, and high-fidelity user interface overhaul for the Virastai Auto Content Rewrite With AI WordPress plugin. It combines background resilience with a premium, modern design language and micro-interaction delight factors.

## 2. User Scenarios
- **Scenario A (Missing configuration)**: A site administrator activates the plugin but does not supply an API key. Background cron jobs should exit silently and cleanly. The admin dashboard displays a warm, encouraging setup prompt rather than plain text.
- **Scenario B (API Downtime or Key Expiry)**: The system prevents infinite API retries on the same posts. Error states are softened with warm, clear guidance.
- **Scenario C (Tab & Form Interaction)**: An administrator configures the plugin. The tabbed settings panel switches with fluid fade-in animations, text inputs glow on focus, toggle switches feel springy, and allowed roles/post types are selected via sleek grid cards that lift on hover and pulse on state changes.
- **Scenario D (Sandbox Review & Shimmering Loaders)**: When editing a post, clicking "Generate All" runs a background request. Shimmering gradient skeleton loaders occupy the preview slots, and fields animate into view when ready. Applying generations triggers a clean reload transition, and copy actions show inline green checkmarks.
- **Scenario E (System Log Examination)**: The site admin views log details. A glassmorphic modal animates open with backdrop blur and elastic scale dynamics. The JSON payloads inside are color-coded and copyable with an animated confirmation.

## 3. Requirements

### 3.1 Verification of API Config
- The cron runner must verify that the OpenRouter API Key is non-empty before initiating any query or loop.

### 3.2 Resilience and Fault Tolerance (Retry Limiter)
- Tracks failed API/decoding attempts on a per-post basis using a dedicated metadata field.
- Limits retries to 3 attempts. Upon reaching 3 failed attempts, the post's status is marked as `'failed'` to exclude it from background cron runs.

### 3.3 Safe Handling of Skipped Empty Posts
- If a post is skipped because it is empty, it is marked as `'skipped'`.
- Saving/updating post content automatically resets the `'skipped'` or `'failed'` flags.

### 3.4 Runtime Timeout Safeguard
- Monitor execution time during cron processing and break the batch loop 5 seconds before PHP `max_execution_time`.

### 3.5 High-Fidelity Design and Delight System
- **Signature UI Elements**: Re-design the old-school WordPress tab navigation into a modern, pill-shaped control panel using premium slate/indigo colors. Implement a sidebar widget with gradient progress meters and hover-responsive reset trash icons.
- **Spring Toggles & Cards**: Switch sliders should bounce slightly on state transition. Checkbox grid cards should lift on hover (`translateY(-2px)`) with a soft drop shadow, displaying an active state with an indigo outline and subtle scale pulse.
- **Shimmering Skeleton Loader**: Replace the basic pulse loader with a high-performance linear gradient sweep that shimmers across placeholders.
- **Glassmorphic Logs Modal**: Modal panels must slide/scale open using a custom cubic-bezier spring curve (`cubic-bezier(0.34, 1.56, 0.64, 1)`) with backdrop blur (`backdrop-filter: blur(8px)`).
- **Copy Confirms**: Copy-to-clipboard buttons in the metabox and log modals must toggle to a green checkmark icon with a springy transition, then revert after 2 seconds.

## 4. Success Criteria
- Zero PHP errors, warnings, or notices.
- A cohesive, modern, and premium design style that aligns with the latest 2026 guidelines (no plain system red/blue/green defaults, custom curated HSL tones, smooth transition curves).
- Delightful micro-interactions (hover, active, copy, tabs, toggle) functioning smoothly without impacting page rendering speed or causing visual clutter.
- Verification checks (e.g. `php -l`) pass with no issues.

