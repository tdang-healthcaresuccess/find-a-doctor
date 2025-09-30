<?php
/**
 * API-based data import for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-client.php';

/**
 * Import doctors from API in batches to prevent timeouts
 *
 * @param int $batch_size Number of physicians to process per batch
 * @param int $current_batch Current batch number (0-based)
 * @param array $search_params Optional search parameters to filter physicians
 * @param bool $dry_run If true, only preview data without importing
 * @return array Batch import results
 */
function fad_import_doctors_batch($batch_size = 50, $current_batch = 0, $search_params = [], $dry_run = false) {
    global $wpdb;
    
    // Get session data to check for user limits
    $session_key = 'fad_import_session_' . get_current_user_id();
    $session_data = get_transient($session_key);
    $user_limit = $session_data ? ($session_data['user_limit'] ?? null) : null;
    $import_all_pages = $session_data ? ($session_data['import_all_pages'] ?? false) : false;
    $session_total_physicians = $session_data ? ($session_data['total_physicians'] ?? 0) : 0;
    
    // Debug session data
    error_log("Batch {$current_batch}: Session data available: " . ($session_data ? 'yes' : 'no') . ", user_limit: " . ($user_limit ?? 'null') . ", import_all_pages: " . ($import_all_pages ? 'true' : 'false') . ", total_physicians: " . $session_total_physicians);
    
    $results = [
        'success' => false,
        'batch_imported' => 0,
        'batch_updated' => 0,
        'batch_skipped' => 0,
        'total_imported' => 0,
        'total_updated' => 0,
        'total_skipped' => 0,
        'current_batch' => $current_batch,
        'has_more_batches' => false,
        'total_physicians' => $session_total_physicians,
        'processed_so_far' => 0,
        'errors' => [],
        'message' => '',
        'dry_run' => $dry_run,
        'preview_data' => [],
        'is_complete' => false
    ];
    
    try {
        // Check if we've already reached the user's limit
        if (!$import_all_pages && $user_limit) {
            $physicians_processed_so_far = $current_batch * $batch_size;
            if ($physicians_processed_so_far >= $user_limit) {
                $results['success'] = true;
                $results['is_complete'] = true;
                $results['message'] = "Import limit of {$user_limit} physicians reached";
                $results['processed_so_far'] = $user_limit;
                return $results;
            }
            
            // Adjust batch size if we're close to the limit
            $remaining_limit = $user_limit - $physicians_processed_so_far;
            if ($remaining_limit < $batch_size) {
                $batch_size = $remaining_limit;
            }
        }
        
        // First, get total count if this is the first batch
        if ($current_batch === 0) {
            $total_response = FAD_API_Client::search_physicians($search_params, 0);
            if (is_wp_error($total_response)) {
                $results['errors'][] = $total_response->get_error_message();
                return $results;
            }
            
            if (isset($total_response['pager']['total_items'])) {
                $api_total = (int)$total_response['pager']['total_items'];
                
                // Update results with effective total (respecting user limit)
                if (!$import_all_pages && $user_limit && $user_limit < $api_total) {
                    $results['total_physicians'] = $user_limit;
                } else {
                    $results['total_physicians'] = $api_total;
                }
            }
        }
        
        // Calculate which API page to fetch based on batch
        $api_page = floor(($current_batch * $batch_size) / 12); // API returns 12 items per page
        $offset_in_page = ($current_batch * $batch_size) % 12;
        
        // Get the specific page from API
        $api_response = FAD_API_Client::search_physicians($search_params, $api_page);
        
        if (is_wp_error($api_response)) {
            $results['errors'][] = $api_response->get_error_message();
            return $results;
        }
        
        if (!isset($api_response['rows']) || !is_array($api_response['rows'])) {
            $results['errors'][] = 'No physicians data returned from API';
            return $results;
        }
        
        $all_physicians = $api_response['rows'];
        
        // If we need more physicians for this batch, get additional pages
        $needed_physicians = $batch_size;
        $collected_physicians = [];
        
        // Collect physicians for this batch
        while (count($collected_physicians) < $needed_physicians && !empty($all_physicians)) {
            $physicians_to_take = min($needed_physicians - count($collected_physicians), count($all_physicians));
            $batch_physicians = array_slice($all_physicians, $offset_in_page, $physicians_to_take);
            $collected_physicians = array_merge($collected_physicians, $batch_physicians);
            
            // Reset offset for subsequent pages
            $offset_in_page = 0;
            
            // Get next page if needed
            if (count($collected_physicians) < $needed_physicians) {
                $api_page++;
                $next_response = FAD_API_Client::search_physicians($search_params, $api_page);
                
                if (is_wp_error($next_response) || !isset($next_response['rows'])) {
                    break;
                }
                
                $all_physicians = $next_response['rows'];
            } else {
                break;
            }
        }
        
        // Check if there will be more batches
        $results['processed_so_far'] = ($current_batch * $batch_size) + count($collected_physicians);
        
        // Determine if there are more batches based on limit settings
        if (!$import_all_pages && $user_limit) {
            $results['has_more_batches'] = $results['processed_so_far'] < $user_limit && $results['processed_so_far'] < $results['total_physicians'];
            
            // Check if we've reached the limit
            if ($results['processed_so_far'] >= $user_limit) {
                $results['is_complete'] = true;
            }
        } else {
            $results['has_more_batches'] = $results['processed_so_far'] < $results['total_physicians'];
        }
        
        // Debug logging
        error_log("Batch {$current_batch}: processed_so_far={$results['processed_so_far']}, total_physicians={$results['total_physicians']}, user_limit={$user_limit}, import_all_pages=" . ($import_all_pages ? 'true' : 'false') . ", has_more_batches=" . ($results['has_more_batches'] ? 'true' : 'false') . ", is_complete=" . ($results['is_complete'] ? 'true' : 'false'));
        
        if (empty($collected_physicians)) {
            $results['success'] = true;
            $results['message'] = 'No more physicians to process';
            return $results;
        }
        
        // Process this batch
        $batch_results = fad_process_physician_batch($collected_physicians, $dry_run);
        
        $results['batch_imported'] = $batch_results['imported'];
        $results['batch_updated'] = $batch_results['updated'];
        $results['batch_skipped'] = $batch_results['skipped'];
        $results['errors'] = array_merge($results['errors'], $batch_results['errors']);
        $results['preview_data'] = $batch_results['preview_data'];
        
        // Get cumulative totals (for both dry run and actual import)
        if (!$dry_run) {
            $session_key = 'fad_import_session_' . get_current_user_id();
            $session_data = get_transient($session_key);
            
            if ($session_data) {
                $results['total_imported'] = ($session_data['total_imported'] ?? 0) + $results['batch_imported'];
                $results['total_updated'] = ($session_data['total_updated'] ?? 0) + $results['batch_updated'];
                $results['total_skipped'] = ($session_data['total_skipped'] ?? 0) + $results['batch_skipped'];
            } else {
                $results['total_imported'] = $results['batch_imported'];
                $results['total_updated'] = $results['batch_updated'];
                $results['total_skipped'] = $results['batch_skipped'];
            }
            
            // Update session data
            set_transient($session_key, [
                'user_limit' => $user_limit,
                'import_all_pages' => $import_all_pages,
                'total_physicians' => $session_total_physicians,
                'search_params' => $search_params ?? [],
                'total_imported' => $results['total_imported'],
                'total_updated' => $results['total_updated'],
                'total_skipped' => $results['total_skipped'],
                'current_batch' => $current_batch + 1
            ], HOUR_IN_SECONDS);
        } else {
            // For dry runs, track cumulative totals in a separate session key
            $dry_session_key = 'fad_dry_run_session_' . get_current_user_id();
            $dry_session_data = get_transient($dry_session_key);
            
            if ($dry_session_data) {
                $results['total_imported'] = $dry_session_data['total_imported'] + $results['batch_imported'];
                $results['total_updated'] = $dry_session_data['total_updated'] + $results['batch_updated'];
                $results['total_skipped'] = $dry_session_data['total_skipped'] + $results['batch_skipped'];
            } else {
                $results['total_imported'] = $results['batch_imported'];
                $results['total_updated'] = $results['batch_updated'];
                $results['total_skipped'] = $results['batch_skipped'];
            }
            
            // Update dry run session data
            set_transient($dry_session_key, [
                'total_imported' => $results['total_imported'],
                'total_updated' => $results['total_updated'],
                'total_skipped' => $results['total_skipped'],
                'current_batch' => $current_batch + 1
            ], HOUR_IN_SECONDS);
        }
        
        $results['success'] = true;
        
        if ($dry_run) {
            $results['message'] = sprintf(
                'Batch %d preview: Would process %d physicians',
                $current_batch + 1,
                count($collected_physicians)
            );
        } else {
            $results['message'] = sprintf(
                'Batch %d complete: Imported %d, Updated %d, Skipped %d physicians',
                $current_batch + 1,
                $results['batch_imported'],
                $results['batch_updated'], 
                $results['batch_skipped']
            );
        }
        
        return $results;
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
        return $results;
    }
}

/**
 * Process a batch of physicians
 *
 * @param array $physicians Array of physician data
 * @param bool $dry_run If true, only preview data without importing
 * @return array Processing results
 */
function fad_process_physician_batch($physicians, $dry_run = false) {
    $results = [
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
        'preview_data' => []
    ];
    
    foreach ($physicians as $physician_data) {
        try {
            if ($dry_run) {
                // For dry run, just collect preview data
                $results['preview_data'][] = [
                    'full_name' => $physician_data['full_name'] ?? 'Unknown',
                    'specialty' => !empty($physician_data['specialties']) 
                        ? $physician_data['specialties'][0]['name'] 
                        : 'Unknown',
                    'location' => !empty($physician_data['location']) 
                        ? $physician_data['location'][0]['locality'] . ', ' . $physician_data['location'][0]['administrative_area']
                        : 'Unknown'
                ];
                $results['imported']++; // Count as "would import"
            } else {
                // Actually process the physician
                $result = fad_import_single_physician($physician_data);
                
                if ($result['success']) {
                    if ($result['action'] === 'inserted') {
                        $results['imported']++;
                    } else {
                        $results['updated']++;
                    }
                } else {
                    $results['skipped']++;
                    $results['errors'][] = $result['error'];
                    error_log("FAD Import Error - Physician skipped: " . $result['error'] . " | Data: " . json_encode($physician_data));
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = 'Error processing physician: ' . $e->getMessage();
            $results['skipped']++;
        }
    }
    
    return $results;
}

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
        $hospital_cache = [];
        $insert_language_relations = [];
        $insert_specialty_relations = [];
        $insert_hospital_relations = [];
        
        foreach ($physicians as $physician_data) {
            try {
                $result = fad_process_physician_data($physician_data, $language_cache, $specialty_cache, $hospital_cache, $insert_language_relations, $insert_specialty_relations, $insert_hospital_relations, $dry_run);
                
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
            
            if (!empty($insert_hospital_relations)) {
                $values_sql = implode(',', $insert_hospital_relations);
                $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}doctor_hospital (doctorID, hospitalID) VALUES $values_sql");
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
 * @param array &$hospital_cache Reference to hospital cache
 * @param array &$insert_language_relations Reference to language relations
 * @param array &$insert_specialty_relations Reference to specialty relations
 * @param array &$insert_hospital_relations Reference to hospital relations
 * @param bool $dry_run If true, only preview without database changes
 * @return array Processing result
 */
function fad_process_physician_data($physician_data, &$language_cache, &$specialty_cache, &$hospital_cache, &$insert_language_relations, &$insert_specialty_relations, &$insert_hospital_relations, $dry_run = false) {
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
            $wpdb->delete(
                "{$wpdb->prefix}doctor_hospital", 
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
        'hospitals' => [],
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
    
    // Process hospital affiliations
    if (isset($physician_data['hospital_affiliations']) && is_array($physician_data['hospital_affiliations'])) {
        foreach ($physician_data['hospital_affiliations'] as $hospital_name) {
            $hospital_name = trim($hospital_name);
            if (empty($hospital_name)) continue;
            
            $preview_data['hospitals'][] = $hospital_name;
            
            if (!$dry_run) {
                if (!isset($hospital_cache[$hospital_name])) {
                    $hospital_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT hospitalID FROM {$wpdb->prefix}hospitals WHERE hospital_name = %s",
                        $hospital_name
                    ));
                    
                    if (!$hospital_id) {
                        $wpdb->insert("{$wpdb->prefix}hospitals", ['hospital_name' => $hospital_name]);
                        $hospital_id = $wpdb->insert_id;
                    }
                    
                    $hospital_cache[$hospital_name] = $hospital_id;
                }
                
                $insert_hospital_relations[] = $wpdb->prepare("(%d, %d)", $doctor_id, $hospital_cache[$hospital_name]);
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
        
        // Extract geolocation data from location array
        $mapped['latitude'] = null;
        $mapped['longitude'] = null;
        
        // Check for direct lat/lng fields in location
        if (isset($location['latitude']) && is_numeric($location['latitude'])) {
            $mapped['latitude'] = (float)$location['latitude'];
        }
        if (isset($location['longitude']) && is_numeric($location['longitude'])) {
            $mapped['longitude'] = (float)$location['longitude'];
        }
        
        // Alternative field names that might contain coordinates
        if ($mapped['latitude'] === null && isset($location['lat']) && is_numeric($location['lat'])) {
            $mapped['latitude'] = (float)$location['lat'];
        }
        if ($mapped['longitude'] === null && isset($location['lng']) && is_numeric($location['lng'])) {
            $mapped['longitude'] = (float)$location['lng'];
        }
        if ($mapped['longitude'] === null && isset($location['lon']) && is_numeric($location['lon'])) {
            $mapped['longitude'] = (float)$location['lon'];
        }
        
        // Check for geolocation object in location
        if (isset($location['geolocation'])) {
            $geo = $location['geolocation'];
            if (is_array($geo)) {
                if ($mapped['latitude'] === null && isset($geo['latitude']) && is_numeric($geo['latitude'])) {
                    $mapped['latitude'] = (float)$geo['latitude'];
                }
                if ($mapped['longitude'] === null && isset($geo['longitude']) && is_numeric($geo['longitude'])) {
                    $mapped['longitude'] = (float)$geo['longitude'];
                }
                if ($mapped['latitude'] === null && isset($geo['lat']) && is_numeric($geo['lat'])) {
                    $mapped['latitude'] = (float)$geo['lat'];
                }
                if ($mapped['longitude'] === null && isset($geo['lng']) && is_numeric($geo['lng'])) {
                    $mapped['longitude'] = (float)$geo['lng'];
                }
            }
        }
        
        // Check for coordinates array in location
        if (isset($location['coordinates']) && is_array($location['coordinates'])) {
            $coords = $location['coordinates'];
            // GeoJSON format: [longitude, latitude]
            if (count($coords) >= 2) {
                if ($mapped['longitude'] === null && is_numeric($coords[0])) {
                    $mapped['longitude'] = (float)$coords[0];
                }
                if ($mapped['latitude'] === null && is_numeric($coords[1])) {
                    $mapped['latitude'] = (float)$coords[1];
                }
            }
        }
        
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
        $mapped['latitude'] = null;
        $mapped['longitude'] = null;
    }
    
    // Extract geolocation data from top-level API field (primary source)
    // This will override any location-based coordinates if present
    if (isset($api_data['geolocation']) && is_array($api_data['geolocation']) && !empty($api_data['geolocation'])) {
        $geo_string = $api_data['geolocation'][0]; // Get first geolocation entry
        if (is_string($geo_string) && strpos($geo_string, ',') !== false) {
            $coords = array_map('trim', explode(',', $geo_string));
            if (count($coords) >= 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
                $mapped['latitude'] = (float)$coords[0];
                $mapped['longitude'] = (float)$coords[1];
            }
        }
    }
    
    // Parse education and training data using the new parsing function
    $education_data = fad_parse_education_training($api_data['education_training'] ?? []);
    $mapped['medical_school'] = $education_data['medical_school'];
    $mapped['internship'] = $education_data['internship'];
    $mapped['residency'] = $education_data['residency'];
    $mapped['fellowship'] = $education_data['fellowship'];
    
    // Board certifications - use existing certification field
    $certifications = [];
    if (isset($api_data['board_certification']) && is_array($api_data['board_certification'])) {
        foreach ($api_data['board_certification'] as $cert) {
            // Extract certification name (after the last |)
            $parts = explode('|', $cert);
            if (count($parts) >= 3) {
                $certifications[] = trim(end($parts));
            }
        }
    }
    
    // Add any parsed certification from education_training
    if (!empty($education_data['certification'])) {
        $certifications[] = $education_data['certification'];
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
    
    // New required fields
    $mapped['full_name'] = trim(($mapped['first_name'] ?? '') . ' ' . ($mapped['last_name'] ?? ''));
    $mapped['npi'] = $api_data['npi'] ?? '';
    $mapped['accept_medi_cal'] = isset($api_data['medi_cal_networks']) && !empty($api_data['medi_cal_networks']) ? 1 : 0;
    $mapped['accepts_new_patients'] = isset($api_data['accepts_new_patients']) ? ($api_data['accepts_new_patients'] ? 1 : 0) : null;
    
    // Other fields that might be missing
    $mapped['primary_care'] = isset($api_data['primary_care']) ? ($api_data['primary_care'] ? 1 : 0) : 0;
    $mapped['prov_status'] = $api_data['prov_status'] ?? '';
    $mapped['is_ab_directory'] = 0; // Default value
    $mapped['is_bt_directory'] = 1; // Default value
    
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
        'insurances_synced' => 0,
        'errors' => []
    ];
    
    try {
        // Sync languages from dedicated endpoint
        $results['languages_synced'] = fad_sync_languages();
        
        // Sync hospitals from dedicated endpoint  
        $results['hospitals_synced'] = fad_sync_hospitals();
        
        // Sync insurances from physician search (no dedicated endpoint)
        $sample_response = FAD_API_Client::search_physicians([], 0);
        if (!is_wp_error($sample_response)) {
            $results['insurances_synced'] = fad_sync_insurances($sample_response);
        } else {
            $results['errors'][] = 'Insurance sync failed: ' . $sample_response->get_error_message();
        }
        
        $results['success'] = empty($results['errors']);
        
    } catch (Exception $e) {
        $results['errors'][] = 'Sync failed: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Sync languages from dedicated API endpoint
 *
 * @return int Number of languages synced
 */
function fad_sync_languages() {
    global $wpdb;
    
    $synced = 0;
    
    try {
        // Use the dedicated languages API endpoint
        $languages_response = FAD_API_Client::get_languages();
        
        if (is_wp_error($languages_response)) {
            error_log('Languages API Error: ' . $languages_response->get_error_message());
            return 0;
        }
        
        $languages_data = [];
        
        // Handle different possible response formats
        if (isset($languages_response['data']) && is_array($languages_response['data'])) {
            $languages_data = $languages_response['data'];
        } elseif (isset($languages_response['rows']) && is_array($languages_response['rows'])) {
            $languages_data = $languages_response['rows'];
        } elseif (is_array($languages_response)) {
            // If the response is directly an array of languages
            $languages_data = $languages_response;
        }
        
        foreach ($languages_data as $language_item) {
            $language_name = '';
            
            // Handle different possible data structures
            if (is_string($language_item)) {
                $language_name = trim($language_item);
            } elseif (is_array($language_item)) {
                // Try common field names
                $language_name = trim($language_item['name'] ?? $language_item['language'] ?? $language_item['language_name'] ?? '');
            }
            
            if (empty($language_name)) {
                continue;
            }
            
            // Check if language already exists (case-insensitive)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT languageID FROM {$wpdb->prefix}languages WHERE LOWER(TRIM(language)) = %s",
                strtolower($language_name)
            ));
            
            if (!$existing) {
                $wpdb->insert("{$wpdb->prefix}languages", [
                    'language' => $language_name
                ]);
                $synced++;
            }
        }
        
    } catch (Exception $e) {
        error_log('Languages sync error: ' . $e->getMessage());
    }
    
    return $synced;
}

/**
 * Sync hospitals from dedicated API endpoint
 *
 * @return int Number of hospitals synced
 */
function fad_sync_hospitals() {
    global $wpdb;
    
    $synced = 0;
    
    try {
        // Use the dedicated hospitals API endpoint
        $hospitals_response = FAD_API_Client::get_hospitals();
        
        if (is_wp_error($hospitals_response)) {
            error_log('Hospitals API Error: ' . $hospitals_response->get_error_message());
            return 0;
        }
        
        $hospitals_data = [];
        
        // Handle different possible response formats
        if (isset($hospitals_response['data']) && is_array($hospitals_response['data'])) {
            $hospitals_data = $hospitals_response['data'];
        } elseif (isset($hospitals_response['rows']) && is_array($hospitals_response['rows'])) {
            $hospitals_data = $hospitals_response['rows'];
        } elseif (is_array($hospitals_response)) {
            // If the response is directly an array of hospitals
            $hospitals_data = $hospitals_response;
        }
        
        foreach ($hospitals_data as $hospital_item) {
            $hospital_name = '';
            
            // Handle different possible data structures
            if (is_string($hospital_item)) {
                $hospital_name = trim($hospital_item);
            } elseif (is_array($hospital_item)) {
                // Try common field names
                $hospital_name = trim($hospital_item['name'] ?? $hospital_item['hospital_name'] ?? $hospital_item['hospital'] ?? '');
            }
            
            if (empty($hospital_name)) {
                continue;
            }
            
            // Check if hospital already exists (case-insensitive)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT hospitalID FROM {$wpdb->prefix}hospitals WHERE LOWER(TRIM(hospital_name)) = %s",
                strtolower($hospital_name)
            ));
            
            if (!$existing) {
                $wpdb->insert("{$wpdb->prefix}hospitals", [
                    'hospital_name' => $hospital_name
                ]);
                $synced++;
            }
        }
        
    } catch (Exception $e) {
        error_log('Hospitals sync error: ' . $e->getMessage());
    }
    
    return $synced;
}

/**
 * Sync insurances from API response
 *
 * @param array $api_response
 * @return int Number of insurances synced
 */
function fad_sync_insurances($api_response) {
    global $wpdb;
    
    $synced = 0;
    $all_insurances = [];
    
    if (isset($api_response['rows']) && is_array($api_response['rows'])) {
        foreach ($api_response['rows'] as $physician) {
            // Collect from all insurance types
            $insurance_types = [
                'hmo_networks' => 'hmo',
                'ppo_networks' => 'ppo', 
                'acn_networks' => 'acn',
                'aco_networks' => 'aco',
                'insurancePlanLinks' => 'plan_link'
            ];
            
            foreach ($insurance_types as $api_field => $type) {
                if (isset($physician[$api_field]) && is_array($physician[$api_field])) {
                    foreach ($physician[$api_field] as $insurance_name) {
                        $insurance_name = trim($insurance_name);
                        if (!empty($insurance_name)) {
                            $key = $insurance_name . '|' . $type;
                            $all_insurances[$key] = ['name' => $insurance_name, 'type' => $type];
                        }
                    }
                }
            }
        }
        
        // Sync unique insurances
        foreach ($all_insurances as $insurance_data) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT insuranceID FROM {$wpdb->prefix}insurances WHERE LOWER(TRIM(insurance_name)) = %s AND insurance_type = %s",
                strtolower(trim($insurance_data['name'])),
                $insurance_data['type']
            ));
            
            if (!$existing) {
                $wpdb->insert("{$wpdb->prefix}insurances", [
                    'insurance_name' => $insurance_data['name'],
                    'insurance_type' => $insurance_data['type']
                ]);
                $synced++;
            }
        }
    }
    
    return $synced;
}

/**
 * Validate database schema for API import
 *
 * @return array Validation results
 */
function fad_validate_database_schema() {
    global $wpdb;
    
    $results = [
        'valid' => true,
        'tables' => [],
        'missing_columns' => [],
        'missing_tables' => [],
        'errors' => []
    ];
    
    // Required tables and their columns
    $required_schema = [
        'doctors' => [
            'doctorID', 'atlas_primary_key', 'idme', 'first_name', 'last_name', 
            'full_name', 'email', 'phone_number', 'fax_number', 'npi',
            'primary_care', 'degree', 'gender', 'medical_school', 
            'internship', 'certification', 'residency', 'fellowship',
            'biography', 'profile_img_url', 'practice_name', 'prov_status',
            'address', 'city', 'state', 'zip', 'county',
            'is_ab_directory', 'is_bt_directory', 'accept_medi_cal',
            'accepts_new_patients', 'Insurances',
            'aco_active_networks', 'hmo_active_networks', 'ppo_active_network', 
            'slug', 'created_at', 'latitude', 'longitude'
        ],
        'specialties' => ['specialtyID', 'specialty_name'],
        'languages' => ['languageID', 'language'],
        'zocdoc' => ['zocdocID', 'book', 'book_url'],
        'hospitals' => ['hospitalID', 'hospital_name'],
        'insurances' => ['insuranceID', 'insurance_name', 'insurance_type'],
        'doctor_specialties' => ['specialtyID', 'doctorID'],
        'doctor_language' => ['doctorID', 'languageID'],
        'doctor_zocdoc' => ['zocdocID', 'doctorID'],
        'doctor_insurance' => ['doctorID', 'insuranceID'],
        'doctor_hospital' => ['doctorID', 'hospitalID']
    ];
    
    foreach ($required_schema as $table_name => $required_columns) {
        $full_table_name = $wpdb->prefix . $table_name;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $full_table_name
        ));
        
        if (!$table_exists) {
            $results['valid'] = false;
            $results['missing_tables'][] = $table_name;
            $results['tables'][$table_name] = [
                'exists' => false,
                'missing_columns' => $required_columns
            ];
            continue;
        }
        
        // Get existing columns
        $existing_columns = $wpdb->get_results("DESCRIBE $full_table_name");
        $existing_column_names = array_map(function($col) {
            return $col->Field;
        }, $existing_columns);
        
        // Check for missing columns
        $missing_columns = array_diff($required_columns, $existing_column_names);
        // Convert to indexed array to ensure clean JSON output
        $missing_columns = array_values($missing_columns);
        
        $results['tables'][$table_name] = [
            'exists' => true,
            'total_columns' => count($existing_column_names),
            'required_columns' => count($required_columns),
            'missing_columns' => $missing_columns
        ];
        
        if (!empty($missing_columns)) {
            $results['valid'] = false;
            $results['missing_columns'][$table_name] = $missing_columns;
        }
    }
    
    return $results;
}

/**
 * Auto-fix database schema issues
 *
 * @return array Fix results
 */
function fad_fix_database_schema() {
    global $wpdb;
    
    $results = [
        'success' => true,
        'tables_created' => [],
        'columns_added' => [],
        'columns_removed' => [],
        'errors' => []
    ];
    
    try {
        // First remove unnecessary duplicate columns
        $unnecessary_columns = [
            'education_training',
            'board_certification', 
            'hospital_affiliations'
        ];
        
        foreach ($unnecessary_columns as $column) {
            // Check if column exists
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}doctors LIKE %s",
                $column
            ));
            
            if (!empty($column_exists)) {
                $sql = "ALTER TABLE {$wpdb->prefix}doctors DROP COLUMN $column";
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    $results['columns_removed'][] = $column;
                } else {
                    $results['errors'][] = "Failed to remove column: $column - " . $wpdb->last_error;
                }
            }
        }
        
        // Add missing required columns
        $required_columns = [
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'npi' => 'VARCHAR(20) DEFAULT NULL',
            'accept_medi_cal' => 'TINYINT(1) DEFAULT NULL',
            'accepts_new_patients' => 'TINYINT(1) DEFAULT NULL'
        ];
        
        foreach ($required_columns as $column => $definition) {
            // Check if column exists
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$wpdb->prefix}doctors LIKE %s",
                $column
            ));
            
            if (empty($column_exists)) {
                $sql = "ALTER TABLE {$wpdb->prefix}doctors ADD COLUMN $column $definition";
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    $results['columns_added'][] = $column;
                } else {
                    $results['errors'][] = "Failed to add column: $column - " . $wpdb->last_error;
                }
            }
        }
        
        // Include the table creation file for other tables
        require_once plugin_dir_path(__FILE__) . 'create-tables.php';
        
        // Call the table creation function
        fad_create_tables();
        
        $message_parts = [];
        if (!empty($results['columns_removed'])) {
            $message_parts[] = "Removed duplicate columns: " . implode(', ', $results['columns_removed']);
        }
        if (!empty($results['columns_added'])) {
            $message_parts[] = "Added missing columns: " . implode(', ', $results['columns_added']);
        }
        $message_parts[] = "Database schema has been updated successfully.";
        
        $results['message'] = implode(' ', $message_parts);
        
    } catch (Exception $e) {
        $results['success'] = false;
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Parse education training data from API format
 *
 * @param array $education_training Array of education strings from API
 * @return array Parsed education data
 */
function fad_parse_education_training($education_training) {
    $parsed = [
        'medical_school' => '',
        'internship' => '',
        'residency' => '',
        'fellowship' => '',
        'certification' => ''
    ];
    
    if (!is_array($education_training)) {
        return $parsed;
    }
    
    foreach ($education_training as $entry) {
        if (empty($entry)) continue;
        
        // Remove timestamp and pipe characters: "1996-09-30|844041600|" becomes ""
        $cleaned = preg_replace('/^\d{4}-\d{2}-\d{2}\|\d+\|/', '', $entry);
        $cleaned = preg_replace('/^\|\|/', '', $cleaned); // Remove leading ||
        $cleaned = trim($cleaned);
        
        if (empty($cleaned)) continue;
        
        // Split by comma to get type and institution
        $parts = explode(',', $cleaned, 2);
        if (count($parts) < 2) continue;
        
        $type = trim($parts[0]);
        $institution = trim($parts[1]);
        
        // Map to appropriate field based on type
        $type_lower = strtolower($type);
        if (strpos($type_lower, 'medical school') !== false) {
            $parsed['medical_school'] = $institution;
        } elseif (strpos($type_lower, 'internship') !== false) {
            $parsed['internship'] = $institution;
        } elseif (strpos($type_lower, 'residency') !== false) {
            $parsed['residency'] = $institution;
        } elseif (strpos($type_lower, 'fellowship') !== false) {
            $parsed['fellowship'] = $institution;
        } elseif (strpos($type_lower, 'certification') !== false || strpos($type_lower, 'board') !== false) {
            $parsed['certification'] = $institution;
        }
    }
    
    return $parsed;
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

/**
 * Import a single physician from API data
 *
 * @param array $physician_data Physician data from API
 * @return array Result with success status and action taken
 */
function fad_import_single_physician($physician_data) {
    global $wpdb;
    
    $result = [
        'success' => false,
        'action' => '',
        'error' => '',
        'doctor_id' => null
    ];
    
    try {
        // Extract basic physician data
        $first_name = sanitize_text_field($physician_data['first_name'] ?? '');
        $last_name = sanitize_text_field($physician_data['last_name'] ?? '');
        $provider_key = sanitize_text_field($physician_data['provider_key'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $result['error'] = 'Missing required physician name data';
            return $result;
        }
        
        // Check if physician already exists (by idme/provider_key or name combination)
        $existing_doctor = null;
        if (!empty($provider_key)) {
            $existing_doctor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}doctors WHERE idme = %s",
                $provider_key
            ));
        }
        
        if (!$existing_doctor) {
            // Fallback: check by name
            $existing_doctor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}doctors WHERE first_name = %s AND last_name = %s",
                $first_name,
                $last_name
            ));
        }
        
        // Prepare physician data for database (matching actual table structure and API data)
        $doctor_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'full_name' => sanitize_text_field($physician_data['full_name'] ?? ''),
            'degree' => sanitize_text_field($physician_data['suffix'] ?? ''),
            'idme' => $provider_key, // Using idme field for provider_key
            'gender' => !empty($physician_data['gender']) ? sanitize_text_field($physician_data['gender'][0]['name'] ?? '') : '',
            'biography' => sanitize_textarea_field($physician_data['about_me'] ?? ''),
            'accepts_new_patients' => isset($physician_data['accepts_new_patients']) ? (bool)$physician_data['accepts_new_patients'] : false,
            'accept_medi_cal' => isset($physician_data['accept_medi_cal']) ? (bool)$physician_data['accept_medi_cal'] : false,
        ];
        
        // Extract and process NPI
        if (!empty($physician_data['npi']) && is_array($physician_data['npi'])) {
            $doctor_data['npi'] = sanitize_text_field($physician_data['npi'][0]);
        }
        
        // Extract and process hospital affiliations
        if (!empty($physician_data['hospital_affiliations']) && is_array($physician_data['hospital_affiliations'])) {
            $cleaned_hospitals = [];
            foreach ($physician_data['hospital_affiliations'] as $hospital) {
                $hospital = sanitize_text_field($hospital);
                // Skip "Hospital Waiver on File" entries
                if (stripos($hospital, 'Hospital Waiver on File') === false && !empty(trim($hospital))) {
                    $cleaned_hospitals[] = trim($hospital);
                }
            }
            $doctor_data['hospitalNames'] = implode(', ', $cleaned_hospitals);
        }
        
        // Process education_training data
        if (!empty($physician_data['education_training']) && is_array($physician_data['education_training'])) {
            $medical_schools = [];
            $internships = [];
            $residencies = [];
            $fellowships = [];
            
            foreach ($physician_data['education_training'] as $education) {
                $education = sanitize_text_field($education);
                
                if (stripos($education, 'Medical School') !== false) {
                    $medical_schools[] = str_replace(['||Medical School, ', '||Medical School,'], '', $education);
                } elseif (stripos($education, 'Internship') !== false) {
                    // Extract institution name only (similar to board certification logic)
                    // Format likely: "timestamp|timestamp|Institution Name" or "||Internship, Institution Name"
                    $parts = explode('|', $education);
                    if (count($parts) >= 3) {
                        $institution = sanitize_text_field(trim($parts[2]));
                        // Remove any remaining "Internship" text and clean up
                        $institution = preg_replace('/\b(Internship|internship)\b,?\s*/i', '', $institution);
                        $institution = trim($institution, ', ');
                        if (!empty($institution)) {
                            $internships[] = $institution;
                        }
                    } else {
                        // Fallback: remove the prefix labels
                        $cleaned = str_replace(['||Internship, ', '||Internship,', 'Internship, ', 'Internship,'], '', $education);
                        $cleaned = preg_replace('/\b(Internship|internship)\b,?\s*/i', '', $cleaned);
                        $cleaned = trim($cleaned, ', ');
                        if (!empty($cleaned)) {
                            $internships[] = $cleaned;
                        }
                    }
                } elseif (stripos($education, 'Residency') !== false) {
                    // Extract institution name only
                    $parts = explode('|', $education);
                    if (count($parts) >= 3) {
                        $institution = sanitize_text_field(trim($parts[2]));
                        // Remove any remaining "Residency" text and clean up
                        $institution = preg_replace('/\b(Residency|residency)\b,?\s*/i', '', $institution);
                        $institution = trim($institution, ', ');
                        if (!empty($institution)) {
                            $residencies[] = $institution;
                        }
                    } else {
                        // Fallback: remove the prefix labels
                        $cleaned = str_replace(['||Residency, ', '||Residency,', 'Residency, ', 'Residency,'], '', $education);
                        $cleaned = preg_replace('/\b(Residency|residency)\b,?\s*/i', '', $cleaned);
                        $cleaned = trim($cleaned, ', ');
                        if (!empty($cleaned)) {
                            $residencies[] = $cleaned;
                        }
                    }
                } elseif (stripos($education, 'Fellowship') !== false) {
                    // Extract institution name only
                    $parts = explode('|', $education);
                    if (count($parts) >= 3) {
                        $institution = sanitize_text_field(trim($parts[2]));
                        // Remove any remaining "Fellowship" text and clean up
                        $institution = preg_replace('/\b(Fellowship|fellowship)\b,?\s*/i', '', $institution);
                        $institution = trim($institution, ', ');
                        if (!empty($institution)) {
                            $fellowships[] = $institution;
                        }
                    } else {
                        // Fallback: remove the prefix labels
                        $cleaned = str_replace(['||Fellowship, ', '||Fellowship,', 'Fellowship, ', 'Fellowship,'], '', $education);
                        $cleaned = preg_replace('/\b(Fellowship|fellowship)\b,?\s*/i', '', $cleaned);
                        $cleaned = trim($cleaned, ', ');
                        if (!empty($cleaned)) {
                            $fellowships[] = $cleaned;
                        }
                    }
                }
            }
            
            $doctor_data['medical_school'] = implode(', ', $medical_schools);
            $doctor_data['internship'] = implode(', ', $internships);
            $doctor_data['residency'] = implode(', ', $residencies);
            $doctor_data['fellowship'] = implode(', ', $fellowships);
        }
        
        // Process board_certification - extract organization name only (after dates/numbers)
        if (!empty($physician_data['board_certification']) && is_array($physician_data['board_certification'])) {
            $certifications = [];
            foreach ($physician_data['board_certification'] as $cert) {
                // Format: "2002-07-31|1028073600|American Board of Pediatrics"
                $parts = explode('|', $cert);
                if (count($parts) >= 3) {
                    $certifications[] = sanitize_text_field($parts[2]); // Get the organization name
                }
            }
            $doctor_data['certification'] = implode(', ', $certifications);
        }
        
        // Extract location data (matching actual table structure)
        if (!empty($physician_data['location']) && is_array($physician_data['location'])) {
            $location = $physician_data['location'][0];
            $address_parts = [];
            if (!empty($location['address_line1'])) $address_parts[] = $location['address_line1'];
            if (!empty($location['address_line2'])) $address_parts[] = $location['address_line2'];
            
            $doctor_data['address'] = sanitize_text_field(implode(', ', $address_parts));
            $doctor_data['city'] = sanitize_text_field($location['locality'] ?? '');
            $doctor_data['state'] = sanitize_text_field($location['administrative_area'] ?? '');
            $doctor_data['zip'] = sanitize_text_field($location['postal_code'] ?? '');
            $doctor_data['phone_number'] = sanitize_text_field($location['address_phonenumber'] ?? '');
            $doctor_data['practice_name'] = sanitize_text_field($location['organization'] ?? '');
        }
        
        // Extract geolocation
        if (!empty($physician_data['geolocation']) && is_array($physician_data['geolocation'])) {
            $geolocation = $physician_data['geolocation'][0];
            if (strpos($geolocation, ',') !== false) {
                list($lat, $lng) = explode(',', $geolocation, 2);
                $doctor_data['latitude'] = (float)trim($lat);
                $doctor_data['longitude'] = (float)trim($lng);
            }
        }
        
        // Generate slug
        $degree = $doctor_data['degree'];
        $slug = fad_slugify_doctor($first_name, $last_name, $degree);
        $doctor_data['slug'] = fad_ensure_unique_slug($slug, $existing_doctor ? $existing_doctor->doctorID : null);
        
        if ($existing_doctor) {
            // Update existing physician
            $doctor_data['doctorID'] = $existing_doctor->doctorID;
            $updated = $wpdb->update(
                $wpdb->prefix . 'doctors',
                $doctor_data,
                ['doctorID' => $existing_doctor->doctorID]
            );
            
            if ($updated !== false) {
                $result['success'] = true;
                $result['action'] = 'updated';
                $result['doctor_id'] = $existing_doctor->doctorID;
                
                // Update related data
                fad_update_physician_relationships($existing_doctor->doctorID, $physician_data);
            } else {
                $result['error'] = 'Failed to update physician in database: ' . $wpdb->last_error;
            }
        } else {
            // Insert new physician (created_at will be set automatically)
            $inserted = $wpdb->insert($wpdb->prefix . 'doctors', $doctor_data);
            
            if ($inserted) {
                $doctor_id = $wpdb->insert_id;
                $result['success'] = true;
                $result['action'] = 'inserted';
                $result['doctor_id'] = $doctor_id;
                
                // Insert related data
                fad_update_physician_relationships($doctor_id, $physician_data);
            } else {
                $result['error'] = 'Failed to insert physician into database: ' . $wpdb->last_error;
            }
        }
        
    } catch (Exception $e) {
        $result['error'] = 'Exception: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Update physician relationships (specialties, languages, etc.)
 *
 * @param int $doctor_id Doctor ID
 * @param array $physician_data Physician data from API
 */
function fad_update_physician_relationships($doctor_id, $physician_data) {
    global $wpdb;
    
    // Clear existing relationships
    $wpdb->delete($wpdb->prefix . 'doctor_specialties', ['doctorID' => $doctor_id]);
    $wpdb->delete($wpdb->prefix . 'doctor_language', ['doctorID' => $doctor_id]);
    $wpdb->delete($wpdb->prefix . 'doctor_insurance', ['doctorID' => $doctor_id]);
    $wpdb->delete($wpdb->prefix . 'doctor_hospital', ['doctorID' => $doctor_id]);
    
    // Handle specialties
    if (!empty($physician_data['specialties']) && is_array($physician_data['specialties'])) {
        foreach ($physician_data['specialties'] as $specialty_data) {
            $specialty_name = trim($specialty_data['name'] ?? '');
            if (!empty($specialty_name)) {
                // Get or create specialty
                $specialty_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT specialtyID FROM {$wpdb->prefix}specialties WHERE LOWER(TRIM(specialty_name)) = %s",
                    strtolower(trim($specialty_name))
                ));
                
                if (!$specialty_id) {
                    $wpdb->insert($wpdb->prefix . 'specialties', ['specialty_name' => trim($specialty_name)]);
                    $specialty_id = $wpdb->insert_id;
                }
                
                // Link doctor to specialty (avoid duplicates)
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}doctor_specialties WHERE doctorID = %d AND specialtyID = %d",
                    $doctor_id, $specialty_id
                ));
                
                if (!$existing) {
                    $wpdb->insert($wpdb->prefix . 'doctor_specialties', [
                        'doctorID' => $doctor_id,
                        'specialtyID' => $specialty_id
                    ]);
                }
            }
        }
    }
    
    // Handle languages
    if (!empty($physician_data['languages_spoken_by_doctor']) && is_array($physician_data['languages_spoken_by_doctor'])) {
        foreach ($physician_data['languages_spoken_by_doctor'] as $language_data) {
            $language_name = trim($language_data['name'] ?? '');
            if (!empty($language_name)) {
                // Get or create language
                $language_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT languageID FROM {$wpdb->prefix}languages WHERE LOWER(TRIM(language)) = %s",
                    strtolower(trim($language_name))
                ));
                
                if (!$language_id) {
                    $wpdb->insert($wpdb->prefix . 'languages', ['language' => trim($language_name)]);
                    $language_id = $wpdb->insert_id;
                }
                
                // Link doctor to language (avoid duplicates)
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}doctor_language WHERE doctorID = %d AND languageID = %d",
                    $doctor_id, $language_id
                ));
                
                if (!$existing) {
                    $wpdb->insert($wpdb->prefix . 'doctor_language', [
                        'doctorID' => $doctor_id,
                        'languageID' => $language_id
                    ]);
                }
            }
        }
    }
    
    // Handle insurance networks
    $insurance_types = [
        'hmo_networks' => 'hmo',
        'ppo_networks' => 'ppo',
        'acn_networks' => 'acn',
        'aco_networks' => 'aco',
        'insurancePlanLinks' => 'plan_link'
    ];
    
    foreach ($insurance_types as $api_field => $type) {
        if (!empty($physician_data[$api_field]) && is_array($physician_data[$api_field])) {
            foreach ($physician_data[$api_field] as $insurance_name) {
                $insurance_name = trim($insurance_name);
                if (!empty($insurance_name)) {
                    // Get or create insurance
                    $insurance_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT insuranceID FROM {$wpdb->prefix}insurances WHERE LOWER(TRIM(insurance_name)) = %s AND insurance_type = %s",
                        strtolower(trim($insurance_name)),
                        $type
                    ));
                    
                    if (!$insurance_id) {
                        $wpdb->insert($wpdb->prefix . 'insurances', [
                            'insurance_name' => trim($insurance_name),
                            'insurance_type' => $type
                        ]);
                        $insurance_id = $wpdb->insert_id;
                    }
                    
                    // Link doctor to insurance (avoid duplicates)
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}doctor_insurance WHERE doctorID = %d AND insuranceID = %d",
                        $doctor_id, $insurance_id
                    ));
                    
                    if (!$existing) {
                        $wpdb->insert($wpdb->prefix . 'doctor_insurance', [
                            'doctorID' => $doctor_id,
                            'insuranceID' => $insurance_id
                        ]);
                    }
                }
            }
        }
    }
    
    // Handle hospital affiliations
    if (!empty($physician_data['hospital_affiliations']) && is_array($physician_data['hospital_affiliations'])) {
        foreach ($physician_data['hospital_affiliations'] as $hospital_name) {
            $hospital_name = trim($hospital_name);
            if (!empty($hospital_name)) {
                // Get or create hospital
                $hospital_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT hospitalID FROM {$wpdb->prefix}hospitals WHERE LOWER(TRIM(hospital_name)) = %s",
                    strtolower(trim($hospital_name))
                ));
                
                if (!$hospital_id) {
                    $wpdb->insert($wpdb->prefix . 'hospitals', [
                        'hospital_name' => trim($hospital_name)
                    ]);
                    $hospital_id = $wpdb->insert_id;
                }
                
                // Link doctor to hospital
                $wpdb->insert($wpdb->prefix . 'doctor_hospital', [
                    'doctorID' => $doctor_id,
                    'hospitalID' => $hospital_id
                ]);
            }
        }
    }
}