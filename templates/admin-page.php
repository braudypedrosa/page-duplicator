<div class="wrap">
    <h1><?php _e('Page Duplicator', 'page-duplicator'); ?></h1>
    
    <div class="page-duplicator-form">
        <form id="page-duplicator-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="template_url"><?php _e('Template Page URL', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="template_url" name="template_url" class="regular-text" required>
                        <p class="description"><?php _e('Enter the full URL of the page you want to duplicate', 'page-duplicator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="locations"><?php _e('Locations', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <textarea id="locations" name="locations" rows="6" class="large-text" required></textarea>
                        <p class="description"><?php _e('Enter one location per line', 'page-duplicator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="search_key"><?php _e('Search Key', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="search_key" name="search_key" class="regular-text" required>
                        <p class="description"><?php _e('Enter the text to be replaced (e.g., "Watersound")', 'page-duplicator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="post_status"><?php _e('Post Status', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <select id="post_status" name="post_status">
                            <option value="publish"><?php _e('Published', 'page-duplicator'); ?></option>
                            <option value="draft"><?php _e('Draft', 'page-duplicator'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="slug_handling"><?php _e('Duplicate Page Slug Handling', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <select id="slug_handling" name="slug_handling">
                            <option value="increment"><?php _e('Auto-increment slug (e.g., location-name-2)', 'page-duplicator'); ?></option>
                            <option value="skip"><?php _e('Skip if exists', 'page-duplicator'); ?></option>
                            <option value="update"><?php _e('Update existing page', 'page-duplicator'); ?></option>
                        </select>
                        <p class="description"><?php _e('How to handle cases where a page with the same URL already exists', 'page-duplicator'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="continue_on_error"><?php _e('Error Handling', 'page-duplicator'); ?></label>
                    </th>
                    <td>
                        <select id="continue_on_error" name="continue_on_error">
                            <option value="0"><?php _e('Stop and rollback on any error', 'page-duplicator'); ?></option>
                            <option value="1"><?php _e('Continue with remaining pages if error occurs', 'page-duplicator'); ?></option>
                        </select>
                        <p class="description"><?php _e('How to handle errors during duplication', 'page-duplicator'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary" id="duplicate-button">
                    <?php _e('Duplicate Now', 'page-duplicator'); ?>
                </button>
                <span class="spinner"></span>
            </p>
        </form>

        <div id="duplication-results" class="hidden">
            <h3><?php _e('Duplication Results', 'page-duplicator'); ?></h3>
            <div class="results-content"></div>
        </div>
    </div>
</div> 