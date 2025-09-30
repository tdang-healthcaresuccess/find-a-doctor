<?php
/**
 * API AJAX Handlers for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-import.php';

// AJAX handler for testing API connection
add_action('wp_ajax_fad_test_api_connection', 'fad_handle_test_api_connection');

// AJAX handler for validating database schema
add_action('wp_ajax_fad_validate_schema', 'fad_handle_validate_schema');

// AJAX handler for fixing database schema
add_action('wp_ajax_fad_fix_schema', 'fad_handle_fix_schema');

// AJAX handler for syncing reference data
add_action('wp_ajax_fad_sync_reference_data', 'fad_handle_sync_reference_data');

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

// AJAX handler for deleting all physicians
add_action('wp_ajax_fad_delete_all_physicians', 'fad_handle_delete_all_physicians');

function fad_handle_delete_all_physicians() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        global $wpdb;
        
        // Get the table names
        $doctors_table = $wpdb->prefix . 'doctors';
        $doctor_specialties_table = $wpdb->prefix . 'doctor_specialties';
        $doctor_language_table = $wpdb->prefix . 'doctor_language';
        $doctor_zocdoc_table = $wpdb->prefix . 'doctor_zocdoc';
        
        // Get current count before deletion
        $count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$doctors_table}");
        
        if ($count_before == 0) {
            wp_send_json_success([
                'message' => 'No physicians found in database. Nothing to delete.',
                'deleted_count' => 0
            ]);
            return;
        }
        
        // Start transaction to ensure data integrity
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete from junction tables first (foreign key constraints)
            $wpdb->query("DELETE FROM {$doctor_specialties_table}");
            $wpdb->query("DELETE FROM {$doctor_language_table}");
            $wpdb->query("DELETE FROM {$doctor_zocdoc_table}");
            
            // Delete from main doctors table
            $result = $wpdb->query("DELETE FROM {$doctors_table}");
            
            if ($result === false) {
                throw new Exception('Failed to delete physicians from database');
            }
            
            // Commit the transaction
            $wpdb->query('COMMIT');
            
            wp_send_json_success([
                'message' => "Successfully deleted {$count_before} physicians and all related data from the database.",
                'deleted_count' => $count_before
            ]);
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Failed to delete physicians: ' . $e->getMessage());
    }
}

// AJAX handler for getting physician count
add_action('wp_ajax_fad_get_physician_count', 'fad_handle_get_physician_count');

function fad_handle_get_physician_count() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        global $wpdb;
        $doctors_table = $wpdb->prefix . 'doctors';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$doctors_table}");
        
        wp_send_json_success([
            'count' => (int)$count
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Failed to get physician count: ' . $e->getMessage());
    }
}

// AJAX handler for batch import
add_action('wp_ajax_fad_import_batch', 'fad_handle_import_batch');

function fad_handle_import_batch() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Get parameters
        $batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 50;
        $current_batch = isset($_POST['current_batch']) ? (int)$_POST['current_batch'] : 0;
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        
        // Get session data to check for limits
        $session_data = get_transient('fad_import_session_' . get_current_user_id());
        $user_limit = $session_data ? $session_data['user_limit'] : null;
        $import_all_pages = $session_data ? $session_data['import_all_pages'] : false;
        
        // Get search parameters from form fields or session
        $search_params = [];
        
        if (!empty($_POST['specialty'])) {
            $search_params['specialty'] = sanitize_text_field($_POST['specialty']);
        } elseif ($session_data && !empty($session_data['search_params']['specialty'])) {
            $search_params['specialty'] = $session_data['search_params']['specialty'];
        }
        
        if (!empty($_POST['location'])) {
            $search_params['location'] = sanitize_text_field($_POST['location']);
        } elseif ($session_data && !empty($session_data['search_params']['location'])) {
            $search_params['location'] = $session_data['search_params']['location'];
        }
        
        // Apply limit if not importing all pages
        if (!$import_all_pages && $user_limit) {
            $physicians_imported_so_far = $current_batch * $batch_size;
            $remaining_limit = $user_limit - $physicians_imported_so_far;
            
            if ($remaining_limit <= 0) {
                // We've reached the limit
                wp_send_json_success([
                    'success' => true,
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'message' => 'Import limit reached',
                    'is_complete' => true
                ]);
                return;
            }
            
            // Adjust batch size if needed to not exceed limit
            if ($remaining_limit < $batch_size) {
                $batch_size = $remaining_limit;
            }
        }
        
        // Process the batch
        $result = fad_import_doctors_batch($batch_size, $current_batch, $search_params, $dry_run);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['errors']);
        }
    } catch (Exception $e) {
        wp_send_json_error('Batch import failed: ' . $e->getMessage());
    }
}

// AJAX handler for starting batch import session
add_action('wp_ajax_fad_start_batch_import', 'fad_handle_start_batch_import');

function fad_handle_start_batch_import() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    try {
        // Sync reference data from dedicated endpoints before starting import
        error_log('Syncing reference data before import...');
        $languages_synced = fad_sync_languages();
        $hospitals_synced = fad_sync_hospitals();
        error_log("Reference data synced: {$languages_synced} languages, {$hospitals_synced} hospitals");
        
        // Clear any existing session
        $session_key = 'fad_import_session_' . get_current_user_id();
        delete_transient($session_key);
        
        // Also clear dry run session
        $dry_session_key = 'fad_dry_run_session_' . get_current_user_id();
        delete_transient($dry_session_key);
        
        // Get total count to estimate batches
        $search_params = [];
        
        if (!empty($_POST['specialty'])) {
            $search_params['specialty'] = sanitize_text_field($_POST['specialty']);
        }
        
        if (!empty($_POST['location'])) {
            $search_params['location'] = sanitize_text_field($_POST['location']);
        }
        
        // Check if user wants to import all pages or respect the limit
        $import_all_pages = !empty($_POST['import_all_pages']);
        $user_limit = !empty($_POST['limit']) ? (int)$_POST['limit'] : null;
        
        $total_response = FAD_API_Client::search_physicians($search_params, 0);
        
        if (is_wp_error($total_response)) {
            wp_send_json_error('Failed to get total physician count: ' . $total_response->get_error_message());
            return;
        }
        
        $api_total_physicians = isset($total_response['pager']['total_items']) 
            ? (int)$total_response['pager']['total_items'] 
            : 0;
            
        // Determine actual total based on user preference
        if ($import_all_pages) {
            $total_physicians = $api_total_physicians;
            $limit_message = "all {$api_total_physicians} physicians (import all pages enabled)";
        } elseif ($user_limit && $user_limit < $api_total_physicians) {
            $total_physicians = $user_limit;
            $limit_message = "{$user_limit} physicians (user limit applied)";
        } else {
            $total_physicians = $api_total_physicians;
            $limit_message = "all {$api_total_physicians} physicians (limit {$user_limit} is greater than available)";
        }
        
        // Store the effective limit in session for batch processing
        $session_data = [
            'user_limit' => $user_limit,
            'import_all_pages' => $import_all_pages,
            'total_physicians' => $total_physicians,
            'search_params' => $search_params
        ];
        set_transient('fad_import_session_' . get_current_user_id(), $session_data, 3600); // 1 hour
            
        $batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 50;
        $estimated_batches = ceil($total_physicians / $batch_size);
        
        wp_send_json_success([
            'total_physicians' => $total_physicians,
            'batch_size' => $batch_size,
            'estimated_batches' => $estimated_batches,
            'message' => "Ready to import {$limit_message} in {$estimated_batches} batches of {$batch_size}"
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Failed to start batch import: ' . $e->getMessage());
    }
}

/**
 * Handle database schema validation
 */
function fad_handle_validate_schema() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Missing nonce');
    }

    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
    }    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    try {
        $validation_results = fad_validate_database_schema();
        
        if ($validation_results['valid']) {
            wp_send_json_success([
                'message' => 'Database schema is valid and ready for import!',
                'details' => $validation_results
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Database schema validation failed',
                'details' => $validation_results
            ]);
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Schema validation failed: ' . $e->getMessage());
    }
}

/**
 * Handle database schema auto-fix
 */
function fad_handle_fix_schema() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Missing nonce');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    try {
        $fix_results = fad_fix_database_schema();
        
        if ($fix_results['success']) {
            wp_send_json_success([
                'message' => $fix_results['message'],
                'details' => $fix_results
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Failed to fix database schema',
                'details' => $fix_results
            ]);
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Schema fix failed: ' . $e->getMessage());
    }
}

/**
 * Handle reference data sync from API
 */
function fad_handle_sync_reference_data() {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Verify nonce
    if (!isset($_POST['nonce'])) {
        wp_send_json_error('Missing nonce');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'fad_api_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    try {
        $sync_results = fad_sync_reference_data();
        
        if ($sync_results['success']) {
            $message = 'Reference data synced successfully! ';
            $details = [];
            if ($sync_results['languages_synced'] > 0) {
                $details[] = $sync_results['languages_synced'] . ' languages';
            }
            if ($sync_results['hospitals_synced'] > 0) {
                $details[] = $sync_results['hospitals_synced'] . ' hospitals';
            }
            if ($sync_results['insurances_synced'] > 0) {
                $details[] = $sync_results['insurances_synced'] . ' insurances';
            }
            
            if (!empty($details)) {
                $message .= 'Added: ' . implode(', ', $details);
            } else {
                $message .= 'No new data to add.';
            }
            
            wp_send_json_success([
                'message' => $message,
                'details' => $sync_results
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Reference data sync failed',
                'details' => $sync_results
            ]);
        }
        
    } catch (Exception $e) {
        wp_send_json_error('Reference data sync failed: ' . $e->getMessage());
    }
}