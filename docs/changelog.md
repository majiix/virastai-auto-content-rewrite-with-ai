step 1:
1- Initialized project documentation structure.
2- Designed the refactoring and renaming strategy.

step 2:
1- Rebranded and renamed all files, classes, constants, variables, hooks, settings options, and database metadata keys.
2- Implemented secure CSRF nonce validations on settings forms and progress reset requests.
3- Added post-specific capability checks to Ajax regeneration routines.
4- Optimised automation queries by ordering background processing runs by ID instead of random sorting.
5- Implemented transient caching for dashboard statistics counts to improve settings page load speed.
6- Fixed Markdown formatting replacement expressions to avoid destroying raw symbol indicators.
7- Created option and post meta migration helpers for a seamless upgrade.

step 3:
1- Removed on-the-fly option and post metadata migration code.
2- Removed transient caching for statistics, querying counts directly from the database on page load.
3- Masked the OpenRouter API Key input field as a password field with a visibility toggle button.
4- Implemented a network call to fetch and cache the model list from OpenRouter for 1 hour, displaying them in a select dropdown.
5- Implemented a tabbed layout navigation for settings sections (API, Automation, Content, Title).
6- Added pulsing skeleton loading indicators during AJAX draft regeneration.

step 4:
1- Added a Reset to Default action button with rotate-on-hover micro-animation for settings textareas (Base Context, Content Instruction, and Title Instruction).
2- Updated the textarea rendering field callback to support automatically falling back to defined default prompts if the option is empty.
3- Added jQuery click event handler to update target textarea inputs to their defined default templates.

step 5:
1- Replaced file/debug logging with exclusive database table logging (wp_virastai_acr_logs).
2- Implemented tab state persistence in browser URL hash (opens the active tab automatically on page reload).
3- Created retention control settings field to delete old database logs via a daily cron event.
4- Added a secure Clear Logs action button (using CSRF nonce checking) and a clipboard copy action button with success animation.
5- Integrated automated request and response logging for LLM generations and model retrieval API calls (with bearer token masking).

step 6:
1- Renamed product title placeholder tag to post title placeholder tag and updated default Base Context prompt.
2- Set cron background query sorting to random to prevent execution bottlenecks.
3- Redesigned sidebar action buttons in settings panel using custom styles and Dashicons.
4- Added support for excerpt and WooCommerce short description rewrites inside the settings panel.
5- Restricted AJAX draft regeneration to rewrite only the clicked field instead of all fields.
6- Configured the clear action in metabox to delete all sandbox content and progress completion flags to reset statistics.

step 7:
1- Fixed settings saving functionality by moving nested Clear Logs form outside the main settings form.
2- Updated click handlers in admin JS to trigger the relocated hidden form.
3- Refined statistics row label in settings sidebar to Processed Excerpts / Descriptions.

step 8:
1- Implemented dynamic logs refresh button using custom AJAX handler, localized variables, and spin animations.
2- Injected strict prompt directives to enforce direct output and remove conversational intros or thinking blocks from API responses.

step 9:
1- Replaced the raw text system logs console with a paginated, responsive HTML table.
2- Implemented toggleable detailed pre-blocks for viewing long log messages directly in the table.
3- Refactored the UI/UX design of all plugin buttons to align with 2026 design standards, using custom border curves, gradients, glows, hover translations, and active click state transitions.
4- Added a primary Violet-to-Rose gradient "Regenerate All" button in the post edit metabox (rendering when multiple fields are enabled) to refresh all draft sections in a single AJAX request.

step 10:
1- Replaced inline accordion-style log toggles with a responsive modal popup display.
2- Implemented client-side JSON block extraction, beautification, and color-coded syntax highlighting inside the log details modal.
3- Added a secure clipboard copy button within the modal to easily copy the log message.
4- Implemented modal close handlers for backdrop clicks, close buttons, and Escape key presses.

step 11:
1- Added checks in metabox.js to block draft content regeneration if the post has unsaved modifications or is in auto-draft status.
2- Localized the warning alert prompt via PHP wp_localize_script so that the warning message is translatable.

step 12:
1- Added an "Apply Generations" action button at the bottom of the sandbox review fields panel.
2- Implemented a localized confirmation modal warning users about overwriting main post fields.
3- Created a backend AJAX handler to update main post fields (title, content, and/or excerpt) and purge the sandbox meta values upon success.
4- Programmed a frontend handler to force-reload the page when generations are successfully applied.

step 13:
1- Hidden the "Apply Generations" button container initially by default, rendering it only after a successful process has completed.
2- Modified clearing the sandbox content so it preserves the excerpt completion markers in statistics (`_virastai_acr_excerpt_generated` meta key).
3- Relocated the progress reset buttons (Reset ALL Progress, Reset Titles, Reset Content, Reset Excerpts) from the sidebar panel to the "Automation & Targeting" configuration tab.
4- Implemented hidden proxy submit forms for these relocated buttons to avoid HTML form nesting errors in the admin panel.

step 14:
1- Implemented persistent settings tabs across page refreshes and settings saves.
2- Programmed dynamic updating of the `_wp_http_referer` hidden input value when switching tabs, so that WordPress redirects users back to the same tab after clicking "Save Settings".
3- Added checks for the `tab` query parameter in the URL on page load to set the active tab state correctly.

step 15:
1- Rebranded user-facing post editor button labels from "Regenerate" to "Generate" in the sandbox review metabox.
2- Updated localized warning prompts to refer to "generating" rather than "regenerating".

step 16:
1- Fixed visual centering of the API Key eye visibility toggle button.
2- Added sandbox statistics (sandbox titles, content, and excerpts pending review) to the admin sidebar statistics card.

step 17:
1- Rebranded the "Skip Incomplete Titles" and "Skip Incomplete Excerpts" setting labels to "Do not process if current content is empty".
2- Updated the skip logic in `class-virastai-acr-rewriter.php` to evaluate the post's content field (`current_content`) for both settings instead of inspecting the title or excerpt fields.

step 18:
1- Cleaned up database and option bloat in uninstall.php by adding missing settings keys (log retention period, excerpt options) and post metadata keys.
2- Added deletion of custom logs database table on plugin uninstall to prevent database clutter.
3- Removed minor formatting inconsistencies and redundant comments/spacing in admin class files.
4- Resolved PHPCS and PluginCheck errors/warnings regarding output escaping, direct database queries, and date functions.

step 19:
1- Wrapped all table name assignments in direct database queries with esc_sql() to fix PluginCheck warnings.
2- Standardized readme.txt to adhere to WordPress.org repository standards.

step 20:
1- Refactored phpcs ignore comments by removing conflicting inline comments that were overriding block ignores.

step 21:
1- Replaced line-level phpcs ignores with block-level disable/enable flags to correctly suppress multiline statement warnings in logger queries.

step 22:
1- Added individual clipboard copy buttons for each generated Sandbox field (Title, Content, Excerpt) in the post edit metabox.
2- Added Settings configuration to restrict access to the Sandbox metabox by user roles.

step 23:
1- Updated Sandbox metabox buttons logic to show the Apply Generations button when any content is generated, persisting across page reloads.
2- Modified Clear Generated button in metabox to hide dynamically if no sandbox content is present.
3- Renamed API Configuration tab and section to General.
4- Renamed Automation & Targeting tab and section to Automation.
5- Relocated Allowed User Roles, Post Types, Post Statuses, and Enable Sandbox Mode options to the General tab.
6- Aligned the API key field visibility toggle button vertically middle in the settings interface.

step 24:
1- Replaced static sandbox statistics count values in the sidebar with inline forms and submit buttons featuring trash icons.
2- Removed the duplicate "Actions" card and its buttons from the admin sidebar page.

step 25:
1- Converted static processed statistics count values (Titles, Content, Excerpts) in the sidebar to inline submit buttons featuring trash icons.
2- Deleted the individual "Reset Titles", "Reset Content", and "Reset Excerpts" buttons from the Automation settings tab.
3- Removed the duplicate hidden proxy forms and script click listeners corresponding to these deleted buttons.

step 26:
1- Relocated the Post Statuses setting from the General tab back to the Automation tab.

step 27:
1- Restricted Sandbox metabox registration, asset loading, and generation AJAX endpoint execution based on configured Post Types, Allowed User Roles, and Post Statuses options.

step 28:
1- Relocated the Post Statuses setting from the Automation tab to the General tab.

step 29:
1- Added a "Delete Data on Uninstall" settings option under the General configuration tab.
2- Styled the delete option in a red danger checkbox format with warning details.
3- Added conditional checks in the uninstall hook to skip options, logs table, cache, and metadata deletion unless the option is checked.

step 30:
1- Prefixed the global `$delete_data` variable in uninstall.php to `$virastai_acr_delete_data` to conform with global variable prefix standards.
2- Added translators comment and output escaping using `wp_kses_post` on the OpenRouter signup text link in the help tab.

step 31:
1- Removed unused utility classes from assets/css/admin.css to clear stylesheet bloat.
2- Refactored duplicated title cleaning and tags stripping logic in class-virastai-acr-rewriter.php into a private helper function.
3- Enhanced combined prompt generation by dynamically assigning sequential numbers to task instructions.
4- Fixed outdated placeholder reference in readme.txt FAQ to use post_title.
