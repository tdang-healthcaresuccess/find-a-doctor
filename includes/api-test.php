<?php
/**
 * API Test Script for Find a Doctor Plugin
 * This file can be run directly to test the API connection
 */

// Include WordPress if running outside of WordPress context
if (!defined('ABSPATH')) {
    // Adjust this path to your WordPress root
    require_once '../../../../../../wp-config.php';
}

require_once plugin_dir_path(__FILE__) . 'api-config.php';
require_once plugin_dir_path(__FILE__) . 'api-client.php';

echo "<h1>Find a Doctor API Test</h1>\n";

// Test API Configuration
echo "<h2>API Configuration</h2>\n";
echo "<p><strong>Hostname:</strong> " . FAD_API_HOSTNAME . "</p>\n";
echo "<p><strong>Username:</strong> " . FAD_API_USERNAME . "</p>\n";
echo "<p><strong>Password:</strong> " . str_repeat('*', strlen(FAD_API_PASSWORD)) . "</p>\n";

// Test each endpoint URL generation
echo "<h2>API Endpoints</h2>\n";
echo "<ul>\n";
foreach (FAD_API_ENDPOINTS as $key => $endpoint) {
    $url = fad_get_api_url($key);
    echo "<li><strong>$key:</strong> $url</li>\n";
}
echo "</ul>\n";

// Test API Connection
echo "<h2>API Connection Test</h2>\n";
$test_result = FAD_API_Client::test_connection();

if (is_wp_error($test_result)) {
    echo "<p style='color: red;'><strong>❌ Connection Failed:</strong> " . $test_result->get_error_message() . "</p>\n";
    
    $error_data = $test_result->get_error_data();
    if ($error_data && isset($error_data['status'])) {
        echo "<p><strong>HTTP Status:</strong> " . $error_data['status'] . "</p>\n";
        echo "<p><strong>Response Body:</strong> " . htmlspecialchars($error_data['body']) . "</p>\n";
    }
} else {
    echo "<p style='color: green;'><strong>✅ Connection Successful!</strong></p>\n";
    echo "<p><strong>Response:</strong></p>\n";
    echo "<pre>" . htmlspecialchars(json_encode($test_result, JSON_PRETTY_PRINT)) . "</pre>\n";
}

// Test individual endpoints
echo "<h2>Individual Endpoint Tests</h2>\n";

$endpoints_to_test = [
    'languages' => [],
    'hospitals' => [],
    'insurance' => []
];

foreach ($endpoints_to_test as $endpoint => $params) {
    echo "<h3>Testing $endpoint endpoint</h3>\n";
    
    $result = FAD_API_Client::make_request($endpoint, $params);
    
    if (is_wp_error($result)) {
        echo "<p style='color: red;'><strong>❌ Failed:</strong> " . $result->get_error_message() . "</p>\n";
    } else {
        echo "<p style='color: green;'><strong>✅ Success!</strong></p>\n";
        echo "<p><strong>Response preview:</strong></p>\n";
        $preview = json_encode($result, JSON_PRETTY_PRINT);
        if (strlen($preview) > 1000) {
            $preview = substr($preview, 0, 1000) . "... (truncated)";
        }
        echo "<pre>" . htmlspecialchars($preview) . "</pre>\n";
    }
}

echo "<hr>\n";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
?>