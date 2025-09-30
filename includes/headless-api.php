<?php
/**
 * Headless WordPress API Extensions for Find a Doctor Plugin
 * Optimized for Faust.js and WP Engine headless setups
 */

defined('ABSPATH') || exit;

class FAD_Headless_API {
    
    /**
     * Initialize headless API features
     */
    public static function init() {
        // Remove template system for headless
        remove_action('wp', [FAD_URL_Rewrite::class, 'handle_physician_page']);
        
        // Add headless-specific REST endpoints
        add_action('rest_api_init', [__CLASS__, 'register_headless_endpoints']);
        
        // Add CORS headers for frontend consumption
        add_action('rest_api_init', [__CLASS__, 'add_cors_headers']);
        
        // Add custom fields to REST API
        add_action('rest_api_init', [__CLASS__, 'add_physician_rest_fields']);
        
        // Enable GraphQL if available
        if (function_exists('register_graphql_object_type')) {
            add_action('graphql_register_types', [__CLASS__, 'register_graphql_types']);
        }
    }
    
    /**
     * Register headless-specific REST endpoints
     */
    public static function register_headless_endpoints() {
        // Get physician by slug (for Faust.js routing)
        register_rest_route('fad/v1', '/physician/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_physician_by_slug'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get physicians list with pagination and search
        register_rest_route('fad/v1', '/physicians', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_physicians_list'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'type' => 'integer',
                    'minimum' => 1
                ],
                'per_page' => [
                    'default' => 20,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'search' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'specialty' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'location' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get all specialties for filters
        register_rest_route('fad/v1', '/specialties', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_specialties'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get all languages for filters
        register_rest_route('fad/v1', '/languages', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_languages'],
            'permission_callback' => '__return_true'
        ]);
        
        // Generate sitemap data for physicians
        register_rest_route('fad/v1', '/sitemap', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_sitemap_data'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Add CORS headers for frontend consumption
     */
    public static function add_cors_headers() {
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            $origin = get_http_origin();
            
            // Allow your frontend domain
            $allowed_origins = [
                'http://localhost:3000', // Local development
                'https://yourdomain.com', // Production frontend
                get_site_url() // WordPress site
            ];
            
            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Allow-Credentials: true');
            }
            
            return $served;
        }, 10, 4);
    }
    
    /**
     * Get physician by slug
     */
    public static function get_physician_by_slug($request) {
        global $wpdb;
        
        $slug = $request->get_param('slug');
        
        $physician = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}doctors WHERE slug = %s",
            $slug
        ), ARRAY_A);
        
        if (!$physician) {
            return new WP_Error('physician_not_found', 'Physician not found', ['status' => 404]);
        }
        
        // Add related data
        $physician['languages'] = fad_get_terms_for_doctor('language', $physician['doctorID']);
        $physician['specialties'] = fad_get_terms_for_doctor('specialty', $physician['doctorID']);
        
        // Format for frontend consumption
        return self::format_physician_for_api($physician);
    }
    
    /**
     * Get physicians list
     */
    public static function get_physicians_list($request) {
        global $wpdb;
        
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $search = $request->get_param('search');
        $specialty = $request->get_param('specialty');
        $location = $request->get_param('location');
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where_conditions = [];
        $where_values = [];
        
        if ($search) {
            $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)";
            $search_term = '%' . $search . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if ($location) {
            $where_conditions[] = "(city LIKE %s OR state LIKE %s OR address LIKE %s)";
            $location_term = '%' . $location . '%';
            $where_values[] = $location_term;
            $where_values[] = $location_term;
            $where_values[] = $location_term;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Handle specialty filter (requires join)
        $join_clause = '';
        if ($specialty) {
            $join_clause = "
                INNER JOIN {$wpdb->prefix}doctor_specialties ds ON d.doctorID = ds.doctorID
                INNER JOIN {$wpdb->prefix}specialties s ON ds.specialtyID = s.specialtyID
            ";
            if ($where_clause) {
                $where_clause .= " AND s.specialty_name LIKE %s";
            } else {
                $where_clause = "WHERE s.specialty_name LIKE %s";
            }
            $where_values[] = '%' . $specialty . '%';
        }
        
        // Get total count
        $count_query = "SELECT COUNT(DISTINCT d.doctorID) FROM {$wpdb->prefix}doctors d {$join_clause} {$where_clause}";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        
        // Get physicians
        $query = "
            SELECT DISTINCT d.* 
            FROM {$wpdb->prefix}doctors d 
            {$join_clause} 
            {$where_clause} 
            ORDER BY d.last_name, d.first_name 
            LIMIT %d OFFSET %d
        ";
        
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $physicians = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
        
        // Add related data for each physician
        foreach ($physicians as &$physician) {
            $physician['languages'] = fad_get_terms_for_doctor('language', $physician['doctorID']);
            $physician['specialties'] = fad_get_terms_for_doctor('specialty', $physician['doctorID']);
            $physician = self::format_physician_for_api($physician);
        }
        
        return [
            'physicians' => $physicians,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page),
                'has_next' => $page < ceil($total / $per_page),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Get all specialties
     */
    public static function get_specialties($request) {
        global $wpdb;
        
        $specialties = $wpdb->get_results(
            "SELECT s.specialty_name as name, COUNT(ds.doctorID) as count 
             FROM {$wpdb->prefix}specialties s 
             LEFT JOIN {$wpdb->prefix}doctor_specialties ds ON s.specialtyID = ds.specialtyID 
             GROUP BY s.specialtyID 
             ORDER BY s.specialty_name",
            ARRAY_A
        );
        
        return $specialties;
    }
    
    /**
     * Get all languages
     */
    public static function get_languages($request) {
        global $wpdb;
        
        $languages = $wpdb->get_results(
            "SELECT l.language as name, COUNT(dl.doctorID) as count 
             FROM {$wpdb->prefix}languages l 
             LEFT JOIN {$wpdb->prefix}doctor_language dl ON l.languageID = dl.languageID 
             GROUP BY l.languageID 
             ORDER BY l.language",
            ARRAY_A
        );
        
        return $languages;
    }
    
    /**
     * Get sitemap data for SEO
     */
    public static function get_sitemap_data($request) {
        global $wpdb;
        
        $physicians = $wpdb->get_results(
            "SELECT slug, updated_at, created_at FROM {$wpdb->prefix}doctors WHERE slug IS NOT NULL AND slug != ''",
            ARRAY_A
        );
        
        $sitemap_data = [];
        foreach ($physicians as $physician) {
            $sitemap_data[] = [
                'slug' => $physician['slug'],
                'url' => '/physicians/' . $physician['slug'],
                'lastmod' => $physician['updated_at'] ?: $physician['created_at'],
                'changefreq' => 'monthly',
                'priority' => '0.7'
            ];
        }
        
        return $sitemap_data;
    }
    
    /**
     * Format physician data for API consumption
     */
    private static function format_physician_for_api($physician) {
        // Convert database fields to camelCase for frontend
        $formatted = [
            'id' => (int)$physician['doctorID'],
            'slug' => $physician['slug'],
            'firstName' => $physician['first_name'],
            'lastName' => $physician['last_name'],
            'fullName' => trim($physician['first_name'] . ' ' . $physician['last_name']),
            'degree' => $physician['degree'],
            'email' => $physician['email'],
            'phone' => $physician['phone_number'],
            'fax' => $physician['fax_number'],
            'gender' => $physician['gender'],
            'biography' => $physician['biography'],
            'profileImage' => $physician['profile_img_url'],
            'isPrimaryCare' => (bool)$physician['primary_care'],
            'education' => [
                'medicalSchool' => $physician['medical_school'],
                'internship' => $physician['internship'],
                'residency' => $physician['residency'],
                'fellowship' => $physician['fellowship'],
                'certification' => $physician['certification']
            ],
            'location' => [
                'practiceName' => $physician['practice_name'],
                'address' => $physician['address'],
                'city' => $physician['city'],
                'state' => $physician['state'],
                'zip' => $physician['zip'],
                'county' => $physician['county'],
                'coordinates' => null
            ],
            'specialties' => $physician['specialties'] ?? [],
            'languages' => $physician['languages'] ?? [],
            'networks' => [
                'insurances' => $physician['Insurances'] ? explode(',', $physician['Insurances']) : [],
                'hospitals' => fad_get_doctor_hospitals($physician['doctorID'], $physician['hospitalNames'] ?? '')
            ],
            'metadata' => [
                'providerKey' => $physician['atlas_primary_key'],
                'idme' => $physician['idme'],
                'provStatus' => $physician['prov_status'],
                'isAbDirectory' => (bool)$physician['is_ab_directory'],
                'isBtDirectory' => (bool)$physician['is_bt_directory']
            ]
        ];
        
        // Add geolocation if available
        if (fad_has_valid_geolocation($physician['latitude'], $physician['longitude'])) {
            $formatted['location']['coordinates'] = [
                'latitude' => (float)$physician['latitude'],
                'longitude' => (float)$physician['longitude']
            ];
        }
        
        return $formatted;
    }
}

// Initialize headless API
FAD_Headless_API::init();