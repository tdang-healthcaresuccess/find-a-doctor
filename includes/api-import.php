<?php
/**
 * API-based data import for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-client.php';

/**
 * Import doctors from API
 *
 * @param array $search_params Optional search parameters to filter physicians
 * @param bool $dry_run If true, only preview data without importing
 * @return array Import results
 */
function fad_import_doctors_from_api($search_params = [], $dry_run = false) {
    global $wpdb;
    
    $results = [
        'success' => false,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
        'message' => '',
        'dry_run' => $dry_run,
        'preview_data' => []
    ];
    
    try {
        // Get physicians from API
        $api_response = FAD_API_Client::search_physicians($search_params);
        
        if (is_wp_error($api_response)) {
            $results['errors'][] = 'API Error: ' . $api_response->get_error_message();
            return $results;
        }
        
        if (!isset($api_response['data']) || !is_array($api_response['data'])) {
            $results['errors'][] = 'Invalid API response format';
            return $results;
        }
        
        $physicians = $api_response['data'];
        
        // Cache for lookups
        $language_cache = [];
        $specialty_cache = [];
        $insert_language_relations = [];
        $insert_specialty_relations = [];
        
        foreach ($physicians as $physician_data) {
            try {
                $result = fad_process_physician_data($physician_data, $language_cache, $specialty_cache, $insert_language_relations, $insert_specialty_relations, $dry_run);
                
                if ($dry_run) {
                    // In dry run mode, collect preview data
                    $results['preview_data'][] = $result['preview'];
                }
                
                if ($result['action'] === 'imported') {
                    $results['imported']++;
                } elseif ($result['action'] === 'updated') {
                    $results['updated']++;
                } else {
                    $results['skipped']++;
                }
                
            } catch (Exception $e) {
                $results['errors'][] = 'Error processing physician: ' . $e->getMessage();
            }
        }
        
        if (!$dry_run) {
            // Only perform actual database operations if not in dry run mode
            // Batch insert relationships
            if (!empty($insert_language_relations)) {
                $values_sql = implode(',', $insert_language_relations);
                $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}doctor_language (doctorID, languageID) VALUES $values_sql");
            }
            
            if (!empty($insert_specialty_relations)) {
                $values_sql = implode(',', $insert_specialty_relations);
                $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}doctor_specialties (doctorID, specialtyID) VALUES $values_sql");
            }
        }
        
        $results['success'] = true;
        
        if ($dry_run) {
            $results['message'] = sprintf(
                'Dry Run Preview: %d would be imported, %d would be updated, %d would be skipped (Total: %d physicians found)',
                $results['imported'],
                $results['updated'],
                $results['skipped'],
                count($physicians)
            );
        } else {
            $results['message'] = sprintf(
                'Import completed: %d imported, %d updated, %d skipped',
                $results['imported'],
                $results['updated'],
                $results['skipped']
            );
        }
        
    } catch (Exception $e) {
        $results['errors'][] = 'Import failed: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Process individual physician data from API
 *
 * @param array $physician_data Raw physician data from API
 * @param array &$language_cache Reference to language cache
 * @param array &$specialty_cache Reference to specialty cache
 * @param array &$insert_language_relations Reference to language relations
 * @param array &$insert_specialty_relations Reference to specialty relations
 * @param bool $dry_run If true, only preview without database changes
 * @return array Processing result
 */
function fad_process_physician_data($physician_data, &$language_cache, &$specialty_cache, &$insert_language_relations, &$insert_specialty_relations, $dry_run = false) {
    global $wpdb;
    
    // Map API fields to database fields
    $doctor_data = fad_map_api_to_db_fields($physician_data);
    
    // Skip if terminated
    if (isset($doctor_data['prov_status']) && strtolower(trim($doctor_data['prov_status'])) === 'terminated') {
        return [
            'action' => 'skipped', 
            'reason' => 'terminated',
            'preview' => [
                'name' => ($doctor_data['first_name'] ?? '') . ' ' . ($doctor_data['last_name'] ?? ''),
                'action' => 'skipped',
                'reason' => 'Provider status is terminated',
                'data' => $doctor_data
            ]
        ];
    }
    
    // Check required fields
    $required_fields = ['address', 'city', 'state', 'zip'];
    foreach ($required_fields as $field) {
        if (empty($doctor_data[$field])) {
            return [
                'action' => 'skipped', 
                'reason' => 'missing_required_field_' . $field,
                'preview' => [
                    'name' => ($doctor_data['first_name'] ?? '') . ' ' . ($doctor_data['last_name'] ?? ''),
                    'action' => 'skipped',
                    'reason' => 'Missing required field: ' . $field,
                    'data' => $doctor_data
                ]
            ];
        }
    }
    
    // Check if doctor exists by external ID or unique identifier
    $existing_doctor_id = null;
    if (!empty($doctor_data['idme'])) {
        $existing_doctor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT doctorID FROM {$wpdb->prefix}doctors WHERE idme = %s",
            $doctor_data['idme']
        ));
    }
    
    $doctor_id = null;
    $action = 'imported';
    
    if ($existing_doctor_id) {
        $action = 'updated';
        $doctor_id = $existing_doctor_id;
        
        if (!$dry_run) {
            // Update existing doctor
            $wpdb->update(
                "{$wpdb->prefix}doctors",
                $doctor_data,
                ['doctorID' => $existing_doctor_id]
            );
        }
    } else {
        $action = 'imported';
        
        if (!$dry_run) {
            // Insert new doctor
            $wpdb->insert("{$wpdb->prefix}doctors", $doctor_data);
            $doctor_id = $wpdb->insert_id;
            
            // Generate slug for new doctor
            fad_upsert_slug_for_doctor($doctor_id, true);
        } else {
            // For dry run, use a fake ID for preview
            $doctor_id = 'NEW_DOCTOR_' . uniqid();
        }
    }
    
    // Prepare preview data
    $preview_data = [
        'name' => ($doctor_data['first_name'] ?? '') . ' ' . ($doctor_data['last_name'] ?? ''),
        'action' => $action,
        'reason' => $action === 'updated' ? 'Doctor already exists, will be updated' : 'New doctor will be added',
        'data' => $doctor_data,
        'languages' => [],
        'specialties' => []
    ];
    
    // Process languages
    if (isset($physician_data['languages']) && is_array($physician_data['languages'])) {
        foreach ($physician_data['languages'] as $language) {
            $lang_name = trim($language);
            if (empty($lang_name)) continue;
            
            $preview_data['languages'][] = $lang_name;
            
            if (!$dry_run) {
                if (!isset($language_cache[$lang_name])) {
                    $language_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT languageID FROM {$wpdb->prefix}languages WHERE language = %s",
                        $lang_name
                    ));
                    
                    if (!$language_id) {
                        $wpdb->insert("{$wpdb->prefix}languages", ['language' => $lang_name]);
                        $language_id = $wpdb->insert_id;
                    }
                    
                    $language_cache[$lang_name] = $language_id;
                }
                
                $insert_language_relations[] = $wpdb->prepare("(%d, %d)", $doctor_id, $language_cache[$lang_name]);
            }
        }
    }
    
    // Process specialties
    if (isset($physician_data['specialties']) && is_array($physician_data['specialties'])) {
        foreach ($physician_data['specialties'] as $specialty) {
            $specialty_name = trim($specialty);
            if (empty($specialty_name)) continue;
            
            $preview_data['specialties'][] = $specialty_name;
            
            if (!$dry_run) {
                if (!isset($specialty_cache[$specialty_name])) {
                    $specialty_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT specialtyID FROM {$wpdb->prefix}specialties WHERE specialty_name = %s",
                        $specialty_name
                    ));
                    
                    if (!$specialty_id) {
                        $wpdb->insert("{$wpdb->prefix}specialties", ['specialty_name' => $specialty_name]);
                        $specialty_id = $wpdb->insert_id;
                    }
                    
                    $specialty_cache[$specialty_name] = $specialty_id;
                }
                
                $insert_specialty_relations[] = $wpdb->prepare("(%d, %d)", $doctor_id, $specialty_cache[$specialty_name]);
            }
        }
    }
    
    return ['action' => $action, 'doctor_id' => $doctor_id, 'preview' => $preview_data];
}

/**
 * Map API response fields to database fields
 *
 * @param array $api_data Raw API data
 * @return array Mapped database fields
 */
function fad_map_api_to_db_fields($api_data) {
    // This mapping will need to be adjusted based on the actual API response structure
    // The following is a template that should be customized based on the real API response
    
    $mapped = [];
    
    // Direct field mappings (adjust field names based on actual API response)
    $field_mappings = [
        'atlas_primary_key' => 'provider_key',
        'idme' => 'id',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
        'phone_number' => 'phone',
        'fax_number' => 'fax',
        'degree' => 'degree',
        'gender' => 'gender',
        'medical_school' => 'medical_school',
        'practice_name' => 'practice_name',
        'prov_status' => 'status',
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip_code',
        'county' => 'county',
        'biography' => 'biography',
        'profile_img_url' => 'profile_image'
    ];
    
    foreach ($field_mappings as $db_field => $api_field) {
        if (isset($api_data[$api_field])) {
            $mapped[$db_field] = $api_data[$api_field];
        }
    }
    
    // Boolean field mappings
    $boolean_fields = ['primary_care', 'is_ab_directory', 'is_bt_directory'];
    foreach ($boolean_fields as $field) {
        if (isset($api_data[$field])) {
            $value = $api_data[$field];
            if (is_bool($value)) {
                $mapped[$field] = $value ? 1 : 0;
            } elseif (is_string($value)) {
                $mapped[$field] = (strtolower($value) === 'yes' || strtolower($value) === 'true') ? 1 : 0;
            }
        }
    }
    
    // Complex field mappings (arrays converted to comma-separated strings)
    $array_fields = ['internship', 'certification', 'residency', 'fellowship', 'Insurances', 'hospitalNames'];
    foreach ($array_fields as $field) {
        if (isset($api_data[$field]) && is_array($api_data[$field])) {
            $mapped[$field] = implode(', ', array_filter($api_data[$field]));
        }
    }
    
    // Network fields
    if (isset($api_data['networks']) && is_array($api_data['networks'])) {
        $networks = $api_data['networks'];
        $mapped['aco_active_networks'] = isset($networks['aco']) ? implode(', ', $networks['aco']) : '';
        $mapped['hmo_active_networks'] = isset($networks['hmo']) ? implode(', ', $networks['hmo']) : '';
        $mapped['ppo_active_network'] = isset($networks['ppo']) ? implode(', ', $networks['ppo']) : '';
    }
    
    return $mapped;
}

/**
 * Sync reference data from API
 *
 * @return array Sync results
 */
function fad_sync_reference_data() {
    $results = [
        'success' => false,
        'languages_synced' => 0,
        'hospitals_synced' => 0,
        'errors' => []
    ];
    
    try {
        // Sync languages
        $languages_response = FAD_API_Client::get_languages();
        if (!is_wp_error($languages_response)) {
            $results['languages_synced'] = fad_sync_languages($languages_response);
        } else {
            $results['errors'][] = 'Languages sync failed: ' . $languages_response->get_error_message();
        }
        
        // Sync hospitals
        $hospitals_response = FAD_API_Client::get_hospitals();
        if (!is_wp_error($hospitals_response)) {
            $results['hospitals_synced'] = fad_sync_hospitals($hospitals_response);
        } else {
            $results['errors'][] = 'Hospitals sync failed: ' . $hospitals_response->get_error_message();
        }
        
        $results['success'] = empty($results['errors']);
        
    } catch (Exception $e) {
        $results['errors'][] = 'Sync failed: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Sync languages from API response
 *
 * @param array $api_response
 * @return int Number of languages synced
 */
function fad_sync_languages($api_response) {
    global $wpdb;
    
    $synced = 0;
    
    if (isset($api_response['data']) && is_array($api_response['data'])) {
        foreach ($api_response['data'] as $language_data) {
            $language_name = trim($language_data['name'] ?? $language_data['language'] ?? '');
            
            if (empty($language_name)) continue;
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT languageID FROM {$wpdb->prefix}languages WHERE language = %s",
                $language_name
            ));
            
            if (!$existing) {
                $wpdb->insert("{$wpdb->prefix}languages", ['language' => $language_name]);
                $synced++;
            }
        }
    }
    
    return $synced;
}

/**
 * Sync hospitals from API response
 *
 * @param array $api_response
 * @return int Number of hospitals processed
 */
function fad_sync_hospitals($api_response) {
    // This would need a hospitals table or could be stored as reference data
    // For now, we'll just return count of hospitals in response
    
    if (isset($api_response['data']) && is_array($api_response['data'])) {
        return count($api_response['data']);
    }
    
    return 0;
}