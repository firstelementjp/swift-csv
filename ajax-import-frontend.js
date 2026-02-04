// Ajax CSV Import - Frontend
function SwiftCSVAjaxImport() {
    let importing = false;
    let currentRow = 0;
    let totalRows = 0;
    
    function startImport(filePath) {
        if (importing) return;
        
        importing = true;
        currentRow = 0;
        
        // Show progress
        showProgress(0);
        
        // Start processing
        processBatch(filePath);
    }
    
    function processBatch(filePath) {
        jQuery.post(ajaxurl, {
            action: 'swift_csv_ajax_import',
            start_row: currentRow,
            file_path: filePath
        }, function(response) {
            if (response.error) {
                showError(response.error);
                importing = false;
                return;
            }
            
            // Update progress
            currentRow = response.processed;
            totalRows = response.total;
            
            showProgress(response.progress);
            
            if (response.continue) {
                // Continue with next batch
                setTimeout(function() {
                    processBatch(filePath);
                }, 100); // 100ms delay
            } else {
                // Complete
                showComplete();
                importing = false;
            }
        }).fail(function() {
            showError('Ajax request failed');
            importing = false;
        });
    }
    
    function showProgress(percent) {
        jQuery('#import-progress').text('Progress: ' + percent + '%');
        jQuery('#import-bar').css('width', percent + '%');
    }
    
    function showComplete() {
        jQuery('#import-progress').text('Import completed!');
        jQuery('#import-bar').css('width', '100%');
    }
    
    function showError(message) {
        jQuery('#import-progress').text('Error: ' + message);
    }
    
    // Public method
    return {
        start: startImport
    };
}

// Usage:
// var importer = SwiftCSVAjaxImport();
// importer.start('/path/to/csv/file');
