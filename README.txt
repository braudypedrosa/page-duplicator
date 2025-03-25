=== Page Duplicator with ACF & Yoast ===
Contributors: [your name]
Tags: page duplication, elementor, acf, yoast seo
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
License: GPLv2 or later

Automates page duplication with ACF fields, Elementor content, and Yoast SEO metadata updates.

== Description ==

This plugin allows you to duplicate pages while automatically updating location-specific content, including:
- ACF fields
- Elementor content
- Yoast SEO metadata
- Page titles and permalinks

== Requirements ==

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Advanced Custom Fields (Free or Pro)
- Elementor (Free or Pro)
- Yoast SEO

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/page-duplicator`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Page Duplicator' in the admin menu

== Usage ==

1. Enter the template page URL
2. Add your list of locations (one per line)
3. Enter the search key (text to be replaced)
4. Choose post status (Published/Draft)
5. Select slug handling option
6. Choose error handling behavior
7. Click 'Duplicate Now'

== Changelog ==

= 1.1.1 =
* Fixed featured image not being set on all duplicated pages
* Improved performance by reusing media attachments instead of creating copies

= 1.1.0 =
* Added proper permalink structure preservation
* Added featured image duplication with metadata
* Added taxonomy (categories, tags) duplication
* Fixed slug handling options (skip/update/increment)
* Improved Elementor support

= 1.0.0 =
* Initial release

== Frequently Asked Questions ==

= What happens if a page already exists? =
You can choose to:
- Auto-increment the slug
- Skip the existing page
- Update the existing page

= Does it work with Elementor? =
Yes, the plugin fully supports Elementor content and settings. 