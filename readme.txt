=== Virastai Auto Content Rewrite With AI ===
Contributors: micromax2
Tags: content rewriter, woocommerce, seo, automation, blogger
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connects to AI to automatically rewrite, translate, or optimize your Wordpress posts, pages and all post types in the background or manually.

== Description ==

Virastai Auto Content Rewrite With AI is a powerful automation tool designed to refresh your WordPress site's content using state-of-the-art Large Language Models. By connecting to the OpenRouter API, this plugin allows you to leverage models to bulk-rewrite titles and body content for your WooCommerce products, blog posts, or pages.

Everything runs silently in the background using WordPress Cron, ensuring your server is never overloaded or you can manually.

Key Features:

- **Model-Powered Rewriting**: Connects to OpenRouter.ai to access top-tier LLMs.
- **Bulk Automation**: Processes content in small batches automatically in the background.
- **Smart Formatting**: Automatically converts generated Markdown (headers, bold text, italics) into clean HTML.
- **Image Preservation**: Smart logic detects existing images in your content and appends them to the rewritten version so you never lose media.
- **Granular Control**: Select exactly which Post Types (Products, Posts, Pages) and Statuses (Publish, Draft) to target.
- **Custom Prompts**: Define your own "Base Context" and specific instructions for Titles and Content to get exactly the tone you want.
- **Safety Filters**: Options to skip posts with empty content/titles to prevent errors.
- **Progress Tracking**: Visual progress bars in the admin dashboard with options to reset progress for Titles, Content, or both.

== External Services ==

This plugin relies on a connection to a third-party service, OpenRouter.ai, to function.

- **Service Name**: OpenRouter
- **Service Usage**: When the rewriting process runs, the plugin sends your post content (Title and Body text) to the OpenRouter API. The API processes this data using the selected model and returns rewritten text, which is then saved back to your website.
- **Data Handling**: Your content is transmitted securely via HTTPS.
- **Terms of Service**: By using this plugin, you agree to the OpenRouter Terms of Service: https://openrouter.ai/terms

== Getting Started ==

To use this plugin, you need an API Key and a Model ID from OpenRouter.

Get an API Key:

1. Go to OpenRouter.ai and sign up or log in.
2. Navigate to your API Keys page.
3. Click "Create Key", give it a name (e.g., "WordPress Plugin"), and copy the key (it starts with `sk-or-...`).
4. Paste this key into the OpenRouter API Key field in the plugin settings.

Choose a Model:

1. Visit the OpenRouter Models page.
2. Browse or search for a model you want to use (e.g., Llama, Gemma).
3. Click on the model card to view details.
4. Copy the Model ID string (e.g., `google/gemma-2-9b-it:free` or `meta-llama/llama-3-8b-instruct`).
5. Paste this ID into the Model ID field in the plugin settings.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/virastai-auto-content-rewrite-with-ai` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Virastai Rewrite.
4. Enter your OpenRouter API Key (required).
5. Configure your desired Post Types and Prompts.
6. Enable "Automation" to start the background process.

== Frequently Asked Questions ==

= Do I need an API Key? =

Yes, you need a valid API key from OpenRouter.ai.

= Which model does this use? =

By default, it uses a free google model. You can change this to any model supported by OpenRouter in the plugin settings.

= Will this overwrite my existing content? =

Yes. The plugin is designed to rewrite content. It replaces the post_title and post_content of the targeted posts. We strongly recommend testing on a staging site first or having a backup.

= Does it handle images? =

Yes! If you enable "Preserve Images" in the settings, the plugin extracts all <img> tags from your original content and appends them to the bottom of the new generated text.

= Can I customize the instructions? =

Absolutely. You can set a "Base Context" (sent with every request) and specific instructions for both Titles and Body content. Placeholders like `{post_title}` and `{post_content}` are available.

== Changelog ==

= 1.5.0 =
* Rebranded the plugin name to Virastai Auto Content Rewrite With AI.
* Updated PHP classes, constants, variables, hooks, settings options, and database metadata prefixing to use the new brand.
* Enhanced database indexing efficiency by querying cron queues by ID instead of random order.
* Resolved CSRF vulnerabilities in setting reset and progress clear operations.
* Improved Markdown formatting replacement expressions to protect non-markdown characters.
* Implemented tabbed settings page navigation.
* Masked OpenRouter API Key and added toggling visibility option.
* Integrated pulsing skeleton loading states for ajax draft regeneration.

= 1.3.0 =
* Added Sidebar Statistics logic in a separate file.
* Improved stats to show separate counts for Title vs Content.
* Enhanced API URL handling to prevent errors.

= 1.2.0 =
* Combined API requests: Now sends a single request for both Title and Content to save tokens.
* Added "Skip Incomplete" logic to prevent processing empty posts.
* Added specific Reset actions for Titles and Content.

= 1.0.0 =
* Initial release.
