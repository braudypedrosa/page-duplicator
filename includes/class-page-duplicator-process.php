<?php
/**
 * Class PageDuplicatorProcess
 * Handles the page duplication process
 */
class PageDuplicatorProcess {
    /**
     * Store any errors that occur during processing
     */
    private $errors = array();

    /**
     * Store created post IDs for potential rollback
     */
    private $created_posts = array();

    /**
     * Store created attachment IDs for potential rollback
     */
    private $created_attachments = array();

    /**
     * Duplicate pages based on provided data with rollback support
     *
     * @param array $data Form data containing template URL, locations, and settings
     * @return array Results of the duplication process
     */
    public function duplicate_pages($data) {
        // Debug logging
        error_log('Received data in duplicate_pages: ' . print_r($data, true));
        
        // Validate required data
        if (empty($data['template_url'])) {
            error_log('Missing template URL');
            return array(
                'success' => false,
                'message' => __('Template URL is required.', 'page-duplicator')
            );
        }
        
        if (empty($data['locations'])) {
            error_log('Missing locations data');
            return array(
                'success' => false,
                'message' => __('Locations data is required.', 'page-duplicator')
            );
        }
        
        if (empty($data['search_key'])) {
            error_log('Missing search key');
            return array(
                'success' => false,
                'message' => __('Search key is required.', 'page-duplicator')
            );
        }

        error_log('Locations raw data: ' . $data['locations']);
        
        // Split locations string into array - using pipe or newlines as separators
        $locations = preg_split('/\||\r\n|\r|\n/', $data['locations']);
        $locations = array_map('trim', $locations);
        $locations = array_filter($locations); // Remove empty entries
        error_log('Processed locations: ' . print_r($locations, true));

        if (empty($locations)) {
            return array(
                'success' => false,
                'message' => __('No valid locations provided.', 'page-duplicator')
            );
        }

        $this->created_posts = array();
        $results = array();
        $has_errors = false;
        
        try {
            // Get template page ID from URL
            $template_id = url_to_postid($data['template_url']);
            
            if (!$template_id) {
                return array(
                    'success' => false,
                    'message' => __('Template page not found.', 'page-duplicator')
                );
            }

            // Get template page data
            $template_post = get_post($template_id);
            
            // Ensure we have locations to process
            if (empty($locations)) {
                return array(
                    'success' => false,
                    'message' => __('No locations provided for duplication.', 'page-duplicator')
                );
            }
            
            foreach ($locations as $location) {
                $result = $this->duplicate_single_page($template_post, $location, $data);
                
                if (!$result['success']) {
                    $has_errors = true;
                }
                
                $results[] = $result;
                
                // If we're skipping on error and there was an error, stop processing
                if ($has_errors && !empty($data['continue_on_error'])) {
                    break;
                }
            }

            // If there were any errors, rollback all changes
            if ($has_errors) {
                $this->rollback_changes();
                return array(
                    'success' => false,
                    'message' => __('Duplication failed. All changes have been rolled back.', 'page-duplicator'),
                    'results' => $results
                );
            }

            return array(
                'success' => true,
                'results' => $results
            );
            
        } catch (Exception $e) {
            // If any unexpected error occurs, rollback changes
            $this->rollback_changes();
            throw $e;
        }
    }

    /**
     * Rollback all changes made during duplication
     */
    private function rollback_changes() {
        // Delete all created posts
        foreach ($this->created_posts as $post_id) {
            // Remove attachment associations without deleting the actual attachments
            $attachment_ids = get_post_meta($post_id, '_wp_attachment_ids', false);
            foreach ($attachment_ids as $attachment_id) {
                delete_post_meta($attachment_id, '_wp_additional_parents', $post_id);
            }
            
            // Delete post meta
            delete_post_meta($post_id, '_wp_attachment_ids');
            delete_post_meta($post_id, '_wp_attached_file');
            delete_post_meta($post_id, '_wp_attachment_metadata');
            
            // Delete the duplicated post
            wp_delete_post($post_id, true);
            
            // Clean up ACF fields if they exist
            if (function_exists('delete_field')) {
                $fields = get_fields($post_id);
                if ($fields) {
                    foreach ($fields as $key => $value) {
                        delete_field($key, $post_id);
                    }
                }
            }
        }
        
        // Delete any attachments that were created
        foreach ($this->created_attachments as $attachment_id) {
            // Get attachment file path
            $file = get_attached_file($attachment_id);
            
            // Delete the attachment post
            wp_delete_attachment($attachment_id, true);
            
            // Delete the actual file if it exists
            if ($file && file_exists($file)) {
                @unlink($file);
                
                // Also delete any resized versions
                $path = pathinfo($file);
                $pattern = $path['dirname'] . '/' . $path['filename'] . '-*.' . $path['extension'];
                array_map('unlink', glob($pattern));
            }
        }
        
        // Reset the arrays
        $this->created_posts = array();
        $this->created_attachments = array();
        
        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Modified duplicate_single_page to track created attachments
     */
    private function duplicate_single_page($template_post, $location, $data) {
        try {
            // Generate the new title and slug
            $new_title = $this->replace_location($template_post->post_title, $data['search_key'], $location);
            
            // Generate slug based on template's slug pattern
            $template_slug = $template_post->post_name;
            $new_slug = $this->replace_location($template_slug, sanitize_title($data['search_key']), sanitize_title($location));
            
            // Check for existing page
            $existing_page = get_page_by_path($new_slug);
            
            // Handle existing pages based on slug_handling setting
            if ($existing_page) {
                switch ($data['slug_handling']) {
                    case 'skip':
                        return array(
                            'location' => $location,
                            'success'  => false,
                            'message'  => __('Page already exists - skipped', 'page-duplicator')
                        );
                    
                    case 'update':
                        $new_post_id = $existing_page->ID;
                        
                        // Update existing post
                        $update_post = array(
                            'ID'           => $new_post_id,
                            'post_title'   => $new_title,
                            'post_content' => $template_post->post_content,
                            'post_status'  => $data['post_status']
                        );
                        
                        wp_update_post($update_post);
                        break;
                    
                    case 'increment':
                    default:
                        $new_slug = $this->get_unique_slug($new_slug);
                        break;
                }
            }

            // Create new post array
            $new_post = array(
                'post_title'   => $new_title,
                'post_content' => $template_post->post_content,
                'post_status'  => $data['post_status'],
                'post_type'    => $template_post->post_type,
                'post_author'  => get_current_user_id(),
                'post_name'    => $new_slug
            );

            // Only insert new post if we're not updating an existing one
            if (!isset($new_post_id)) {
                // Insert the post
                $new_post_id = wp_insert_post($new_post);

                if (is_wp_error($new_post_id)) {
                    throw new Exception($new_post_id->get_error_message());
                }

                // Add to created posts array for potential rollback
                $this->created_posts[] = $new_post_id;
            }

            // Handle Elementor data
            $this->duplicate_elementor_data($template_post->ID, $new_post_id, $data['search_key'], $location);

            // Copy featured image
            $this->duplicate_featured_image($template_post->ID, $new_post_id);

            // Duplicate any attached media files
            $this->duplicate_attachments($template_post->ID, $new_post_id);
            
            // Copy taxonomies (categories, tags, etc.)
            $this->duplicate_taxonomies($template_post->ID, $new_post_id);
            
            // Update ACF fields and all post meta
            $is_update = isset($existing_page) && $data['slug_handling'] === 'update';
            $this->update_acf_fields($template_post->ID, $new_post_id, $location, $data['search_key'], $is_update);
            
            // Update Yoast SEO metadata
            $this->update_yoast_metadata($template_post->ID, $new_post_id, $data['search_key'], $location);

            return array(
                'location' => $location,
                'success'  => true,
                'message'  => isset($existing_page) && $data['slug_handling'] === 'update' 
                    ? __('Existing page updated successfully', 'page-duplicator')
                    : __('Page created successfully', 'page-duplicator'),
                'url'      => get_permalink($new_post_id)
            );
        } catch (Exception $e) {
            return array(
                'location' => $location,
                'success'  => false,
                'message'  => $e->getMessage()
            );
        }
    }

    /**
     * Validate and preview the duplication process
     *
     * @param array $data Form data
     * @return array Preview results
     */
    public function preview_duplication($data) {
        $preview_results = array();
        $template_id = url_to_postid($data['template_url']);
        
        // Validate template URL
        if (!$template_id) {
            return array(
                'success' => false,
                'message' => __('Template page not found on this site.', 'page-duplicator')
            );
        }

        $template_post = get_post($template_id);
        $locations = json_decode($data['locations']);
        
        foreach ($locations as $location) {
            $new_title = $this->replace_location($template_post->post_title, $data['search_key'], $location);
            $new_slug = sanitize_title($new_title);
            
            $preview_data = array(
                'location' => $location,
                'new_title' => $new_title,
                'new_slug' => $new_slug,
                'full_url' => home_url("/$new_slug/"),
                'status' => 'ready'
            );
            
            // Check for existing pages
            $existing_page = get_page_by_path($new_slug);
            if ($existing_page) {
                switch ($data['slug_handling']) {
                    case 'increment':
                        $preview_data['status'] = 'will_increment';
                        $preview_data['note'] = __('Will create with incremented slug', 'page-duplicator');
                        break;
                    case 'skip':
                        $preview_data['status'] = 'will_skip';
                        $preview_data['note'] = __('Will skip - page exists', 'page-duplicator');
                        break;
                    case 'update':
                        $preview_data['status'] = 'will_update';
                        $preview_data['note'] = __('Will update existing page', 'page-duplicator');
                        break;
                }
            }
            
            $preview_results[] = $preview_data;
        }
        
        return array(
            'success' => true,
            'preview' => $preview_results
        );
    }

    /**
     * Get incremented slug if exists
     *
     * @param string $base_slug Original slug
     * @return string Unique slug
     */
    private function get_unique_slug($base_slug) {
        $slug = $base_slug;
        $counter = 1;
        
        while (get_page_by_path($slug)) {
            $counter++;
            $slug = $base_slug . '-' . $counter;
        }
        
        return $slug;
    }

    /**
     * Update ACF fields and all post meta for the duplicated page
     *
     * @param int $template_id Original page ID
     * @param int $new_post_id New page ID
     * @param string $location New location value
     * @param string $search_key Text to search for
     * @param bool $is_update Whether this is an update operation
     */
    private function update_acf_fields($template_id, $new_post_id, $location, $search_key, $is_update = false) {
        // Handle meta differently based on whether we're updating or creating
        if ($is_update) {
            // For updates, only delete meta that we're going to copy
            $template_meta = get_post_meta($template_id);
            foreach ($template_meta as $meta_key => $meta_values) {
                if (!in_array($meta_key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_trash_meta_time', '_wp_trash_meta_status'])) {
                    delete_post_meta($new_post_id, $meta_key);
                }
            }
        } else {
            // For new pages, delete all existing meta except Elementor data
            $existing_meta = get_post_meta($new_post_id);
            foreach ($existing_meta as $meta_key => $meta_values) {
                delete_post_meta($new_post_id, $meta_key);
            }
        }

        // Special handling for Elementor data
        $elementor_data = get_post_meta($template_id, '_elementor_data', true);
        if ($elementor_data) {
            // If it's JSON string, decode it first
            if (is_string($elementor_data)) {
                $elementor_data = json_decode($elementor_data, true);
            }
            
            // Replace location in the Elementor data
            $updated_elementor_data = $this->replace_location_recursive($elementor_data, $search_key, $location);
            
            // Update Elementor data
            update_post_meta($new_post_id, '_elementor_data', wp_slash(json_encode($updated_elementor_data)));
            
            // Copy other Elementor meta
            $elementor_meta_keys = [
                '_elementor_edit_mode',
                '_elementor_template_type',
                '_elementor_version',
                '_elementor_pro_version',
                '_elementor_css'
            ];
            
            foreach ($elementor_meta_keys as $elementor_key) {
                $elementor_value = get_post_meta($template_id, $elementor_key, true);
                if ($elementor_value) {
                    update_post_meta($new_post_id, $elementor_key, $elementor_value);
                }
            }
        }

        // Get ALL post meta from template
        $all_post_meta = get_post_meta($template_id);
        
        // List of meta keys to skip
        $skip_keys = array_merge([
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_trash_meta_time',
            '_wp_trash_meta_status',
            '_elementor_data',
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_version',
            '_elementor_pro_version',
            '_elementor_css'
        ]);

        foreach ($all_post_meta as $meta_key => $meta_values) {
            // Skip internal WordPress meta and Elementor meta
            if (in_array($meta_key, $skip_keys)) {
                continue;
            }

            // Get raw meta values
            $raw_values = get_post_meta($template_id, $meta_key, false);

            foreach ($raw_values as $raw_value) {
                $new_value = $raw_value;

                // Process strings and arrays
                if (is_string($raw_value)) {
                    $new_value = $this->replace_location($raw_value, $search_key, $location);
                } elseif (is_array($raw_value)) {
                    $new_value = $this->replace_location_recursive($raw_value, $search_key, $location);
                }

                // Special handling for location field
                if ($meta_key === 'location') {
                    $new_value = $location;
                }

                // Add the meta value
                add_post_meta($new_post_id, $meta_key, $new_value);
            }
        }

        // Handle ACF fields if they exist
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($template_id);
            if ($acf_fields) {
                foreach ($acf_fields as $key => $value) {
                    if ($key === 'location') {
                        update_field($key, $location, $new_post_id);
                    } else {
                        $updated_value = $this->replace_location_recursive($value, $search_key, $location);
                        update_field($key, $updated_value, $new_post_id);
                    }
                }
            }
        }

        // Copy Yoast SEO meta if it exists
        $this->copy_yoast_seo_meta($template_id, $new_post_id, $search_key, $location);
    }

    /**
     * Copy Yoast SEO meta data
     *
     * @param int $template_id Original page ID
     * @param int $new_post_id New page ID
     * @param string $search_key Text to search for
     * @param string $location New location value
     */
    private function copy_yoast_seo_meta($template_id, $new_post_id, $search_key, $location) {
        $yoast_meta_keys = array(
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_meta-robots-noindex',
            '_yoast_wpseo_meta-robots-nofollow',
            '_yoast_wpseo_meta-robots-adv',
            '_yoast_wpseo_canonical',
            '_yoast_wpseo_bctitle',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-title',
            '_yoast_wpseo_twitter-description',
            '_yoast_wpseo_twitter-image'
        );

        foreach ($yoast_meta_keys as $meta_key) {
            $meta_value = get_post_meta($template_id, $meta_key, true);
            if ($meta_value) {
                if (is_string($meta_value)) {
                    $meta_value = $this->replace_location($meta_value, $search_key, $location);
                }
                update_post_meta($new_post_id, $meta_key, $meta_value);
            }
        }
    }

    /**
     * Update Yoast SEO metadata for the duplicated page
     *
     * @param int $template_id Original page ID
     * @param int $new_post_id New page ID
     * @param string $search_key Text to search for
     * @param string $location New location value
     */
    private function update_yoast_metadata($template_id, $new_post_id, $search_key, $location) {
        // Yoast SEO meta keys to update
        $meta_keys = array(
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw'
        );

        foreach ($meta_keys as $meta_key) {
            $meta_value = get_post_meta($template_id, $meta_key, true);
            
            if ($meta_value) {
                // Replace the search key with the new location
                $new_meta_value = $this->replace_location($meta_value, $search_key, $location);
                update_post_meta($new_post_id, $meta_key, $new_meta_value);
            }
        }
    }

    /**
     * Recursively replace location in array/object values
     *
     * @param mixed $data Data to process
     * @param string $search_key Text to search for
     * @param string $location New location value
     * @return mixed Updated data
     */
    private function replace_location_recursive($data, $search_key, $location) {
        if (is_string($data)) {
            return $this->replace_location($data, $search_key, $location);
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->replace_location_recursive($value, $search_key, $location);
            }
        }
        
        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->$key = $this->replace_location_recursive($value, $search_key, $location);
            }
        }
        
        return $data;
    }

    /**
     * Replace location in text while preserving case
     *
     * @param string $text Original text
     * @param string $search_key Text to search for
     * @param string $location New location value
     * @return string Updated text
     */
    private function replace_location($text, $search_key, $location) {
        if (!is_string($text) || empty($text)) {
            return $text;
        }

        // Case variations to replace
        $search_variations = array(
            $search_key, // Original
            ucfirst($search_key), // First letter capital
            strtoupper($search_key), // All caps
            strtolower($search_key) // All lowercase
        );

        $replace_variations = array(
            $location, // Original
            ucfirst($location), // First letter capital
            strtoupper($location), // All caps
            strtolower($location) // All lowercase
        );

        return str_replace($search_variations, $replace_variations, $text);
    }

    /**
     * Duplicate attachments from template post to new post
     *
     * @param int $template_id Original post ID
     * @param int $new_post_id New post ID
     */
    private function duplicate_attachments($template_id, $new_post_id) {
        // Get all attachments
        $attachments = get_attached_media('', $template_id);
        
        foreach ($attachments as $attachment) {
            // Create a new post meta entry to associate the existing attachment
            // with the new post WITHOUT removing it from the original post
            add_post_meta($new_post_id, '_wp_attached_file', get_post_meta($attachment->ID, '_wp_attached_file', true));
            
            // Copy the attachment metadata
            $attachment_metadata = wp_get_attachment_metadata($attachment->ID);
            if ($attachment_metadata) {
                add_post_meta($new_post_id, '_wp_attachment_metadata', $attachment_metadata);
            }
            
            // Add the attachment ID to the new post's attachments
            add_post_meta($new_post_id, '_wp_attachment_ids', $attachment->ID);
            
            // Update post parent for the attachment to include both posts
            // This creates a many-to-many relationship
            add_post_meta($attachment->ID, '_wp_additional_parents', $new_post_id);
        }
        
        // If using Elementor, ensure image references are preserved
        if (did_action('elementor/loaded')) {
            $elementor_data = get_post_meta($template_id, '_elementor_data', true);
            if ($elementor_data) {
                update_post_meta($new_post_id, '_elementor_data', wp_slash($elementor_data));
            }
        }
    }

    /**
     * Duplicate Elementor data from template to new post
     *
     * @param int $template_id Original post ID
     * @param int $new_post_id New post ID
     * @param string $search_key Text to search for
     * @param string $location New location value
     */
    private function duplicate_elementor_data($template_id, $new_post_id, $search_key, $location) {
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Copy Elementor page settings
        $elementor_page_settings = get_post_meta($template_id, '_elementor_page_settings', true);
        if ($elementor_page_settings) {
            update_post_meta($new_post_id, '_elementor_page_settings', $elementor_page_settings);
        }

        // Mark post as built with Elementor
        update_post_meta($new_post_id, '_elementor_edit_mode', 'builder');

        // Get Elementor data
        $elementor_data = get_post_meta($template_id, '_elementor_data', true);
        if (!empty($elementor_data)) {
            // If data is stored as a string, decode it
            if (is_string($elementor_data)) {
                $elementor_data = json_decode($elementor_data, true);
            }

            // Replace location text in Elementor data
            $elementor_data = $this->replace_location_in_elementor_data($elementor_data, $search_key, $location);

            // Update Elementor data
            update_post_meta($new_post_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
        }

        // Copy Elementor version
        $elementor_version = get_post_meta($template_id, '_elementor_version', true);
        if ($elementor_version) {
            update_post_meta($new_post_id, '_elementor_version', $elementor_version);
        }

        // Copy Elementor template type
        $template_type = get_post_meta($template_id, '_elementor_template_type', true);
        if ($template_type) {
            update_post_meta($new_post_id, '_elementor_template_type', $template_type);
        }

        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    /**
     * Recursively replace location text in Elementor data
     *
     * @param array $elementor_data Elementor data array
     * @param string $search_key Text to search for
     * @param string $location New location value
     * @return array Updated Elementor data
     */
    private function replace_location_in_elementor_data($elementor_data, $search_key, $location) {
        if (!is_array($elementor_data)) {
            return $elementor_data;
        }

        foreach ($elementor_data as $key => &$data) {
            if ($key === 'settings' && is_array($data)) {
                // Replace text in all settings
                array_walk_recursive($data, function(&$value) use ($search_key, $location) {
                    if (is_string($value)) {
                        $value = $this->replace_location($value, $search_key, $location);
                    }
                });
            } elseif ($key === 'elements' && is_array($data)) {
                // Process nested elements
                $data = $this->replace_location_in_elementor_data($data, $search_key, $location);
            } elseif (is_array($data)) {
                // Recursively process other arrays
                $data = $this->replace_location_in_elementor_data($data, $search_key, $location);
            }
        }

        return $elementor_data;
    }

    /**
     * Duplicate featured image from template to new post
     *
     * @param int $template_id Original post ID
     * @param int $new_post_id New post ID
     */
    private function duplicate_featured_image($template_id, $new_post_id) {
        $thumbnail_id = get_post_thumbnail_id($template_id);
        
        if (!$thumbnail_id) {
            return;
        }
        
        // Simply set the same featured image ID to the new post
        set_post_thumbnail($new_post_id, $thumbnail_id);
    }

    /**
     * Duplicate taxonomies from template to new post
     *
     * @param int $template_id Original post ID
     * @param int $new_post_id New post ID
     */
    private function duplicate_taxonomies($template_id, $new_post_id) {
        // Get all taxonomies for the post type
        $taxonomies = get_object_taxonomies(get_post_type($template_id));
        
        foreach ($taxonomies as $taxonomy) {
            // Get all terms for this taxonomy on the template post
            $terms = wp_get_object_terms($template_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                // Set the terms on the new post
                wp_set_object_terms($new_post_id, $terms, $taxonomy);
            }
        }
    }

    /**
     * Helper function to process Elementor data and replace locations
     *
     * @param array $elements Elementor elements array
     * @param string $search_key Text to search for
     * @param string $location New location value
     * @return array Updated elements
     */
    private function process_elementor_elements($elements, $search_key, $location) {
        foreach ($elements as &$element) {
            // Process settings
            if (!empty($element['settings'])) {
                foreach ($element['settings'] as $setting_key => &$setting_value) {
                    if (is_string($setting_value)) {
                        $setting_value = $this->replace_location($setting_value, $search_key, $location);
                    } elseif (is_array($setting_value)) {
                        $setting_value = $this->replace_location_recursive($setting_value, $search_key, $location);
                    }
                }
            }

            // Process elements recursively
            if (!empty($element['elements'])) {
                $element['elements'] = $this->process_elementor_elements($element['elements'], $search_key, $location);
            }
        }

        return $elements;
    }
} 