<?php
/**
 * Debug API Response Helper
 * This file helps debug the actual API response structure
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'api-client.php';

/**
 * Get raw API response for debugging
 */
function fad_debug_api_response() {
    echo "<h2>API Response Debug</h2>";
    
    // Test search endpoint
    echo "<h3>Search Endpoint Response:</h3>";
    $search_response = FAD_API_Client::search_physicians(['limit' => 2]);
    
    if (is_wp_error($search_response)) {
        echo "<p style='color: red;'>Error: " . $search_response->get_error_message() . "</p>";
        $error_data = $search_response->get_error_data();
        if ($error_data && isset($error_data['body'])) {
            echo "<h4>Raw Response Body:</h4>";
            echo "<pre>" . htmlspecialchars($error_data['body']) . "</pre>";
        }
    } else {
        echo "<h4>Response Structure:</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($search_response, JSON_PRETTY_PRINT)) . "</pre>";
        
        echo "<h4>Top-level keys:</h4>";
        if (is_array($search_response)) {
            echo "<ul>";
            foreach (array_keys($search_response) as $key) {
                echo "<li><strong>$key</strong> (" . gettype($search_response[$key]) . ")";
                if (is_array($search_response[$key])) {
                    echo " - " . count($search_response[$key]) . " items";
                }
                echo "</li>";
            }
            echo "</ul>";
        }
    }
    
    // Test other endpoints
    $endpoints = ['languages', 'hospitals', 'insurance'];
    
    foreach ($endpoints as $endpoint) {
        echo "<h3>" . ucfirst($endpoint) . " Endpoint Response:</h3>";
        $response = FAD_API_Client::make_request($endpoint);
        
        if (is_wp_error($response)) {
            echo "<p style='color: red;'>Error: " . $response->get_error_message() . "</p>";
        } else {
            echo "<h4>Response Structure:</h4>";
            $json_preview = json_encode($response, JSON_PRETTY_PRINT);
            if (strlen($json_preview) > 2000) {
                $json_preview = substr($json_preview, 0, 2000) . "\n... (truncated)";
            }
            echo "<pre>" . htmlspecialchars($json_preview) . "</pre>";
            
            if (is_array($response)) {
                echo "<h4>Top-level keys:</h4>";
                echo "<ul>";
                foreach (array_keys($response) as $key) {
                    echo "<li><strong>$key</strong> (" . gettype($response[$key]) . ")";
                    if (is_array($response[$key])) {
                        echo " - " . count($response[$key]) . " items";
                    }
                    echo "</li>";
                }
                echo "</ul>";
            }
        }
    }
}

// Add to admin menu for easy access
add_action('admin_menu', function() {
    add_submenu_page(
        'find-a-doctor',
        'API Debug',
        'API Debug',
        'manage_options',
        'api-debug',
        function() {
            echo '<div class="wrap">';
            echo '<h1>API Response Debug</h1>';
            echo '<div class="card">';
            fad_debug_api_response();
            echo '</div>';
            echo '</div>';
        }
    );
});
?>