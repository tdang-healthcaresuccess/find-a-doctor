<?php
/**
 * API Import Admin Page
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '../api-import.php';

function fnd_render_api_import_page() {
    ?>
    <div class="wrap">
        <h1>API Data Import</h1>
        
        <!-- API Connection Test -->
        <div class="card">
            <h2>API Connection Test</h2>
            <p>Test the connection to the external API before importing data.</p>
            <button id="test-api-connection" class="button">Test API Connection</button>
            <div id="api-test-result"></div>
        </div>
        
        <!-- Database Schema Validation -->
        <div class="card">
            <h2>Database Schema Validation</h2>
            <p>Validate that the database has all required tables and columns for importing API data.</p>
            <button id="validate-schema" class="button">Check Database Schema</button>
            <button id="fix-schema" class="button button-primary" style="display:none;">Fix Database Schema</button>
            <div id="schema-validation-result"></div>
        </div>
        
        <!-- Import Physicians -->
        <div class="card">
            <h2>Import Physicians</h2>
            <p>Import physician data from the external API.</p>
            
            <form id="api-import-form">
                <input type="hidden" id="dry-run-mode" name="dry_run" value="0" />
                <table class="form-table">
                    <tr>
                        <th scope="row">Search Parameters (Optional)</th>
                        <td>
                            <label for="search-specialty">Specialty:</label>
                            <input type="text" id="search-specialty" name="specialty" placeholder="e.g., Cardiology" />
                            <br><br>
                            
                            <label for="search-location">Location:</label>
                            <input type="text" id="search-location" name="location" placeholder="e.g., City, State" />
                            <br><br>
                            
                            <label for="search-limit">Limit:</label>
                            <input type="number" id="search-limit" name="limit" value="100" min="1" max="1000" />
                            <small>Maximum number of physicians to import (1-1000). <strong>Note:</strong> If "Import All Pages" is checked, this limit is ignored and ALL ~3,237 physicians will be imported.</small>
                            <br><br>
                            
                            <label for="batch-size">Batch Size:</label>
                            <input type="number" id="batch-size" name="batch_size" value="50" min="10" max="100" />
                            <small>Number of physicians to process per batch (10-100). Smaller batches prevent timeouts but take longer.</small>
                            <br><br>
                            
                            <label>
                                <input type="checkbox" id="import-all-pages" name="import_all_pages" value="1" />
                                <strong>Import All Pages</strong> - Import all physicians from all pages (uses batch processing)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="start-batch-import" class="button-primary button-large">Import Physicians</button>
                    <button type="button" id="preview-batch-import" class="button">Preview Import (No Changes)</button>
                </p>
            </form>
            
            <div id="batch-progress" style="display: none;">
                <h3>Batch Import Progress</h3>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span id="progress-current">0</span> / <span id="progress-total">0</span> physicians
                        (<span id="progress-percentage">0</span>%)
                    </div>
                </div>
                <div class="batch-stats">
                    <span>Batch <span id="current-batch">0</span> of <span id="total-batches">0</span></span> | 
                    <span>Imported: <span id="total-imported">0</span></span> | 
                    <span>Updated: <span id="total-updated">0</span></span> | 
                    <span>Skipped: <span id="total-skipped">0</span></span>
                </div>
                <div class="batch-controls">
                    <button type="button" id="pause-import" class="button">Pause</button>
                    <button type="button" id="cancel-import" class="button button-secondary">Cancel</button>
                </div>
                <div id="batch-messages"></div>
            </div>
            
            <div id="import-progress" style="display: none;">
                <div class="spinner is-active"></div>
                <p>Importing data from API, please wait...</p>
            </div>
            
            <div id="import-result"></div>
        </div>
        
        <!-- Sync Reference Data -->
        <div class="card">
            <h2>Sync Reference Data</h2>
            <p>Sync languages and hospital affiliations from the API.</p>
            <button id="sync-reference-data" class="button">Sync Reference Data</button>
            <div id="sync-result"></div>
        </div>
        
        <!-- Duplicate Management -->
        <div class="card">
            <h2>Duplicate Management</h2>
            <p>Check for and manage duplicate physician records in the database.</p>
            
            <div style="margin-bottom: 15px;">
                <button id="check-duplicates" class="button">Check for Duplicates</button>
                <button id="cleanup-duplicates-preview" class="button">Preview Cleanup</button>
                <button id="cleanup-duplicates-execute" class="button button-secondary" style="display: none;">Execute Cleanup</button>
            </div>
            
            <div id="duplicate-results"></div>
        </div>
        
        <!-- Database Management -->
        <div class="card">
            <h2>Database Management</h2>
            <p><strong>Warning:</strong> These actions are permanent and cannot be undone. Please backup your database before proceeding.</p>
            
            <div style="margin-bottom: 15px;">
                <button id="delete-all-physicians" class="button button-secondary" style="background-color: #dc3232; border-color: #dc3232; color: white;">Delete All Physicians</button>
                <button id="confirm-delete-all" class="button button-primary" style="display: none; background-color: #dc3232; border-color: #dc3232;">Confirm Delete All</button>
                <button id="cancel-delete-all" class="button" style="display: none;">Cancel</button>
            </div>
            
            <div id="delete-result"></div>
            
            <div id="physician-count-info">
                <?php
                global $wpdb;
                $doctors_table = $wpdb->prefix . 'doctors';
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$doctors_table}");
                echo "<p><strong>Current physicians in database:</strong> " . number_format($count) . "</p>";
                ?>
            </div>
        </div>
        
        <!-- API Information -->
        <div class="card">
            <h2>API Information</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Search Physicians</td>
                        <td><?php echo esc_html(fad_get_api_url('search')); ?></td>
                    </tr>
                    <tr>
                        <td>Get Physician</td>
                        <td><?php echo esc_html(fad_get_api_url('physician', ['id' => '{id}'])); ?></td>
                    </tr>
                    <tr>
                        <td>Insurance Networks</td>
                        <td><?php echo esc_html(fad_get_api_url('insurance')); ?></td>
                    </tr>
                    <tr>
                        <td>Languages</td>
                        <td><?php echo esc_html(fad_get_api_url('languages')); ?></td>
                    </tr>
                    <tr>
                        <td>Hospitals</td>
                        <td><?php echo esc_html(fad_get_api_url('hospitals')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle Import All Pages checkbox
        $('#import-all-pages').on('change', function() {
            var limitField = $('#search-limit');
            var limitLabel = $('label[for="search-limit"]');
            
            if ($(this).is(':checked')) {
                limitField.prop('disabled', true).css('opacity', '0.5');
                limitLabel.css('opacity', '0.5');
            } else {
                limitField.prop('disabled', false).css('opacity', '1');
                limitLabel.css('opacity', '1');
            }
        });
        
        // Test API Connection
        $('#test-api-connection').on('click', function() {
            var button = $(this);
            var resultDiv = $('#api-test-result');
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.html('<div class="spinner is-active"></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_test_api_connection',
                    nonce: '<?php echo wp_create_nonce('fad_api_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success"><p>✓ API connection successful!</p></div>');
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>✗ API connection failed: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>✗ Connection test failed</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test API Connection');
                }
            });
        });
        
        // Import Data
        $('#api-import-form').on('submit', function(e) {
            e.preventDefault();
            performImport(false); // Actual import
        });
        
        // Preview Import (Dry Run)
        $('#preview-import').on('click', function(e) {
            e.preventDefault();
            performImport(true); // Dry run
        });
        
        function performImport(isDryRun) {
            var form = $('#api-import-form');
            var progressDiv = $('#import-progress');
            var resultDiv = $('#import-result');
            var submitButton = $('#start-import');
            var previewButton = $('#preview-import');
            
            var searchParams = {
                specialty: $('#search-specialty').val(),
                location: $('#search-location').val(),
                limit: $('#search-limit').val()
            };
            
            var importAllPages = $('#import-all-pages').is(':checked');
            
            // Disable limit input when import all pages is checked
            $('#import-all-pages').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#search-limit').prop('disabled', true).val('ALL');
                } else {
                    $('#search-limit').prop('disabled', false).val('100');
                }
            });
            
            submitButton.prop('disabled', true);
            previewButton.prop('disabled', true);
            progressDiv.show();
            resultDiv.empty();
            
            // Update progress message
            if (isDryRun) {
                progressDiv.find('p').text('Running preview, please wait...');
            } else {
                progressDiv.find('p').text('Importing data from API, please wait...');
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_import_from_api',
                    search_params: searchParams,
                    dry_run: isDryRun ? 'true' : 'false',
                    import_all_pages: importAllPages ? 'true' : 'false',
                    nonce: '<?php echo wp_create_nonce('fad_api_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '';
                        
                        if (isDryRun) {
                            message = '<div class="notice notice-info"><p><strong>📋 Preview Results (Dry Run)</strong><br>';
                            message += data.message;
                            
                            // Add pagination info if available
                            if (data.pagination) {
                                var pager = data.pagination;
                                if (pager.limited_to) {
                                    message += '<br><strong>Note:</strong> Limited to ' + pager.limited_to + ' physicians';
                                } else if (pager.total_items) {
                                    message += '<br><strong>Total found:</strong> ' + pager.total_items + ' physicians';
                                }
                            }
                            
                            message += '</p></div>';
                            
                            // Show detailed preview data
                            if (data.preview_data && data.preview_data.length > 0) {
                                message += '<div class="card"><h3>Preview Data (First 10 physicians)</h3>';
                                message += '<table class="widefat"><thead><tr>';
                                message += '<th>Name</th><th>Action</th><th>Specialties</th><th>Languages</th><th>City, State</th><th>Geolocation</th>';
                                message += '</tr></thead><tbody>';
                                
                                var previewLimit = Math.min(data.preview_data.length, 10);
                                for (var i = 0; i < previewLimit; i++) {
                                    var physician = data.preview_data[i];
                                    var actionClass = physician.action === 'imported' ? 'success' : 
                                                    physician.action === 'updated' ? 'warning' : 'error';
                                    
                                    // Format geolocation display
                                    var geoDisplay = '-';
                                    if (physician.data.latitude && physician.data.longitude) {
                                        geoDisplay = parseFloat(physician.data.latitude).toFixed(6) + ', ' + parseFloat(physician.data.longitude).toFixed(6);
                                    } else if (physician.data.latitude || physician.data.longitude) {
                                        geoDisplay = 'Partial: ' + (physician.data.latitude || 'N/A') + ', ' + (physician.data.longitude || 'N/A');
                                    }
                                    
                                    message += '<tr>';
                                    message += '<td><strong>' + physician.name + '</strong></td>';
                                    message += '<td><span class="badge badge-' + actionClass + '">' + 
                                             physician.action.charAt(0).toUpperCase() + physician.action.slice(1) + '</span>';
                                    if (physician.reason) {
                                        message += '<br><small>' + physician.reason + '</small>';
                                    }
                                    message += '</td>';
                                    message += '<td>' + (physician.specialties ? physician.specialties.join(', ') : '-') + '</td>';
                                    message += '<td>' + (physician.languages ? physician.languages.join(', ') : '-') + '</td>';
                                    message += '<td>' + (physician.data.city || '') + ', ' + (physician.data.state || '') + '</td>';
                                    message += '<td>' + geoDisplay + '</td>';
                                    message += '</tr>';
                                }
                                
                                message += '</tbody></table>';
                                
                                if (data.preview_data.length > 10) {
                                    message += '<p><em>... and ' + (data.preview_data.length - 10) + ' more physicians</em></p>';
                                }
                                
                                message += '</div>';
                                
                                // Add "Proceed with Import" button if preview looks good
                                message += '<div style="margin-top: 20px;">';
                                message += '<button type="button" id="proceed-with-import" class="button button-primary">✅ Proceed with Actual Import</button>';
                                message += '<button type="button" id="cancel-import" class="button">❌ Cancel</button>';
                                message += '</div>';
                            }
                        } else {
                            message = '<div class="notice notice-success"><p><strong>✅ Import Completed!</strong><br>';
                            message += 'Imported: ' + data.imported + '<br>';
                            message += 'Updated: ' + data.updated + '<br>';
                            message += 'Skipped: ' + data.skipped;
                            
                            // Add pagination info if available
                            if (data.pagination) {
                                var pager = data.pagination;
                                if (pager.limited_to) {
                                    message += '<br>Limited to: ' + pager.limited_to + ' physicians';
                                } else if (pager.total_items) {
                                    message += '<br>Total processed: ' + pager.total_items + ' physicians';
                                }
                            }
                            
                            message += '</p></div>';
                        }
                        
                        if (data.errors && data.errors.length > 0) {
                            message += '<div class="notice notice-warning"><p><strong>⚠️ Warnings:</strong><br>';
                            message += data.errors.join('<br>') + '</p></div>';
                        }
                        
                        resultDiv.html(message);
                        
                        // Handle proceed with import button
                        $('#proceed-with-import').on('click', function() {
                            $(this).prop('disabled', true).text('Processing...');
                            performImport(false); // Actual import
                        });
                        
                        $('#cancel-import').on('click', function() {
                            resultDiv.empty();
                        });
                        
                    } else {
                        var errorMsg = isDryRun ? 'Preview failed: ' : 'Import failed: ';
                        resultDiv.html('<div class="notice notice-error"><p>' + errorMsg + response.data + '</p></div>');
                    }
                },
                error: function() {
                    var errorMsg = isDryRun ? 'Preview request failed' : 'Import request failed';
                    resultDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                    previewButton.prop('disabled', false);
                    progressDiv.hide();
                }
            });
        }
        
        // Sync Reference Data
        $('#sync-reference-data').on('click', function() {
            var button = $(this);
            var resultDiv = $('#sync-result');
            
            button.prop('disabled', true).text('Syncing...');
            resultDiv.html('<div class="spinner is-active"></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_sync_reference_data',
                    nonce: '<?php echo wp_create_nonce('fad_api_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-success"><p><strong>Sync completed!</strong><br>';
                        message += 'Languages synced: ' + data.languages_synced + '<br>';
                        message += 'Hospitals synced: ' + data.hospitals_synced + '</p></div>';
                        
                        if (data.errors && data.errors.length > 0) {
                            message += '<div class="notice notice-warning"><p><strong>Errors:</strong><br>';
                            message += data.errors.join('<br>') + '</p></div>';
                        }
                        
                        resultDiv.html(message);
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>Sync failed: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>Sync request failed</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Sync Reference Data');
                }
            });
        });
        
        // Check for Duplicates
        $('#check-duplicates').on('click', function() {
            var button = $(this);
            var resultDiv = $('#duplicate-results');
            
            button.prop('disabled', true).text('Checking...');
            resultDiv.html('<div class="spinner is-active"></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_check_duplicates',
                    nonce: '<?php echo wp_create_nonce('fad_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-info"><p><strong>📊 Duplicate Check Results</strong></p></div>';
                        
                        message += '<table class="widefat"><tbody>';
                        message += '<tr><td><strong>Total Doctors:</strong></td><td>' + data.total_doctors + '</td></tr>';
                        message += '<tr><td><strong>Potential Name Duplicates:</strong></td><td>' + data.potential_name_duplicates + '</td></tr>';
                        message += '<tr><td><strong>Missing Provider Keys:</strong></td><td>' + data.empty_provider_keys + '</td></tr>';
                        message += '<tr><td><strong>Missing IDME:</strong></td><td>' + data.empty_idme + '</td></tr>';
                        message += '<tr><td><strong>Duplicate Provider Keys:</strong></td><td>' + data.duplicate_provider_keys + '</td></tr>';
                        message += '</tbody></table>';
                        
                        if (data.duplicate_provider_keys > 0) {
                            message += '<div style="margin-top: 15px;"><button id="cleanup-duplicates-preview" class="button">Preview Cleanup</button></div>';
                        }
                        
                        resultDiv.html(message);
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>Duplicate check failed: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>Duplicate check request failed</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Check for Duplicates');
                }
            });
        });
        
        // Preview Duplicate Cleanup
        $(document).on('click', '#cleanup-duplicates-preview', function() {
            var button = $(this);
            var resultDiv = $('#duplicate-results');
            
            button.prop('disabled', true).text('Previewing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_cleanup_duplicates',
                    dry_run: 'true',
                    nonce: '<?php echo wp_create_nonce('fad_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-warning"><p><strong>🔍 Cleanup Preview (Dry Run)</strong></p></div>';
                        
                        message += '<p><strong>Summary:</strong> ' + data.kept + ' doctors would be kept, ' + data.removed + ' duplicates would be removed.</p>';
                        
                        if (data.actions.length > 0) {
                            message += '<h4>Actions Preview:</h4>';
                            message += '<table class="widefat"><thead><tr><th>Provider Key</th><th>Keep Doctor ID</th><th>Remove Doctor IDs</th></tr></thead><tbody>';
                            
                            for (var i = 0; i < Math.min(data.actions.length, 10); i++) {
                                var action = data.actions[i];
                                message += '<tr>';
                                message += '<td>' + action.provider_key + '</td>';
                                message += '<td>' + action.kept_doctor_id + '</td>';
                                message += '<td>' + action.removed_doctor_ids.join(', ') + '</td>';
                                message += '</tr>';
                            }
                            
                            message += '</tbody></table>';
                            
                            if (data.actions.length > 10) {
                                message += '<p><em>... and ' + (data.actions.length - 10) + ' more actions</em></p>';
                            }
                            
                            message += '<div style="margin-top: 15px;">';
                            message += '<button id="cleanup-duplicates-execute" class="button button-primary">✅ Execute Cleanup</button>';
                            message += '<button id="cancel-cleanup" class="button">❌ Cancel</button>';
                            message += '</div>';
                        }
                        
                        resultDiv.html(message);
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>Cleanup preview failed: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>Cleanup preview request failed</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Preview Cleanup');
                }
            });
        });
        
        // Execute Duplicate Cleanup
        $(document).on('click', '#cleanup-duplicates-execute', function() {
            var button = $(this);
            var resultDiv = $('#duplicate-results');
            
            if (!confirm('Are you sure you want to permanently remove duplicate records? This cannot be undone.')) {
                return;
            }
            
            button.prop('disabled', true).text('Cleaning up...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_cleanup_duplicates',
                    dry_run: 'false',
                    nonce: '<?php echo wp_create_nonce('fad_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-success"><p><strong>✅ Cleanup Completed!</strong></p></div>';
                        message += '<p>Kept ' + data.kept + ' doctors, removed ' + data.removed + ' duplicates.</p>';
                        resultDiv.html(message);
                    } else {
                        resultDiv.html('<div class="notice notice-error"><p>Cleanup failed: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error"><p>Cleanup request failed</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Execute Cleanup');
                }
            });
        });
        
        // Cancel Cleanup
        $(document).on('click', '#cancel-cleanup', function() {
            $('#duplicate-results').empty();
        });
    });
    </script>
    <?php
}