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
        
        <!-- Import Physicians -->
        <div class="card">
            <h2>Import Physicians</h2>
            <p>Import physician data from the external API.</p>
            
            <form id="api-import-form">
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
                            <small>Maximum number of physicians to import (1-1000)</small>
                            <br><br>
                            
                            <label>
                                <input type="checkbox" id="dry-run-mode" name="dry_run" value="1" />
                                <strong>Dry Run Mode</strong> - Preview what will be imported without making changes
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" id="start-import" class="button-primary">Start Import</button>
                    <button type="button" id="preview-import" class="button">Preview Import (Dry Run)</button>
                </p>
            </form>
            
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
                    nonce: '<?php echo wp_create_nonce('fad_api_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var message = '';
                        
                        if (isDryRun) {
                            message = '<div class="notice notice-info"><p><strong>📋 Preview Results (Dry Run)</strong><br>';
                            message += data.message + '</p></div>';
                            
                            // Show detailed preview data
                            if (data.preview_data && data.preview_data.length > 0) {
                                message += '<div class="card"><h3>Preview Data (First 10 physicians)</h3>';
                                message += '<table class="widefat"><thead><tr>';
                                message += '<th>Name</th><th>Action</th><th>Specialties</th><th>Languages</th><th>City, State</th>';
                                message += '</tr></thead><tbody>';
                                
                                var previewLimit = Math.min(data.preview_data.length, 10);
                                for (var i = 0; i < previewLimit; i++) {
                                    var physician = data.preview_data[i];
                                    var actionClass = physician.action === 'imported' ? 'success' : 
                                                    physician.action === 'updated' ? 'warning' : 'error';
                                    
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
                            message += 'Skipped: ' + data.skipped + '</p></div>';
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
    });
    </script>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        margin: 20px 0;
        padding: 20px;
    }
    
    .card h2 {
        margin-top: 0;
    }
    
    #import-progress .spinner {
        float: none;
        margin: 0 10px 0 0;
    }
    
    .widefat th,
    .widefat td {
        padding: 10px;
    }
    
    .form-table th {
        width: 200px;
    }
    
    .form-table input[type="text"],
    .form-table input[type="number"] {
        width: 300px;
    }
    
    .badge {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .badge-success {
        background-color: #46b450;
        color: white;
    }
    
    .badge-warning {
        background-color: #ffb900;
        color: white;
    }
    
    .badge-error {
        background-color: #dc3232;
        color: white;
    }
    
    #dry-run-mode {
        margin-right: 8px;
    }
    
    .preview-table {
        margin-top: 15px;
    }
    
    .preview-table th,
    .preview-table td {
        padding: 8px 12px;
        text-align: left;
    }
    
    .preview-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    </style>
    <?php
}