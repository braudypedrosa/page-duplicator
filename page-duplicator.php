<?php
// Enable error reporting for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

/**
 * Plugin Name: Page Duplicator with ACF & Yoast
 * Description: Automates page duplication with ACF fields and Yoast SEO metadata updates
 * Version: 1.2.5
 * Author: Braudy
 * Text Domain: page-duplicator
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PD_VERSION', '1.2.3');

// Load required files
require_once PD_PLUGIN_PATH . 'includes/class-page-duplicator-process.php';
require_once PD_PLUGIN_PATH . 'includes/class-page-duplicator-logger.php';

function page_duplicator_acf_json_save_point( $path ) {
    return PD_PLUGIN_PATH . '/acf-json';
}
add_filter( 'acf/settings/save_json', 'page_duplicator_acf_json_save_point' );

/**
 * Class PageDuplicator
 * Main plugin class to handle initialization and hooks
 */
class PageDuplicator {
    /**
     * Constructor to initialize the plugin
     */
    public function __construct() {
        // Check dependencies
        add_action('admin_init', array($this, 'check_dependencies'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register assets
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));
        
        // Add AJAX handlers
        add_action('wp_ajax_preview_duplication', array($this, 'handle_preview_ajax'));
        add_action('wp_ajax_duplicate_pages', array($this, 'handle_duplication_ajax'));
    }

    /**
     * Check if required plugins are active
     */
    public function check_dependencies() {
        if (!class_exists('ACF')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('Page Duplicator requires Advanced Custom Fields to be installed and activated.', 'page-duplicator'); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Page Duplicator', 'page-duplicator'),
            __('Page Duplicator', 'page-duplicator'),
            'manage_options',
            'page-duplicator',
            array($this, 'render_admin_page'),
            'dashicons-admin-page',
            30
        );

              // // Add CSS to hide the menu item
        add_action('admin_head', function() {
            echo '<style>
                #adminmenu .toplevel_page_page-duplicator {
                    display: none !important;
                }
            </style>';
        });
    }

    /**
     * Register and enqueue assets
     */
    public function register_assets($hook) {
        if ($hook !== 'toplevel_page_page-duplicator') {
            return;
        }

        wp_enqueue_style(
            'page-duplicator-styles',
            PD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PD_VERSION
        );

        wp_enqueue_script(
            'page-duplicator-script',
            PD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PD_VERSION,
            true
        );

        wp_localize_script('page-duplicator-script', 'pdSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('page-duplicator-nonce'),
            'i18n' => array(
                'location' => __('Location', 'page-duplicator'),
                'newTitle' => __('New Title', 'page-duplicator'),
                'newSlug' => __('New URL', 'page-duplicator'),
                'status' => __('Status', 'page-duplicator'),
                'ready' => __('Ready to duplicate', 'page-duplicator'),
                'confirmDuplication' => __('Are you sure you want to proceed with the duplication?', 'page-duplicator')
            )
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        include PD_PLUGIN_PATH . 'templates/admin-page.php';
    }

    /**
     * Handle preview AJAX request
     */
    public function handle_preview_ajax() {
        // Verify nonce
        if (!check_ajax_referer('page-duplicator-nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Invalid security token.', 'page-duplicator')));
        }

        // Validate user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'page-duplicator')));
        }

        $processor = new PageDuplicatorProcess();
        $result = $processor->preview_duplication($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle duplication AJAX request
     */
    public function handle_duplication_ajax() {
        // Debug logging
        error_log('Starting duplication process');
        
        // Verify nonce
        if (!check_ajax_referer('page-duplicator-nonce', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(array('message' => __('Invalid security token.', 'page-duplicator')));
        }

        // Validate user capabilities
        if (!current_user_can('manage_options')) {
            error_log('User capability check failed');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'page-duplicator')));
        }

        // Validate required fields
        $required_fields = array('template_url', 'locations', 'search_key');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                error_log("Missing required field: {$field}");
                wp_send_json_error(array(
                    'message' => sprintf(__('Missing required field: %s', 'page-duplicator'), $field)
                ));
            }
        }

        // Debug POST data
        error_log('POST data: ' . print_r($_POST, true));

        try {
            $processor = new PageDuplicatorProcess();
            $logger = new PageDuplicatorLogger();
            
            error_log('Starting duplicate_pages process');
            $result = $processor->duplicate_pages($_POST);
            
            if ($result['success']) {
                $logger->log('Pages duplicated successfully', 'info');
                error_log('Duplication successful');
                wp_send_json_success($result);
            } else {
                $logger->log($result['message'], 'error');
                error_log('Duplication failed: ' . $result['message']);
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'details' => isset($result['details']) ? $result['details'] : null
                ));
            }
        } catch (Exception $e) {
            error_log('Exception during duplication: ' . $e->getMessage());
            $logger->log($e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => __('Error during duplication:', 'page-duplicator'),
                'details' => $e->getMessage()
            ));
        }
    }
}

// Initialize the plugin
new PageDuplicator(); 

