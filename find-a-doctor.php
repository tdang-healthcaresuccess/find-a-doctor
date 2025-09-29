<?php
/**
 * Plugin Name: Find a Doctor
 * Description: Clean structured plugin with DB schema, import, and REST API.
 * Version: 2.0
 * Author: Your Name
 */

defined('ABSPATH') || exit;

// Required files
require_once plugin_dir_path(__FILE__) . 'includes/create-tables.php';
require_once plugin_dir_path(__FILE__) . 'includes/import-data.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-config.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-import.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/doctor-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/doctor-edit.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/manage-reference-data.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/api-import.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/api-test-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/url-rewrite.php';

// Include debug helper in development
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once plugin_dir_path(__FILE__) . 'includes/api-debug.php';
}


// Dummy fallback if fnd_import_page isn't defined


// Menu structure
add_action('admin_menu', function () {
    add_menu_page(
        'Find a Doctor', 'Find a Doctor', 'manage_options', 'find-a-doctor', 'fnd_render_api_import_page', 'dashicons-heart', 6
    );

    add_submenu_page(
        'find-a-doctor', 'API Import', 'API Import', 'manage_options', 'find-a-doctor', 'fnd_render_api_import_page'
    );

    add_submenu_page(
        'find-a-doctor', 'File Import (Legacy)', 'File Import (Legacy)', 'manage_options', 'file-import', 'fnd_import_page'
    );

    add_submenu_page(
        'find-a-doctor', 'API Test', 'API Test', 'manage_options', 'api-test', 'fnd_render_api_test_page'
    );

    add_submenu_page(
        'find-a-doctor', 'Doctor List', 'Doctor List', 'manage_options', 'doctor-list', 'fnd_render_doctor_list_page'
    );
     add_submenu_page(null, 'Edit Doctor', 'Edit Doctor', 'manage_options', 'doctor-edit', 'fnd_render_doctor_edit_page');

     add_submenu_page('find-a-doctor', 'Manage Reference Data', 'Reference Data', 'manage_options', 'manage-reference-data', 'fnd_render_reference_data_page');

});

add_action('admin_enqueue_scripts', function ($hook) {
    // Load ThickBox only on your custom plugin page
    if (isset($_GET['page']) && $_GET['page'] === 'manage-reference-data') {
        wp_enqueue_style('thickbox');
        wp_enqueue_script('thickbox');
        wp_enqueue_script('jquery'); // Ensure jQuery is loaded
    }
});


add_action('admin_enqueue_scripts', 'fnd_enqueue_admin_assets');

function fnd_enqueue_admin_assets($hook) {
    // Only load on your plugin pages
    if (strpos($hook, 'find-a-doctor') === false && strpos($hook, 'doctor') === false && strpos($hook, 'manage-reference-data') === false && strpos($hook, 'file-import') === false && strpos($hook, 'api-test') === false) {
        return;
    }

    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('fnd-custom-style', $plugin_url . 'assets/css/custom.css', [], '1.0.0');
    wp_enqueue_script('fnd-custom-script', $plugin_url . 'assets/js/custom.js', ['jquery'], '1.0.0', true);
}

add_action('wp_ajax_fnd_handle_doctor_upload', 'fnd_handle_doctor_upload_ajax');

function fnd_handle_doctor_upload_ajax() {
    if (!current_user_can('manage_options')) wp_die(); // Optional: Secure for admins only

    if (!empty($_FILES['doctor_file'])) {
        fnd_handle_doctor_upload($_FILES['doctor_file']);
    }

    wp_die(); // Important to end AJAX execution
}



// Activation hook
register_activation_hook(__FILE__, 'fnd_create_tables');
register_activation_hook(__FILE__, 'fnd_activate_plugin');

/**
 * Plugin activation handler
 */
function fnd_activate_plugin() {
    // Reset URL rewrite rules flag so they get flushed
    FAD_URL_Rewrite::reset_flush_flag();
}

// related to wp graph
define('FAD_GQL_VERSION', '1.0.3');
define('FAD_GQL_PATH', plugin_dir_path(__FILE__));
define('FAD_GQL_URL',  plugin_dir_url(__FILE__));

require_once FAD_GQL_PATH . 'includes/class-fad-activator.php';
require_once FAD_GQL_PATH . 'includes/helpers.php';

register_activation_hook(__FILE__, ['FAD_GraphQL_Activator', 'activate']);

add_action('plugins_loaded', function () {
    if ( function_exists('register_graphql_object_type') && function_exists('register_graphql_field') ) {
        require_once FAD_GQL_PATH . 'includes/class-fad-graphql.php';
    } else {
        add_action('admin_notices', function () {
            if ( ! function_exists('register_graphql_object_type') ) {
                echo '<div class="notice notice-error"><p><strong>Find a Doctor – GraphQL:</strong> Please activate the <a href="https://www.wpgraphql.com/" target="_blank">WPGraphQL</a> plugin to enable the API.</p></div>';
            }
        });
    }
}, 99);

// One-time backfill
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['backfill_doctor_slugs'])) return;
    global $wpdb;
    $t = $wpdb->prefix . 'doctors';
    $rows = $wpdb->get_results("SELECT doctorID, first_name, last_name, degree FROM {$t}", ARRAY_A);
    foreach ($rows as $r) {
        $slug = fad_slugify_doctor($r['first_name'] ?? '', $r['last_name'] ?? '', $r['degree'] ?? '');
        // If you enforce unique slugs, append -2, -3, ... on collision:
        $i = 2; $try = $slug;
        while ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE slug=%s AND doctorID<>%d", $try, (int)$r['doctorID']))) {
            $try = $slug . '-' . $i++;
        }
        $wpdb->update($t, ['slug' => $try], ['doctorID' => (int)$r['doctorID']]);
    }
    wp_safe_redirect(remove_query_arg('backfill_doctor_slugs'));
    exit;
});

