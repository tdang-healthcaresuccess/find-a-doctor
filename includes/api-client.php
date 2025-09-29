<?php
/**
 * API Client for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-config.php';

class FAD_API_Client {
    
    /**
     * Make API request with basic authentication
     *
     * @param string $endpoint_key The endpoint key from FAD_API_ENDPOINTS
     * @param array $params URL parameters to replace (e.g., {id}, {term})
     * @param array $query_params Query string parameters
     * @param string $method HTTP method (GET, POST, etc.)
     * @return array|WP_Error API response or error
     */
    public static function make_request($endpoint_key, $params = [], $query_params = [], $method = 'GET') {
        $url = fad_get_api_url($endpoint_key, $params);
        
        if (!$url) {
            return new WP_Error('invalid_endpoint', 'Invalid API endpoint');
        }
        
        // Add query parameters to URL
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
        
        // First attempt with configurable SSL verification
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . fad_get_auth_header(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => FAD_API_TIMEOUT,
            'sslverify' => FAD_API_SSL_VERIFY,
            'user-agent' => 'Find-A-Doctor-Plugin/1.0'
        ];
        
        $response = wp_remote_request($url, $args);
        
        // If SSL error, try alternative approaches
        if (is_wp_error($response) && strpos($response->get_error_message(), 'SSL') !== false) {
            // Try with different SSL settings
            $args['sslverify'] = false;
            $args['sslcertificates'] = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
            
            $response = wp_remote_request($url, $args);
            
            // If still failing, try with curl-specific options
            if (is_wp_error($response)) {
                $args['httpversion'] = '1.1';
                $args['redirection'] = 5;
                $args['blocking'] = true;
                $args['decompress'] = true;
                
                $response = wp_remote_request($url, $args);
            }
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error('api_error', 'API request failed with status ' . $status_code, [
                'status' => $status_code,
                'body' => $body,
                'url' => $url
            ]);
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from API', [
                'body' => $body,
                'json_error' => json_last_error_msg(),
                'url' => $url
            ]);
        }
        
        // Log the API response for debugging (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FAD API Response for ' . $endpoint_key . ': ' . print_r($decoded, true));
        }
        
        return $decoded;
    }
    
    /**
     * Search for physicians with pagination support
     *
     * @param array $search_params Search parameters
     * @param int $page Page number (0-based)
     * @return array|WP_Error
     */
    public static function search_physicians($search_params = [], $page = 0) {
        // Add page parameter to search params
        $search_params['page'] = $page;
        
        return self::make_request('search', [], $search_params);
    }
    
    /**
     * Get all physicians across all pages
     *
     * @param array $search_params Search parameters
     * @param int $max_pages Maximum pages to fetch (safety limit)
     * @return array|WP_Error
     */
    public static function search_all_physicians($search_params = [], $max_pages = 500) {
        $all_physicians = [];
        $current_page = 0;
        $total_pages = null;
        
        while ($current_page < $max_pages) {
            $response = self::search_physicians($search_params, $current_page);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (!isset($response['rows']) || !is_array($response['rows'])) {
                break;
            }
            
            // Add physicians from this page
            $all_physicians = array_merge($all_physicians, $response['rows']);
            
            // Check pagination info
            if (isset($response['pager'])) {
                $pager = $response['pager'];
                $total_pages = (int)$pager['total_pages'];
                $current_page = (int)$pager['current_page'];
                
                // If we've reached the last page, break
                if ($current_page >= $total_pages - 1) {
                    break;
                }
            }
            
            $current_page++;
        }
        
        // Return in the same format as single page response
        return [
            'rows' => $all_physicians,
            'pager' => [
                'current_page' => 0,
                'total_items' => count($all_physicians),
                'total_pages' => 1,
                'items_per_page' => count($all_physicians)
            ]
        ];
    }
    
    /**
     * Get physician by ID
     *
     * @param string $physician_id
     * @return array|WP_Error
     */
    public static function get_physician($physician_id) {
        return self::make_request('physician', ['id' => $physician_id]);
    }
    
    /**
     * Get physician by term
     *
     * @param string $term
     * @return array|WP_Error
     */
    public static function get_physician_by_term($term) {
        return self::make_request('term', ['term' => $term]);
    }
    
    /**
     * Get insurance networks
     *
     * @return array|WP_Error
     */
    public static function get_insurance_networks() {
        return self::make_request('insurance');
    }
    
    /**
     * Get available languages
     *
     * @return array|WP_Error
     */
    public static function get_languages() {
        return self::make_request('languages');
    }
    
    /**
     * Get hospital affiliations
     *
     * @return array|WP_Error
     */
    public static function get_hospitals() {
        return self::make_request('hospitals');
    }
    
    /**
     * Test API connection
     *
     * @return array|WP_Error
     */
    public static function test_connection() {
        // Use the search endpoint with minimal parameters to test connection
        $result = self::search_physicians(['limit' => 1]);
        
        // If this fails, try a simpler endpoint
        if (is_wp_error($result)) {
            // Try the languages endpoint as a fallback
            $result = self::get_languages();
        }
        
        return $result;
    }
    
    /**
     * Detailed connection test with debugging info
     *
     * @return array Connection test results
     */
    public static function detailed_connection_test() {
        $results = [
            'success' => false,
            'tests' => [],
            'errors' => [],
            'debug_info' => []
        ];
        
        // Add debug info
        $results['debug_info'] = [
            'php_version' => PHP_VERSION,
            'curl_available' => function_exists('curl_version'),
            'openssl_available' => extension_loaded('openssl'),
            'wp_remote_available' => function_exists('wp_remote_request'),
            'api_hostname' => FAD_API_HOSTNAME,
            'api_username' => FAD_API_USERNAME
        ];
        
        if (function_exists('curl_version')) {
            $curl_info = curl_version();
            $results['debug_info']['curl_version'] = $curl_info['version'];
            $results['debug_info']['ssl_version'] = $curl_info['ssl_version'];
        }
        
        // Test each endpoint
        $endpoints_to_test = ['search', 'languages', 'hospitals'];
        
        foreach ($endpoints_to_test as $endpoint) {
            $test_result = [
                'endpoint' => $endpoint,
                'url' => fad_get_api_url($endpoint),
                'success' => false,
                'error' => null,
                'response_code' => null
            ];
            
            try {
                $response = self::make_request($endpoint, [], $endpoint === 'search' ? ['limit' => 1] : []);
                
                if (is_wp_error($response)) {
                    $test_result['error'] = $response->get_error_message();
                    $error_data = $response->get_error_data();
                    if (isset($error_data['status'])) {
                        $test_result['response_code'] = $error_data['status'];
                    }
                } else {
                    $test_result['success'] = true;
                    $test_result['response_code'] = 200;
                    $results['success'] = true; // At least one endpoint worked
                }
            } catch (Exception $e) {
                $test_result['error'] = $e->getMessage();
            }
            
            $results['tests'][] = $test_result;
        }
        
        return $results;
    }
}