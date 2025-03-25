(function($) {
    'use strict';

    class PageDuplicator {
        constructor() {
            this.form = $('#page-duplicator-form');
            this.previewButton = $('#preview-button');
            this.submitButton = $('#duplicate-button');
            this.confirmButton = $('#confirm-duplication');
            this.cancelButton = $('#cancel-duplication');
            this.spinner = this.form.find('.spinner');
            this.previewContainer = $('#preview-results');
            this.previewContent = $('.preview-content');
            this.resultsContainer = $('#duplication-results');
            this.resultsContent = $('.results-content');
            
            this.bindEvents();
        }

        bindEvents() {
            this.form.on('submit', (e) => this.handleSubmit(e));
        }

        handleSubmit(e) {
            e.preventDefault();
            
            // Disable form and show spinner
            this.toggleLoading(true);
            
            // Get form data
            const formData = new FormData(this.form[0]);
            
            // Process locations into array and clean the data
            const locations = formData.get('locations')
                .split('\n')
                .map(location => location.trim())
                .filter(location => location)
            
            // Ensure we have locations
            if (!locations.length) {
                this.showError('Please enter at least one location');
                this.toggleLoading(false);
                return;
            }
            
            formData.append('action', 'duplicate_pages');
            formData.append('nonce', pdSettings.nonce);
            
            // Set locations as comma-separated string instead of JSON
            formData.set('locations', locations.join('|'));

            // Send AJAX request
            $.ajax({
                url: pdSettings.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => this.handleSuccess(response),
                error: (xhr) => this.handleError(xhr)
            });
        }

        handleSuccess(response) {
            if (!response.success) {
                this.showError(response.data.message);
                return;
            }

            this.resultsContent.html('');
            
            // Display results
            response.data.results.forEach(result => {
                const resultClass = result.success ? 'success' : 'error';
                const html = `
                    <div class="result-item ${resultClass}">
                        <strong>${result.location}:</strong> ${result.message}
                        ${result.url ? `<br><a href="${result.url}" target="_blank">View Page</a>` : ''}
                    </div>
                `;
                this.resultsContent.append(html);
            });

            this.resultsContainer.removeClass('hidden');
            this.toggleLoading(false);
        }

        handleError(xhr) {
            let errorMessage = 'An error occurred while processing your request.';
            
            console.error('AJAX Error:', xhr);
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data.message;
                if (xhr.responseJSON.data.details) {
                    errorMessage += '<br><small>' + xhr.responseJSON.data.details + '</small>';
                }
            }
            
            this.showError(errorMessage);
            this.toggleLoading(false);
        }

        showError(message) {
            this.resultsContent.html(`
                <div class="result-item error">
                    <strong>Error:</strong> ${message}
                </div>
            `);
            this.resultsContainer.removeClass('hidden');
            
            // Log to console for debugging
            console.error('Page Duplicator Error:', message);
        }

        toggleLoading(isLoading) {
            this.submitButton.prop('disabled', isLoading);
            this.spinner.toggleClass('is-active', isLoading);
        }
    }

    // Initialize when document is ready
    $(document).ready(() => new PageDuplicator());

})(jQuery); 