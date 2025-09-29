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
 * @param bool $import_all_pages If true, import from all pages (ignore limit)
 * @return array Import results
 */
function fad_import_doctors_from_api($search_params = [], $dry_run = false, $import_all_pages = false) {
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
        if ($import_all_pages) {
            // Remove limit parameter when importing all pages and fetch all pages
            unset($search_params['limit']);
            $api_response = FAD_API_Client::search_all_physicians($search_params);
        } else {
            // For limited imports, we need to handle pagination manually to respect the limit
            $limit = isset($search_params['limit']) ? (int)$search_params['limit'] : 100;
            $physicians = [];
            $current_page = 0;
            $fetched_count = 0;
            
            // Remove limit from API params since we'll handle it ourselves
            $api_search_params = $search_params;
            unset($api_search_params['limit']);
            
            while ($fetched_count < $limit) {
                $page_response = FAD_API_Client::search_physicians($api_search_params, $current_page);
                
                if (is_wp_error($page_response)) {
                    $api_response = $page_response;
                    break;
                }
                
                if (!isset($page_response['rows']) || !is_array($page_response['rows'])) {
                    break;
                }
                
                $page_physicians = $page_response['rows'];
                
                // Only take what we need to reach the limit
                $remaining_needed = $limit - $fetched_count;
                if (count($page_physicians) > $remaining_needed) {
                    $page_physicians = array_slice($page_physicians, 0, $remaining_needed);
                }
                
                $physicians = array_merge($physicians, $page_physicians);
                $fetched_count += count($page_physicians);
                
                // Check if we've reached the limit or there are no more pages
                if ($fetched_count >= $limit || count($page_physicians) == 0) {
                    break;
                }
                
                // Check pagination info to see if there are more pages
                if (isset($page_response['pager'])) {
                    $pager = $page_response['pager'];
                    $total_pages = (int)$pager['total_pages'];
                    
                    if ($current_page >= $total_pages - 1) {
                        break; // No more pages
                    }
                }
                
                $current_page++;
            }
            
            // Create a response in the same format
            $api_response = [
                'rows' => $physicians,
                'pager' => [
                    'current_page' => 0,
                    'total_items' => count($physicians),
                    'total_pages' => 1,
                    'items_per_page' => count($physicians),
                    'limited_to' => $limit
                ]
            ];
        }
        
        if (is_wp_error($api_response)) {
            $results['errors'][] = 'API Error: ' . $api_response->get_error_message();
            return $results;
        }
        
        if (!isset($api_response['rows']) || !is_array($api_response['rows'])) {
            $results['errors'][] = 'Invalid API response format. Expected "rows" array not found.';
            
            // Add debug information about the actual response
            if (is_array($api_response)) {
                $results['errors'][] = 'API response keys: ' . implode(', ', array_keys($api_response));
                
                // Show first few characters of response for debugging
                $response_preview = json_encode($api_response);
                if (strlen($response_preview) > 500) {
                    $response_preview = substr($response_preview, 0, 500) . '... (truncated)';
                }
                $results['errors'][] = 'API response preview: ' . $response_preview;
            } else {
                $results['errors'][] = 'API response type: ' . gettype($api_response);
                $results['errors'][] = 'API response content: ' . print_r($api_response, true);
            }
            
            return $results;
        }
        
        $physicians = $api_response['rows'];
        
        // Add pagination information to results
        if (isset($api_response['pager'])) {
            $results['pagination'] = $api_response['pager'];
        }
        
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
    
    // Check if doctor exists by multiple methods (most robust duplicate detection)
    $existing_doctor_id = null;
    $duplicate_check_method = '';
    
    // Method 1: Check by provider_key (most reliable)
    if (!empty($doctor_data['atlas_primary_key'])) {
        $existing_doctor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT doctorID FROM {$wpdb->prefix}doctors WHERE atlas_primary_key = %s",
            $doctor_data['atlas_primary_key']
        ));
        if ($existing_doctor_id) {
            $duplicate_check_method = 'provider_key';
        }
    }
    
    // Method 2: Check by idme (fallback)
    if (!$existing_doctor_id && !empty($doctor_data['idme'])) {
        $existing_doctor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT doctorID FROM {$wpdb->prefix}doctors WHERE idme = %s",
            $doctor_data['idme']
        ));
        if ($existing_doctor_id) {
            $duplicate_check_method = 'idme';
        }
    }
    
    // Method 3: Check by name + degree combination (last resort)
    if (!$existing_doctor_id && !empty($doctor_data['first_name']) && !empty($doctor_data['last_name'])) {
        $existing_doctor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT doctorID FROM {$wpdb->prefix}doctors 
             WHERE first_name = %s AND last_name = %s AND degree = %s",
            $doctor_data['first_name'],
            $doctor_data['last_name'],
            $doctor_data['degree'] ?? ''
        ));
        if ($existing_doctor_id) {
            $duplicate_check_method = 'name_degree';
        }
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
            
            // Clear existing relationships before adding new ones
            $wpdb->delete(
                "{$wpdb->prefix}doctor_language",
                ['doctorID' => $existing_doctor_id]
            );
            $wpdb->delete(
                "{$wpdb->prefix}doctor_specialties", 
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
        'reason' => $action === 'updated' ? 
            'Doctor already exists (found by ' . $duplicate_check_method . '), will be updated' : 
            'New doctor will be added',
        'data' => $doctor_data,
        'languages' => [],
        'specialties' => [],
        'duplicate_check_method' => $duplicate_check_method ?? 'none'
    ];
    
    // Process languages from languages_spoken_by_doctor
    if (isset($physician_data['languages_spoken_by_doctor']) && is_array($physician_data['languages_spoken_by_doctor'])) {
        foreach ($physician_data['languages_spoken_by_doctor'] as $language_obj) {
            $lang_name = trim($language_obj['name'] ?? '');
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
        foreach ($physician_data['specialties'] as $specialty_obj) {
            $specialty_name = trim($specialty_obj['name'] ?? '');
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
    $mapped = [];
    
    // Direct field mappings based on actual API response
    $mapped['atlas_primary_key'] = $api_data['provider_key'] ?? '';
    $mapped['idme'] = $api_data['provider_key'] ?? ''; // Using provider_key as unique identifier
    $mapped['first_name'] = $api_data['first_name'] ?? '';
    $mapped['last_name'] = $api_data['last_name'] ?? '';
    $mapped['email'] = ''; // Not provided in API response
    $mapped['degree'] = $api_data['suffix'] ?? '';
    $mapped['biography'] = $api_data['about_me'] ?? '';
    $mapped['profile_img_url'] = $api_data['picture'] ?? '';
    
    // Extract gender from gender array
    if (isset($api_data['gender']) && is_array($api_data['gender']) && !empty($api_data['gender'])) {
        $mapped['gender'] = $api_data['gender'][0]['name'] ?? '';
    } else {
        $mapped['gender'] = '';
    }
    
    // Extract phone and address from location array (use first location)
    if (isset($api_data['location']) && is_array($api_data['location']) && !empty($api_data['location'])) {
        $location = $api_data['location'][0];
        $mapped['phone_number'] = $location['address_phonenumber'] ?? '';
        $mapped['fax_number'] = ''; // Not provided in API
        $mapped['address'] = $location['address_line1'] ?? '';
        $mapped['city'] = $location['locality'] ?? '';
        $mapped['state'] = $location['administrative_area'] ?? '';
        $mapped['zip'] = $location['postal_code'] ?? '';
        $mapped['county'] = ''; // Not provided in API
        $mapped['practice_name'] = $location['organization'] ?? '';
    } else {
        // Set empty values if no location data
        $mapped['phone_number'] = '';
        $mapped['fax_number'] = '';
        $mapped['address'] = '';
        $mapped['city'] = '';
        $mapped['state'] = '';
        $mapped['zip'] = '';
        $mapped['county'] = '';
        $mapped['practice_name'] = '';
    }
    
    // Medical school from education_training
    $mapped['medical_school'] = '';
    if (isset($api_data['education_training']) && is_array($api_data['education_training'])) {
        foreach ($api_data['education_training'] as $education) {
            if (strpos(strtolower($education), 'medical school') !== false) {
                $mapped['medical_school'] = trim(str_replace('||Medical School, ', '', $education));
                break;
            }
        }
    }
    
    // Education fields from education_training array
    $internship = [];
    $residency = [];
    $fellowship = [];
    
    if (isset($api_data['education_training']) && is_array($api_data['education_training'])) {
        foreach ($api_data['education_training'] as $education) {
            $education = trim($education);
            if (strpos(strtolower($education), 'internship') !== false) {
                $internship[] = str_replace('||Internship, ', '', $education);
            } elseif (strpos(strtolower($education), 'residency') !== false) {
                $residency[] = str_replace('||Residency, ', '', $education);
            } elseif (strpos(strtolower($education), 'fellowship') !== false) {
                $fellowship[] = str_replace('||Fellowship, ', '', $education);
            }
        }
    }
    
    $mapped['internship'] = implode(', ', $internship);
    $mapped['residency'] = implode(', ', $residency);
    $mapped['fellowship'] = implode(', ', $fellowship);
    
    // Board certifications
    $certifications = [];
    if (isset($api_data['board_certification']) && is_array($api_data['board_certification'])) {
        foreach ($api_data['board_certification'] as $cert) {
            // Extract certification name (after the last |)
            $parts = explode('|', $cert);
            if (count($parts) >= 3) {
                $certifications[] = end($parts);
            }
        }
    }
    $mapped['certification'] = implode(', ', $certifications);
    
    // Boolean fields
    $mapped['primary_care'] = 0; // Not clearly defined in API
    $mapped['is_ab_directory'] = 0; // Not provided in API
    $mapped['is_bt_directory'] = 0; // Not provided in API
    
    // Provider status (assume active if accepts new patients)
    $mapped['prov_status'] = ($api_data['accepts_new_patients'] ?? false) ? 'Active' : 'Inactive';
    
    // Network information
    $mapped['aco_active_networks'] = isset($api_data['aco_networks']) ? implode(', ', $api_data['aco_networks']) : '';
    $mapped['hmo_active_networks'] = isset($api_data['hmo_networks']) ? implode(', ', $api_data['hmo_networks']) : '';
    $mapped['ppo_active_network'] = isset($api_data['ppo_networks']) ? implode(', ', $api_data['ppo_networks']) : '';
    
    // Hospital affiliations
    $mapped['hospitalNames'] = isset($api_data['hospital_affiliations']) ? implode(', ', $api_data['hospital_affiliations']) : '';
    
    // Insurance networks (combine all network types)
    $all_networks = array_merge(
        $api_data['hmo_networks'] ?? [],
        $api_data['ppo_networks'] ?? [],
        $api_data['aco_networks'] ?? [],
        $api_data['acn_networks'] ?? [],
        $api_data['medi_cal_networks'] ?? []
    );
    $mapped['Insurances'] = implode(', ', array_unique($all_networks));
    
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
    
    // The languages endpoint might return different format, 
    // but we can also extract from the search results
    if (isset($api_response['rows']) && is_array($api_response['rows'])) {
        $all_languages = [];
        
        // Extract all unique languages from physician data
        foreach ($api_response['rows'] as $physician) {
            if (isset($physician['languages_spoken_by_doctor']) && is_array($physician['languages_spoken_by_doctor'])) {
                foreach ($physician['languages_spoken_by_doctor'] as $lang_obj) {
                    $lang_name = trim($lang_obj['name'] ?? '');
                    if (!empty($lang_name)) {
                        $all_languages[] = $lang_name;
                    }
                }
            }
        }
        
        // Remove duplicates and sync
        $unique_languages = array_unique($all_languages);
        
        foreach ($unique_languages as $language_name) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT languageID FROM {$wpdb->prefix}languages WHERE language = %s",
                $language_name
            ));
            
            if (!$existing) {
                $wpdb->insert("{$wpdb->prefix}languages", ['language' => $language_name]);
                $synced++;
            }
        }
    } elseif (isset($api_response['data']) && is_array($api_response['data'])) {
        // Handle if API returns direct language list
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
    // Extract unique hospital affiliations from physician data
    $all_hospitals = [];
    
    if (isset($api_response['rows']) && is_array($api_response['rows'])) {
        foreach ($api_response['rows'] as $physician) {
            if (isset($physician['hospital_affiliations']) && is_array($physician['hospital_affiliations'])) {
                $all_hospitals = array_merge($all_hospitals, $physician['hospital_affiliations']);
            }
        }
        
        return count(array_unique($all_hospitals));
    } elseif (isset($api_response['data']) && is_array($api_response['data'])) {
        return count($api_response['data']);
    }
    
    return 0;
}

/**
 * Check for potential duplicates in the database
 *
 * @return array Summary of potential duplicates
 */
function fad_check_potential_duplicates() {
    global $wpdb;
    
    $results = [
        'total_doctors' => 0,
        'potential_name_duplicates' => 0,
        'empty_provider_keys' => 0,
        'empty_idme' => 0,
        'duplicate_provider_keys' => 0,
        'duplicate_details' => []
    ];
    
    // Total doctors
    $results['total_doctors'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}doctors"
    );
    
    // Check for name-based duplicates
    $name_duplicates = $wpdb->get_results(
        "SELECT first_name, last_name, COUNT(*) as count 
         FROM {$wpdb->prefix}doctors 
         WHERE first_name != '' AND last_name != ''
         GROUP BY first_name, last_name 
         HAVING count > 1",
        ARRAY_A
    );
    $results['potential_name_duplicates'] = count($name_duplicates);
    
    // Check for empty provider keys
    $results['empty_provider_keys'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}doctors 
         WHERE atlas_primary_key IS NULL OR atlas_primary_key = ''"
    );
    
    // Check for empty idme
    $results['empty_idme'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}doctors 
         WHERE idme IS NULL OR idme = ''"
    );
    
    // Check for duplicate provider keys
    $provider_key_duplicates = $wpdb->get_results(
        "SELECT atlas_primary_key, COUNT(*) as count 
         FROM {$wpdb->prefix}doctors 
         WHERE atlas_primary_key != '' AND atlas_primary_key IS NOT NULL
         GROUP BY atlas_primary_key 
         HAVING count > 1",
        ARRAY_A
    );
    $results['duplicate_provider_keys'] = count($provider_key_duplicates);
    
    // Get detailed duplicate information
    if (!empty($name_duplicates)) {
        $results['duplicate_details']['name_duplicates'] = array_slice($name_duplicates, 0, 5); // First 5
    }
    
    if (!empty($provider_key_duplicates)) {
        $results['duplicate_details']['provider_key_duplicates'] = array_slice($provider_key_duplicates, 0, 5); // First 5
    }
    
    return $results;
}

/**
 * Clean up duplicate doctors based on provider_key priority
 *
 * @param bool $dry_run If true, only show what would be cleaned
 * @return array Cleanup results
 */
function fad_cleanup_duplicates($dry_run = true) {
    global $wpdb;
    
    $results = [
        'success' => false,
        'removed' => 0,
        'kept' => 0,
        'errors' => [],
        'dry_run' => $dry_run,
        'actions' => []
    ];
    
    try {
        // Find duplicates by provider_key (keep the most recent one)
        $duplicate_groups = $wpdb->get_results(
            "SELECT atlas_primary_key, GROUP_CONCAT(doctorID ORDER BY created_at DESC) as doctor_ids
             FROM {$wpdb->prefix}doctors 
             WHERE atlas_primary_key != '' AND atlas_primary_key IS NOT NULL
             GROUP BY atlas_primary_key 
             HAVING COUNT(*) > 1",
            ARRAY_A
        );
        
        foreach ($duplicate_groups as $group) {
            $doctor_ids = explode(',', $group['doctor_ids']);
            $keep_id = array_shift($doctor_ids); // Keep the first (most recent)
            $remove_ids = $doctor_ids; // Remove the rest
            
            $results['kept']++;
            $results['removed'] += count($remove_ids);
            
            $action = [
                'provider_key' => $group['atlas_primary_key'],
                'kept_doctor_id' => $keep_id,
                'removed_doctor_ids' => $remove_ids
            ];
            
            if (!$dry_run) {
                // Actually remove duplicates
                foreach ($remove_ids as $remove_id) {
                    // Delete relationships first
                    $wpdb->delete("{$wpdb->prefix}doctor_language", ['doctorID' => $remove_id]);
                    $wpdb->delete("{$wpdb->prefix}doctor_specialties", ['doctorID' => $remove_id]);
                    
                    // Delete doctor record
                    $wpdb->delete("{$wpdb->prefix}doctors", ['doctorID' => $remove_id]);
                }
            }
            
            $results['actions'][] = $action;
        }
        
        $results['success'] = true;
        
    } catch (Exception $e) {
        $results['errors'][] = 'Cleanup failed: ' . $e->getMessage();
    }
    
    return $results;
}