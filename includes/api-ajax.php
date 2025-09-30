<?php
/**
 * API AJAX Handlers for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-import.php';

// AJAX handler for testing API connection
add_action('wp_ajax_fad_test_api_connection', 'fad_handle_test_api_connection');

function fad_handle_test_api_connection() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('No nonce provided');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        $result = FAD_API_Client::test_connection();
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            
            // Provide helpful SSL-specific error messages
            if (strpos($error_message, 'SSL') !== false || strpos($error_message, 'certificate') !== false) {
                $error_message .= '<br><br><strong>SSL Certificate Issue Detected:</strong><br>';
                $error_message .= '• This is common in development environments<br>';
                $error_message .= '• The plugin has been configured to bypass SSL verification for testing<br>';
                $error_message .= '• If this persists, check your server\'s SSL configuration';
            }
            
            wp_send_json_error($error_message);
        } else {
            // Success - format the response nicely
            $message = 'Connection successful! ';
            if (is_array($result) && isset($result['rows'])) {
                $count = count($result['rows']);
                $message .= "Retrieved {$count} sample physicians from API.";
            } else {
                $message .= 'API responded correctly.';
            }
            
            wp_send_json_success($message);
        }
    } catch (Exception $e) {
        wp_send_json_error('Exception: ' . $e->getMessage());
    }
}

// AJAX handler for importing data from API
add_action('wp_ajax_fad_import_from_api', 'fad_handle_import_from_api');

function fad_handle_import_from_api() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Get search parameters from form fields
        $search_params = [];
        
        if (!empty($_POST['specialty'])) {
            $search_params['specialty'] = sanitize_text_field($_POST['specialty']);
        }
        
        if (!empty($_POST['location'])) {
            $search_params['location'] = sanitize_text_field($_POST['location']);
        }
        
        if (!empty($_POST['limit'])) {
            $search_params['limit'] = (int)$_POST['limit'];
        }
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        $import_all_pages = isset($_POST['import_all_pages']) && $_POST['import_all_pages'] === '1';
        
        // Import doctors from API
        $result = fad_import_doctors_from_api($search_params, $dry_run, $import_all_pages);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['errors']);
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

// AJAX handler for syncing reference data
add_action('wp_ajax_fad_sync_reference_data', 'fad_handle_sync_reference_data');

function fad_handle_sync_reference_data() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        $result = fad_sync_reference_data();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['errors']);
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

// AJAX handler for getting individual physician data
add_action('wp_ajax_fad_get_physician_from_api', 'fad_handle_get_physician_from_api');

function fad_handle_get_physician_from_api() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $physician_id = $_POST['physician_id'] ?? '';
    
    if (empty($physician_id)) {
        wp_send_json_error('Physician ID is required');
        return;
    }
    
    try {
        $result = FAD_API_Client::get_physician($physician_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

// AJAX handler for admin page API test
add_action('wp_ajax_fad_admin_test_connection', 'fad_handle_admin_test_connection');

function fad_handle_admin_test_connection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_admin_test')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Run detailed connection test
        $results = FAD_API_Client::detailed_connection_test();
        
        if ($results['success']) {
            $message = 'Connection successful! ✅<br><br>';
            $message .= '<strong>Test Results:</strong><br>';
            
            foreach ($results['tests'] as $test) {
                $status = $test['success'] ? '✅' : '❌';
                $message .= $status . ' ' . ucfirst($test['endpoint']) . ' endpoint';
                if (!$test['success'] && $test['error']) {
                    $message .= ' - Error: ' . esc_html($test['error']);
                }
                $message .= '<br>';
            }
            
            wp_send_json_success($message);
        } else {
            $error_message = 'Connection failed ❌<br><br>';
            $error_message .= '<strong>Debug Information:</strong><br>';
            $error_message .= 'PHP Version: ' . $results['debug_info']['php_version'] . '<br>';
            $error_message .= 'cURL Available: ' . ($results['debug_info']['curl_available'] ? 'Yes' : 'No') . '<br>';
            $error_message .= 'OpenSSL Available: ' . ($results['debug_info']['openssl_available'] ? 'Yes' : 'No') . '<br>';
            
            if (isset($results['debug_info']['ssl_version'])) {
                $error_message .= 'SSL Version: ' . $results['debug_info']['ssl_version'] . '<br>';
            }
            
            $error_message .= '<br><strong>Test Results:</strong><br>';
            
            foreach ($results['tests'] as $test) {
                $status = $test['success'] ? '✅' : '❌';
                $error_message .= $status . ' ' . ucfirst($test['endpoint']) . ' endpoint';
                if (!$test['success'] && $test['error']) {
                    $error_message .= '<br>&nbsp;&nbsp;&nbsp;&nbsp;Error: ' . esc_html($test['error']);
                }
                if ($test['response_code']) {
                    $error_message .= '<br>&nbsp;&nbsp;&nbsp;&nbsp;HTTP Status: ' . $test['response_code'];
                }
                $error_message .= '<br>';
            }
            
            wp_send_json_error($error_message);
        }
    } catch (Exception $e) {
        wp_send_json_error('Connection test failed: ' . $e->getMessage());
    }
}

// AJAX handler for checking duplicates
add_action('wp_ajax_fad_check_duplicates', 'fad_handle_check_duplicates');

function fad_handle_check_duplicates() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_admin_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        $results = fad_check_potential_duplicates();
        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error('Duplicate check failed: ' . $e->getMessage());
    }
}

// AJAX handler for cleaning up duplicates
add_action('wp_ajax_fad_cleanup_duplicates', 'fad_handle_cleanup_duplicates');

function fad_handle_cleanup_duplicates() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'fad_admin_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        $results = fad_cleanup_duplicates($dry_run);
        wp_send_json_success($results);
    } catch (Exception $e) {
        wp_send_json_error('Cleanup failed: ' . $e->getMessage());
    }
}