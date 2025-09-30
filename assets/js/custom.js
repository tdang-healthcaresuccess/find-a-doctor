jQuery(function($) {
    // Highlight row on hover
    $('.wp-list-table tr').hover(function() {
        $(this).addClass('highlight');
    }, function() {
        $(this).removeClass('highlight');
    });
});

jQuery(document).ready(function ($) {
    $('#import-doctor-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'fnd_handle_doctor_upload'); 

        $('#fnd-import-overlay').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                $('#fnd-import-overlay').hide();
                $('#fnd-import-result').html(response); 
            },
            error: function () {
                $('#fnd-import-overlay').hide();
                $('#fnd-import-result').html('<div class="error"><p>Something went wrong. Please try again.</p></div>');
            }
        });
    });
});

function fndToggleGroup(group, check) {
  var root = document.querySelector('.fnd-checkbox-grid[data-group="' + group + '"]');
  if (!root) return;
  root.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
    cb.checked = !!check;
  });
}

document.getElementById('profile_img')?.addEventListener('change', function (e) {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  const img = document.getElementById('fnd-profile-preview');
  if (!img) return;
  const reader = new FileReader();
  reader.onload = function (ev) { img.src = ev.target.result; };
  reader.readAsDataURL(file);
});

// API Connection Test
jQuery(document).ready(function($) {
    $('#test-api-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#api-test-result');
        
        button.prop('disabled', true).text('Testing...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        
        console.log('Testing API connection...');
        console.log('AJAX URL:', fad_ajax_object.ajaxurl);
        console.log('Nonce:', fad_ajax_object.nonce);
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_test_api_connection',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                button.prop('disabled', false).text('Test API Connection');
                
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p><strong>Success!</strong> ' + response.data + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {xhr: xhr, status: status, error: error});
                console.log('Response Text:', xhr.responseText);
                button.prop('disabled', false).text('Test API Connection');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to test API connection. Check browser console for details.</p></div>');
            }
        });
    });
    
    // Database Schema Validation
    $('#validate-schema').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#schema-validation-result');
        var fixButton = $('#fix-schema');
        
        button.prop('disabled', true).text('Checking...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        fixButton.hide();
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_validate_schema',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Check Database Schema');
                
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p><strong>✓ Database Schema Valid!</strong> ' + response.data.message + '</p></div>');
                } else {
                    var hasIssues = false;
                    var errorHtml = '<div class="notice notice-error"><p><strong>✗ Database Schema Issues Found:</strong></p>';
                    
                    if (response.data && response.data.details) {
                        var details = response.data.details;
                        
                        if (details.missing_tables && details.missing_tables.length > 0) {
                            errorHtml += '<p><strong>Missing Tables:</strong> ' + details.missing_tables.join(', ') + '</p>';
                            hasIssues = true;
                        }
                        
                        if (details.missing_columns && Object.keys(details.missing_columns).length > 0) {
                            errorHtml += '<p><strong>Missing Columns:</strong></p><ul>';
                            for (var table in details.missing_columns) {
                                errorHtml += '<li><strong>' + table + ':</strong> ' + details.missing_columns[table].join(', ') + '</li>';
                            }
                            errorHtml += '</ul>';
                            hasIssues = true;
                        }
                        
                        if (details.errors && details.errors.length > 0) {
                            errorHtml += '<p><strong>Errors:</strong></p><ul>';
                            for (var i = 0; i < details.errors.length; i++) {
                                errorHtml += '<li>' + details.errors[i] + '</li>';
                            }
                            errorHtml += '</ul>';
                            hasIssues = true;
                        }
                    }
                    
                    // If no specific issues found, show the general error message
                    if (!hasIssues) {
                        errorHtml += '<p>' + (response.data ? response.data.message : 'Unknown validation error occurred.') + '</p>';
                    }
                    
                    errorHtml += '</div>';
                    resultDiv.html(errorHtml);
                    fixButton.show();
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Check Database Schema');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to validate schema. Check browser console for details.</p></div>');
            }
        });
    });
    
    // Database Schema Fix
    $('#fix-schema').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#schema-validation-result');
        
        if (!confirm('This will update your database schema. Are you sure you want to continue?')) {
            return;
        }
        
        button.prop('disabled', true).text('Fixing...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_fix_schema',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Fix Database Schema');
                
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p><strong>✓ Schema Fixed!</strong> ' + response.data.message + '</p></div>');
                    button.hide();
                    // Automatically re-validate
                    setTimeout(function() {
                        $('#validate-schema').click();
                    }, 1000);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>✗ Fix Failed:</strong> ' + (response.data ? response.data.message : 'Unknown error') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Fix Database Schema');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to fix schema. Check browser console for details.</p></div>');
            }
        });
    });
    
    // API Import Form
    $('#api-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var progressDiv = $('#import-progress');
        var resultDiv = $('#import-result');
        var submitButton = $('#start-import');
        
        submitButton.prop('disabled', true);
        progressDiv.show();
        resultDiv.html('');
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: formData + '&action=fad_import_from_api&nonce=' + fad_ajax_object.nonce,
            success: function(response) {
                submitButton.prop('disabled', false);
                progressDiv.hide();
                
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    if (response.data.preview_data && response.data.preview_data.length > 0) {
                        var previewHtml = '<h3>Preview Data (First 5 Records):</h3><table class="widefat"><thead><tr><th>Name</th><th>Specialty</th><th>Location</th></tr></thead><tbody>';
                        response.data.preview_data.slice(0, 5).forEach(function(physician) {
                            previewHtml += '<tr><td>' + physician.full_name + '</td><td>' + physician.specialty + '</td><td>' + physician.location + '</td></tr>';
                        });
                        previewHtml += '</tbody></table>';
                        resultDiv.append(previewHtml);
                    }
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function() {
                submitButton.prop('disabled', false);
                progressDiv.hide();
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to import data. Please try again.</p></div>');
            }
        });
    });
    
    // Preview Import Button
    $('#preview-import').on('click', function(e) {
        e.preventDefault();
        $('#dry-run-mode').prop('checked', true);
        $('#api-import-form').submit();
    });
    
    // Sync Reference Data
    $('#sync-reference-data').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#sync-result');
        
        button.prop('disabled', true).text('Syncing...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_sync_reference_data',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Sync Reference Data');
                
                if (response.success) {
                    var message = response.data.message || response.data;
                    var details = '';
                    
                    // Add detailed breakdown if available
                    if (response.data.details) {
                        var syncDetails = response.data.details;
                        var breakdown = [];
                        
                        if (syncDetails.languages_synced !== undefined) {
                            breakdown.push('Languages synced: ' + syncDetails.languages_synced);
                        }
                        if (syncDetails.hospitals_synced !== undefined) {
                            breakdown.push('Hospitals synced: ' + syncDetails.hospitals_synced);
                        }
                        if (syncDetails.insurances_synced !== undefined) {
                            breakdown.push('Insurances synced: ' + syncDetails.insurances_synced);
                        }
                        
                        if (breakdown.length > 0) {
                            details = '<br><small>' + breakdown.join('<br>') + '</small>';
                        }
                    }
                    
                    resultDiv.html('<div class="notice notice-success"><p>' + message + details + '</p></div>');
                } else {
                    var errorMessage = response.data.message || response.data;
                    resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + errorMessage + '</p></div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Sync Reference Data');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to sync reference data. Please try again.</p></div>');
            }
        });
    });
    
    // Duplicate Management
    $('#check-duplicates').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#duplicate-results');
        
        button.prop('disabled', true).text('Checking...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_check_duplicates',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Check for Duplicates');
                
                if (response.success) {
                    resultDiv.html(response.data);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Check for Duplicates');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to check duplicates. Please try again.</p></div>');
            }
        });
    });
    
    // Delete All Physicians - Initial click shows confirmation
    $('#delete-all-physicians').on('click', function(e) {
        e.preventDefault();
        
        // Show confirmation buttons
        $('#confirm-delete-all').show();
        $('#cancel-delete-all').show();
        $(this).prop('disabled', true).text('Delete All Physicians (Confirm Below)');
    });
    
    // Confirm Delete All Physicians - Actually performs the deletion
    $('#confirm-delete-all').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var deleteButton = $('#delete-all-physicians');
        var cancelButton = $('#cancel-delete-all');
        
        button.prop('disabled', true).text('Deleting...');
        cancelButton.prop('disabled', true);
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_delete_all_physicians',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Confirm Delete All').hide();
                cancelButton.prop('disabled', false).hide();
                deleteButton.prop('disabled', false).text('Delete All Physicians');
                
                if (response.success) {
                    $('#delete-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Refresh physician count
                    refreshPhysicianCount();
                } else {
                    $('#delete-result').html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Confirm Delete All').hide();
                cancelButton.prop('disabled', false).hide();
                deleteButton.prop('disabled', false).text('Delete All Physicians');
                $('#delete-result').html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to delete physicians. Please try again.</p></div>');
            }
        });
    });
    
    // Cancel Delete All Physicians
    $('#cancel-delete-all').on('click', function(e) {
        e.preventDefault();
        
        // Hide confirmation buttons and reset
        $('#confirm-delete-all').hide();
        $(this).hide();
        $('#delete-all-physicians').prop('disabled', false).text('Delete All Physicians');
        $('#delete-result').html('');
    });
    
    // Prevent API import form submission since we're using button handlers instead
    $('#api-import-form').on('submit', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Batch Import Variables
    var batchImportState = {
        isRunning: false,
        isPaused: false,
        isDryRun: false,
        currentBatch: 0,
        totalBatches: 0,
        totalPhysicians: 0,
        batchSize: 50,
        totalImported: 0,
        totalUpdated: 0,
        totalSkipped: 0,
        searchParams: {}
    };
    
    // Preview Batch Import
    $('#preview-batch-import').on('click', function(e) {
        e.preventDefault();
        startBatchImport(true); // dry run mode
    });
    
    // Start Batch Import
    $('#start-batch-import').on('click', function(e) {
        e.preventDefault();
        startBatchImport(false); // actual import
    });
    
    // Unified function to start batch import
    function startBatchImport(isDryRun) {
        if (batchImportState.isRunning) {
            return;
        }
        
        batchImportState.isDryRun = isDryRun;
        
        // Get form parameters
        var formData = $('#api-import-form').serializeArray();
        batchImportState.searchParams = {};
        
        $.each(formData, function(i, field) {
            if (field.value && field.name !== 'action' && field.name !== 'nonce') {
                batchImportState.searchParams[field.name] = field.value;
            }
        });
        
        batchImportState.batchSize = parseInt($('#batch-size').val()) || 50;
        
        // Initialize batch import
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_start_batch_import',
                nonce: fad_ajax_object.nonce,
                specialty: batchImportState.searchParams.specialty || '',
                location: batchImportState.searchParams.location || '',
                limit: batchImportState.searchParams.limit || '',
                import_all_pages: batchImportState.searchParams.import_all_pages || '',
                batch_size: batchImportState.batchSize
            },
            success: function(response) {
                if (response.success) {
                    batchImportState.totalPhysicians = response.data.total_physicians;
                    batchImportState.totalBatches = response.data.estimated_batches;
                    batchImportState.currentBatch = 0;
                    batchImportState.totalImported = 0;
                    batchImportState.totalUpdated = 0;
                    batchImportState.totalSkipped = 0;
                    batchImportState.isRunning = true;
                    batchImportState.isPaused = false;
                    
                    showBatchProgress();
                    updateBatchProgress();
                    
                    $('#batch-messages').html('<div class="batch-message">Starting batch import: ' + response.data.message + '</div>');
                    
                    // Start processing batches
                    processBatch();
                } else {
                    alert('Failed to start batch import: ' + response.data);
                }
            },
            error: function() {
                alert('Error starting batch import. Please try again.');
            }
        });
    }
    
    // Process a single batch
    function processBatch() {
        if (!batchImportState.isRunning || batchImportState.isPaused) {
            return;
        }
        
        if (batchImportState.currentBatch >= batchImportState.totalBatches) {
            completeBatchImport();
            return;
        }
        
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_import_batch',
                nonce: fad_ajax_object.nonce,
                current_batch: batchImportState.currentBatch,
                batch_size: batchImportState.batchSize,
                specialty: batchImportState.searchParams.specialty || '',
                location: batchImportState.searchParams.location || '',
                dry_run: batchImportState.isDryRun ? '1' : '0'
            },
            success: function(response) {
                console.log('Batch response:', response);
                
                if (response.success) {
                    var data = response.data;
                    console.log('Batch data:', data);
                    
                    // Update totals
                    batchImportState.totalImported = data.total_imported || 0;
                    batchImportState.totalUpdated = data.total_updated || 0;
                    batchImportState.totalSkipped = data.total_skipped || 0;
                    
                    // Update progress
                    updateBatchProgress();
                    
                    // Add message
                    var messageClass = data.errors && data.errors.length > 0 ? 'batch-error' : 'batch-success';
                    var message = '<div class="batch-message ' + messageClass + '">' + data.message + '</div>';
                    $('#batch-messages').append(message);
                    
                    // Auto-scroll messages
                    $('#batch-messages').scrollTop($('#batch-messages')[0].scrollHeight);
                    
                    // Continue with next batch after a short delay
                    setTimeout(function() {
                        console.log('Checking if should continue: is_complete=' + data.is_complete + ', has_more_batches=' + data.has_more_batches);
                        
                        if (data.is_complete || !data.has_more_batches) {
                            console.log('Import complete, stopping');
                            completeBatchImport();
                        } else {
                            console.log('Continuing to next batch');
                            // Only increment batch counter if we're continuing
                            batchImportState.currentBatch++;
                            processBatch();
                        }
                    }, 500);
                    
                } else {
                    var errorMsg = '<div class="batch-message batch-error">Batch ' + (batchImportState.currentBatch + 1) + ' failed: ' + response.data + '</div>';
                    $('#batch-messages').append(errorMsg);
                    
                    // Continue anyway or stop?
                    if (confirm('Batch failed. Continue with next batch?')) {
                        batchImportState.currentBatch++;
                        setTimeout(processBatch, 1000);
                    } else {
                        cancelBatchImport();
                    }
                }
            },
            error: function() {
                var errorMsg = '<div class="batch-message batch-error">Network error on batch ' + (batchImportState.currentBatch + 1) + '</div>';
                $('#batch-messages').append(errorMsg);
                
                if (confirm('Network error. Retry batch?')) {
                    setTimeout(processBatch, 2000);
                } else {
                    cancelBatchImport();
                }
            }
        });
    }
    
    // Show batch progress section
    function showBatchProgress() {
        $('#batch-progress').show();
        $('#import-result').html('');
        
        if (batchImportState.isDryRun) {
            $('#start-batch-import').prop('disabled', true).text('Preview Running...');
            $('#preview-batch-import').prop('disabled', true);
            $('#batch-progress h3').text('Batch Import Preview Progress');
        } else {
            $('#start-batch-import').prop('disabled', true).text('Import Running...');
            $('#preview-batch-import').prop('disabled', true);
            $('#batch-progress h3').text('Batch Import Progress');
        }
    }
    
    // Update batch progress display
    function updateBatchProgress() {
        var processed = batchImportState.currentBatch * batchImportState.batchSize;
        if (processed > batchImportState.totalPhysicians) {
            processed = batchImportState.totalPhysicians;
        }
        
        var percentage = batchImportState.totalPhysicians > 0 
            ? Math.round((processed / batchImportState.totalPhysicians) * 100) 
            : 0;
        
        $('#progress-fill').css('width', percentage + '%');
        $('#progress-current').text(processed.toLocaleString());
        $('#progress-total').text(batchImportState.totalPhysicians.toLocaleString());
        $('#progress-percentage').text(percentage);
        
        $('#current-batch').text(batchImportState.currentBatch + 1);
        $('#total-batches').text(batchImportState.totalBatches);
        $('#total-imported').text(batchImportState.totalImported.toLocaleString());
        $('#total-updated').text(batchImportState.totalUpdated.toLocaleString());
        $('#total-skipped').text(batchImportState.totalSkipped.toLocaleString());
    }
    
    // Complete batch import
    function completeBatchImport() {
        batchImportState.isRunning = false;
        
        if (batchImportState.isDryRun) {
            $('#import-result').html('<div class="notice notice-info"><p><strong>Preview Complete!</strong> Would import ' + 
                batchImportState.totalImported + ' physicians. <em>No data was actually imported.</em></p></div>');
            $('#start-batch-import').prop('disabled', false).text('Import Physicians');
            $('#preview-batch-import').prop('disabled', false).text('Preview Import (No Changes)');
        } else {
            $('#import-result').html('<div class="notice notice-success"><p><strong>Import Complete!</strong> Imported ' + 
                batchImportState.totalImported + ' physicians.</p></div>');
            $('#start-batch-import').prop('disabled', false).text('Import Physicians');
            $('#preview-batch-import').prop('disabled', false).text('Preview Import (No Changes)');
            
            // Refresh physician count
            refreshPhysicianCount();
        }
        
        $('#batch-progress').hide();
    }
    
    // Cancel batch import
    function cancelBatchImport() {
        batchImportState.isRunning = false;
        batchImportState.isPaused = false;
        
        $('#start-batch-import').prop('disabled', false).text('Import Physicians');
        $('#preview-batch-import').prop('disabled', false).text('Preview Import (No Changes)');
        $('#batch-progress').hide();
        
        $('#import-result').html('<div class="notice notice-warning"><p>Import cancelled by user.</p></div>');
    }
    
    // Pause/Resume controls
    $('#pause-import').on('click', function() {
        if (batchImportState.isPaused) {
            batchImportState.isPaused = false;
            $(this).text('Pause');
            processBatch(); // Resume
        } else {
            batchImportState.isPaused = true;
            $(this).text('Resume');
        }
    });
    
    $('#cancel-import').on('click', function() {
        if (confirm('Are you sure you want to cancel the import?')) {
            cancelBatchImport();
        }
    });
    
    // Function to refresh physician count display
    function refreshPhysicianCount() {
        $.ajax({
            url: fad_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_get_physician_count',
                nonce: fad_ajax_object.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#physician-count-info').html('<p><strong>Current physicians in database:</strong> ' + response.data.count.toLocaleString() + '</p>');
                }
            },
            error: function() {
                console.log('Failed to refresh physician count');
            }
        });
    }
});