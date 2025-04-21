=== OpenRouted ===
Contributors: openrouted, pellmellism
Donate link: https://openrouted.com/donate/
Tags: ai, accessibility, seo, alt-text, image-optimization
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically generate SEO-friendly alt tags for images using AI vision models to improve accessibility and search rankings.

== Description ==

OpenRouted is a powerful WordPress plugin that leverages OpenRouter's AI vision models to automatically generate descriptive, SEO-friendly alt tags for your images. With both free and paid options available, it helps improve your website's accessibility and search engine optimization with minimal effort.

### Why You Need Alt Tags

Alt tags (alternative text) are essential for:

* **Accessibility**: Screen readers use alt text to describe images to visually impaired users
* **SEO Optimization**: Search engines use alt text to understand image content
* **Compliance**: Many accessibility standards require alt text for images

### Key Features

* **Automated Alt Tag Generation**: Uses AI vision models to analyze your images and create descriptive alt tags
* **Free Usage Option**: Utilizes OpenRouter's free daily quota (no credit card required)
* **Media Library Integration**: Generate alt tags directly from your media library
* **Bulk Processing**: Scan and generate alt tags for multiple images at once
* **Daily Checks**: Automatically find and process new images missing alt tags
* **Flexible Operation Modes**:
  * Manual: Review alt tags before applying them
  * Automatic: Apply generated alt tags immediately
* **Custom Instructions**: Personalize how the AI generates alt tags
* **Simple Dashboard**: Easy-to-use interface for reviewing and applying alt tags

### How It Works

1. The plugin runs a daily cron job to check for images without alt tags
2. It uses OpenRouter's vision models to analyze each image
3. Generated alt tags are stored in your WordPress database
4. You can review and apply alt tags manually or set them to apply automatically
5. You can also generate alt tags on-demand from the Media Library or dashboard

### Requirements

* WordPress 5.0 or higher
* PHP 7.2 or higher
* OpenRouter API key (free registration at [OpenRouter.ai](https://openrouter.ai/))

== Installation ==

1. Upload the `openrouted` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Alt Tags AI > Settings
4. Enter your OpenRouter API key (register at [OpenRouter.ai](https://openrouter.ai/) if you don't have one)
5. Configure your preferred operation mode and custom instructions
6. The plugin will automatically start checking for images missing alt tags daily

== Frequently Asked Questions ==

= How much does this cost? =

The plugin itself is free. It uses OpenRouter's free daily quota for AI vision calls, so there's no cost for the basic usage. If you exceed the free quota, you will need to wait until it refreshes or upgrade your OpenRouter account.

= Is my data secure? =

The plugin sends only your images to OpenRouter's API for analysis. Your OpenRouter API key is stored securely in your WordPress database. No user data is collected or stored by the plugin developers.

= How do I get an OpenRouter API key? =

Visit [OpenRouter.ai](https://openrouter.ai/), create an account, and generate an API key.

= Can I customize how the AI generates alt tags? =

Yes! The plugin includes a custom instructions field where you can provide specific guidance for the AI. For example, you can specify the tone for alt tags, suggest they include specific keywords, or focus on certain aspects of the images.

= Does this work with existing images? =

Yes! The plugin can scan your entire media library for images without alt tags and generate them automatically. You can also manually trigger alt tag generation for specific images from the media library.

= How can I manually generate alt tags? =

You can generate alt tags in two ways:
1. From the Media Library: Edit an image and click the "Generate with AI" button
2. From the OpenRouted dashboard: Use the bulk generator tool to process multiple images at once

= What AI models are used? =

The plugin uses OpenRouter's vision models, which may include models from various providers like Claude, GPT-4 Vision, or other vision-capable large language models. The specific model used depends on availability through OpenRouter.

= Can I regenerate alt tags if I don't like the ones created? =

Yes! If you're not satisfied with a generated alt tag, you can reject it and generate a new one. You can also edit alt tags manually after they've been applied.

= Will this slow down my website? =

No. The plugin processes images in the background and doesn't affect front-end performance at all. The generated alt tags are stored in your WordPress database, so there's no additional load time when visitors view your site.

= Is there a limit to how many images I can process? =

The limit is based on OpenRouter's daily quota for free accounts. You can process as many images as your OpenRouter plan allows. The plugin also includes options to throttle requests to stay within rate limits.

== Screenshots ==

1. Dashboard overview showing alt tag generation statistics
2. Bulk generator tool for processing multiple images
3. Media library integration with AI generation button
4. Settings page for configuring the plugin
5. Review and approve generated alt tags

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of OpenRouted. Enjoy automatic alt tag generation with AI!

== Privacy Policy ==

OpenRouted plugin sends your images to OpenRouter's API for analysis to generate alt tags. Your OpenRouter API key is stored securely in your WordPress database. No user data is collected or stored by the plugin developers.

For more information on OpenRouter's privacy policy, please visit: [OpenRouter Privacy Policy](https://openrouter.ai/privacy).

== Additional Notes ==

= Support =

For support, please visit [our GitHub repository](https://github.com/Pellmellism/openrouted/issues) to report issues or ask questions.

= Rate Us =

If you find this plugin useful, please consider leaving a positive review. It helps others discover the plugin and encourages continued development.

= Credits =

This plugin was developed by [OpenRouted](https://openrouted.com) and uses OpenRouter's AI vision models.