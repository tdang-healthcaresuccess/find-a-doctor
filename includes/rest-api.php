<?php
require_once plugin_dir_path(__FILE__) . 'api-client.php';

add_action('rest_api_init', function () {
    // Existing endpoint for local doctor data
    register_rest_route('finddoctor/v1', '/doctors', [
        'methods' => 'GET',
        'callback' => 'fnd_get_doctors',
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
});

function fnd_get_doctors() {
    global $wpdb;
    $table = $wpdb->prefix . 'doctors';
    $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    return rest_ensure_response($results);
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
