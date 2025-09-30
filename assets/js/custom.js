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
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
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
});




