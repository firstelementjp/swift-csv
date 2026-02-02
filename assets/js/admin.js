/**
 * Swift CSV Admin JavaScript
 *
 * Handles batch export functionality with progress tracking.
 *
 * @since 0.9.3
 */

jQuery(document).ready(function($) {
    // Export batch handling
    $('#export-csv-btn').on('click', function(e) {
        e.preventDefault();
        
        const $form = $('#swift-csv-export-form');
        const postType = $('#post_type').val();
        const postsPerPage = $('#posts_per_page').val();
        
        // Validate input
        if (!postType || !postsPerPage || postsPerPage < 1) {
            alert(swiftCSV.messages.error);
            return;
        }
        
        // Start batch export
        startBatchExport(postType, postsPerPage);
    });
    
    /**
     * Start batch export process
     */
    function startBatchExport(postType, postsPerPage) {
        // Show progress UI
        showProgress();
        
        // Start batch export via AJAX
        $.ajax({
            url: swiftCSV.ajaxUrl,
            type: 'POST',
            data: {
                action: 'swift_csv_start_export',
                nonce: swiftCSV.nonce,
                post_type: postType,
                posts_per_page: postsPerPage
            },
            success: function(response) {
                if (response.success && response.data.batch_id) {
                    // Start polling for progress
                    pollProgress(response.data.batch_id);
                } else {
                    showError(response.data.message || swiftCSV.messages.error);
                }
            },
            error: function() {
                showError(swiftCSV.messages.error);
            }
        });
    }
    
    /**
     * Poll for export progress
     */
    function pollProgress(batchId) {
        const interval = setInterval(function() {
            $.ajax({
                url: swiftCSV.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'swift_csv_export_progress',
                    nonce: swiftCSV.nonce,
                    batch_id: batchId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        updateProgress(response.data);
                        
                        // Check if completed
                        if (response.data.status === 'completed') {
                            clearInterval(interval);
                            showComplete(response.data);
                        } else if (response.data.status === 'error') {
                            clearInterval(interval);
                            showError(response.data.message || swiftCSV.messages.error);
                        }
                    } else {
                        clearInterval(interval);
                        showError(swiftCSV.messages.error);
                    }
                },
                error: function() {
                    clearInterval(interval);
                    showError(swiftCSV.messages.error);
                }
            });
        }, 2000); // Poll every 2 seconds
    }
    
    /**
     * Show progress UI
     */
    function showProgress() {
        $('#swift-csv-export-progress').show();
        $('#swift-csv-export-form').addClass('swift-csv-exporting');
        updateProgressBar(0);
        updateStatus(swiftCSV.messages.preparing);
        hideDownloadLink();
    }
    
    /**
     * Update progress display
     */
    function updateProgress(data) {
        const percentage = Math.round((data.processed_rows / data.total_rows) * 100);
        updateProgressBar(percentage);
        updateStatus(`${swiftCSV.messages.processing} (${data.processed_rows} / ${data.total_rows})`);
    }
    
    /**
     * Update progress bar
     */
    function updateProgressBar(percentage) {
        $('.progress-fill').css('width', percentage + '%');
        $('.progress-text').text(percentage + '%');
    }
    
    /**
     * Update status message
     */
    function updateStatus(message) {
        $('.progress-status').text(message);
    }
    
    /**
     * Show completion state
     */
    function showComplete(data) {
        updateProgressBar(100);
        updateStatus(swiftCSV.messages.completed);
        
        if (data.file_path && data.download_url) {
            showDownloadLink(data.download_url);
        }
        
        // Re-enable form
        $('#swift-csv-export-form').removeClass('swift-csv-exporting');
    }
    
    /**
     * Show error state
     */
    function showError(message) {
        updateStatus(message);
        $('.progress-bar').addClass('error');
        
        // Re-enable form
        $('#swift-csv-export-form').removeClass('swift-csv-exporting');
    }
    
    /**
     * Show download link
     */
    function showDownloadLink(url) {
        const $link = $('#export-download-link a');
        $link.attr('href', url);
        $('#export-download-link').show();
    }
    
    /**
     * Hide download link
     */
    function hideDownloadLink() {
        $('#export-download-link').hide();
    }
});
