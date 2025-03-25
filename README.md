# Page Duplicator with ACF & Yoast

WordPress plugin for automating page duplication with ACF fields, Elementor content, and Yoast SEO metadata updates.

## Description

This plugin allows you to easily duplicate WordPress pages while automatically updating location-specific content, including:

- Advanced Custom Fields (ACF) data
- Elementor content
- Yoast SEO metadata
- Page titles and permalinks
- Featured images and media

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Advanced Custom Fields (Free or Pro)
- Elementor (Free or Pro) - optional but recommended
- Yoast SEO - optional but recommended

## Installation

### From ZIP File

1. Download the latest release from the [releases page](https://github.com/yourusername/page-duplicator/releases)
2. In your WordPress admin, go to Plugins > Add New > Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

### Manual Installation

1. Clone this repository
2. Run `npm install` to install dependencies
3. Run `npm run build` to generate the plugin ZIP file
4. Install the ZIP file in WordPress

## Usage

1. Navigate to the Page Duplicator menu in your WordPress admin
2. Enter the template page URL
3. Add your list of locations (one per line)
4. Enter the search key (text to be replaced)
5. Choose post status (Published/Draft)
6. Select slug handling option
7. Choose error handling behavior
8. Click 'Duplicate Now'

## Development

### Setup

```bash
# Install dependencies
npm install

# Build development version
npm run dev

# Build production version
npm run build
```

### File Structure

- `page-duplicator.php` - Main plugin file
- `includes/` - PHP classes for plugin functionality
- `assets/` - JS, CSS, and other assets
- `templates/` - Admin page templates
- `acf-json/` - ACF field group definitions

## License

This project is licensed under the GPL v2 or later.

## Changelog

See [README.txt](README.txt) for the full changelog. 