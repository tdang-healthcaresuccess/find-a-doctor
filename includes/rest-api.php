<?php
add_action('rest_api_init', function () {
    register_rest_route('finddoctor/v1', '/doctors', [
        'methods' => 'GET',
        'callback' => 'fnd_get_doctors',
        'permission_callback' => '__return_true'
    ]);
});

function fnd_get_doctors() {
    global $wpdb;
    $table = $wpdb->prefix . 'doctors';
    $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    return rest_ensure_response($results);
}
