<?php
require_once plugin_dir_path(__FILE__) . 'api-client.php';

add_action('rest_api_init', function () {
    // Existing endpoint for local doctor data (legacy)
    register_rest_route('finddoctor/v1', '/doctors', [
        'methods' => 'GET',
        'callback' => 'fnd_get_doctors',
        'permission_callback' => '__return_true'
    ]);
    
    // Enhanced endpoint for headless consumption
    register_rest_route('finddoctor/v1', '/physicians', [
        'methods' => 'GET',
        'callback' => 'fnd_get_physicians_headless',
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
            ]
        ]
    ]);
    
    // Single physician by slug for headless
    register_rest_route('finddoctor/v1', '/physician/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'fnd_get_physician_by_slug',
        'permission_callback' => '__return_true'
    ]);
    
    // New endpoint to proxy API search
    register_rest_route('finddoctor/v1', '/api/search', [
        'methods' => 'GET',
        'callback' => 'fnd_api_search_doctors',
        'permission_callback' => '__return_true',
        'args' => [
            'specialty' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'location' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100
            ]
        ]
    ]);
    
    // New endpoint to get doctor by external ID
    register_rest_route('finddoctor/v1', '/api/doctor/(?P<id>\w+)', [
        'methods' => 'GET',
        'callback' => 'fnd_api_get_doctor',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]
    ]);
    
    // New endpoint to get reference data
    register_rest_route('finddoctor/v1', '/api/languages', [
        'methods' => 'GET',
        'callback' => 'fnd_api_get_languages',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('finddoctor/v1', '/api/hospitals', [
        'methods' => 'GET',
        'callback' => 'fnd_api_get_hospitals',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('finddoctor/v1', '/api/insurance', [
        'methods' => 'GET',
        'callback' => 'fnd_api_get_insurance',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('finddoctor/v1', '/api/specialties', [
        'methods' => 'GET',
        'callback' => 'fnd_api_get_specialties',
        'permission_callback' => '__return_true'
    ]);
    
    // Local database reference data endpoints
    register_rest_route('finddoctor/v1', '/local/specialties', [
        'methods' => 'GET',
        'callback' => 'fnd_get_local_specialties',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('finddoctor/v1', '/local/languages', [
        'methods' => 'GET',
        'callback' => 'fnd_get_local_languages',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('finddoctor/v1', '/local/hospitals', [
        'methods' => 'GET',
        'callback' => 'fnd_get_local_hospitals',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('finddoctor/v1', '/local/insurances', [
        'methods' => 'GET',
        'callback' => 'fnd_get_local_insurances',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('finddoctor/v1', '/local/degrees', [
        'methods' => 'GET',
        'callback' => 'fnd_get_local_degrees',
        'permission_callback' => '__return_true'
    ]);
});

function fnd_get_doctors() {
    global $wpdb;
    $table = $wpdb->prefix . 'doctors';
    $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    return rest_ensure_response($results);
}

function fnd_get_physicians_headless($request) {
    global $wpdb;
    
    $page = $request->get_param('page');
    $per_page = $request->get_param('per_page');
    $search = $request->get_param('search');
    
    $offset = ($page - 1) * $per_page;
    
    // Build search query
    $where_clause = '';
    $where_values = [];
    
    if ($search) {
        $where_clause = "WHERE first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s";
        $search_term = '%' . $search . '%';
        $where_values = [$search_term, $search_term, $search_term];
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}doctors {$where_clause}";
    $total = $wpdb->get_var($where_values ? $wpdb->prepare($count_query, $where_values) : $count_query);
    
    // Get physicians
    $query = "SELECT * FROM {$wpdb->prefix}doctors {$where_clause} ORDER BY last_name, first_name LIMIT %d OFFSET %d";
    $query_values = array_merge($where_values, [$per_page, $offset]);
    $physicians = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
    
    // Add related data
    foreach ($physicians as &$physician) {
        $physician['languages'] = fad_get_terms_for_doctor('language', $physician['doctorID']);
        $physician['specialties'] = fad_get_terms_for_doctor('specialty', $physician['doctorID']);
        $physician['hospitals'] = fad_get_doctor_hospitals($physician['doctorID'], $physician['hospitalNames'] ?? '');
        $physician['degrees'] = fad_get_terms_for_doctor('degree', $physician['doctorID']);
        $physician['insurances'] = fad_get_terms_for_doctor('insurance', $physician['doctorID']);
    }
    
    return rest_ensure_response([
        'physicians' => $physicians,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int)$total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

function fnd_get_physician_by_slug($request) {
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
    $physician['hospitals'] = fad_get_doctor_hospitals($physician['doctorID'], $physician['hospitalNames'] ?? '');
    $physician['degrees'] = fad_get_terms_for_doctor('degree', $physician['doctorID']);
    $physician['insurances'] = fad_get_terms_for_doctor('insurance', $physician['doctorID']);
    
    return rest_ensure_response($physician);
}

function fnd_api_search_doctors($request) {
    $params = [];
    
    if ($request->get_param('specialty')) {
        $params['specialty'] = $request->get_param('specialty');
    }
    
    if ($request->get_param('location')) {
        $params['location'] = $request->get_param('location');
    }
    
    if ($request->get_param('limit')) {
        $params['limit'] = $request->get_param('limit');
    }
    
    $result = FAD_API_Client::search_physicians($params);
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

function fnd_api_get_doctor($request) {
    $doctor_id = $request->get_param('id');
    
    $result = FAD_API_Client::get_physician($doctor_id);
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

function fnd_api_get_languages() {
    $result = FAD_API_Client::get_languages();
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

function fnd_api_get_hospitals() {
    $result = FAD_API_Client::get_hospitals();
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

function fnd_api_get_insurance() {
    $result = FAD_API_Client::get_insurance_networks();
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

function fnd_api_get_specialties() {
    $result = FAD_API_Client::get_specialties();
    
    if (is_wp_error($result)) {
        return new WP_Error(
            'api_error',
            $result->get_error_message(),
            ['status' => 500]
        );
    }
    
    return rest_ensure_response($result);
}

// Local database reference data functions
function fnd_get_local_specialties() {
    global $wpdb;
    
    $specialties = $wpdb->get_results(
        "SELECT s.specialty_name as name, COUNT(ds.doctorID) as count 
         FROM {$wpdb->prefix}specialties s 
         LEFT JOIN {$wpdb->prefix}doctor_specialties ds ON s.specialtyID = ds.specialtyID 
         GROUP BY s.specialtyID 
         ORDER BY s.specialty_name",
        ARRAY_A
    );
    
    return rest_ensure_response($specialties);
}

function fnd_get_local_languages() {
    global $wpdb;
    
    $languages = $wpdb->get_results(
        "SELECT l.language as name, COUNT(dl.doctorID) as count 
         FROM {$wpdb->prefix}languages l 
         LEFT JOIN {$wpdb->prefix}doctor_language dl ON l.languageID = dl.languageID 
         GROUP BY l.languageID 
         ORDER BY l.language",
        ARRAY_A
    );
    
    return rest_ensure_response($languages);
}

function fnd_get_local_hospitals() {
    global $wpdb;
    
    $hospitals = $wpdb->get_results(
        "SELECT h.hospital_name as name, COUNT(dh.doctorID) as count 
         FROM {$wpdb->prefix}hospitals h 
         LEFT JOIN {$wpdb->prefix}doctor_hospital dh ON h.hospitalID = dh.hospitalID 
         GROUP BY h.hospitalID 
         ORDER BY h.hospital_name",
        ARRAY_A
    );
    
    return rest_ensure_response($hospitals);
}

function fnd_get_local_insurances() {
    global $wpdb;
    
    $insurances = $wpdb->get_results(
        "SELECT i.insurance_name as name, i.insurance_type as type, COUNT(di.doctorID) as count 
         FROM {$wpdb->prefix}insurances i 
         LEFT JOIN {$wpdb->prefix}doctor_insurance di ON i.insuranceID = di.insuranceID 
         GROUP BY i.insuranceID 
         ORDER BY i.insurance_name",
        ARRAY_A
    );
    
    return rest_ensure_response($insurances);
}

function fnd_get_local_degrees() {
    global $wpdb;
    
    $degrees = $wpdb->get_results(
        "SELECT d.degree_name as name, d.degree_type as type, COUNT(dd.doctorID) as count 
         FROM {$wpdb->prefix}degrees d 
         LEFT JOIN {$wpdb->prefix}doctor_degrees dd ON d.degreeID = dd.degreeID 
         GROUP BY d.degreeID 
         ORDER BY d.degree_name",
        ARRAY_A
    );
    
    return rest_ensure_response($degrees);
}
