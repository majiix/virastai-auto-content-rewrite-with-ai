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
