=== Page Duplicator with ACF & Yoast ===
Contributors: Braudy
Tags: page duplication, elementor, acf, yoast seo
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
License: GPLv2 or later

Automates page duplication with ACF fields, Elementor content, and Yoast SEO metadata updates.

== Features ==

- Duplicate pages with ACF fields automatically updated
- Update Elementor content
- Update Yoast SEO metadata
- Batch duplication from location list
- Choose post status (Published/Draft)
- Slug handling options (skip/update/increment)
- Featured images and taxonomies duplication

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/page-duplicator`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Page Duplicator' in the admin menu

== Usage ==

1. Enter the template page URL
2. Add your list of locations (one per line)
3. Enter the search key (text to be replaced)
4. Choose post status and slug handling options
5. Click 'Duplicate Now'

== Requirements ==

- WordPress 5.0+
- PHP 7.2+
- Advanced Custom Fields (required)
- Elementor (optional)
- Yoast SEO (optional)

== Changelog ==

= 1.3.1 =
* Current stable version

= 1.1.1 =
* Fixed featured image not being set on all duplicated pages
* Improved performance by reusing media attachments

= 1.1.0 =
* Added permalink structure preservation
* Added featured image duplication
* Added taxonomy duplication
* Fixed slug handling options

= 1.0.0 =
* Initial release 