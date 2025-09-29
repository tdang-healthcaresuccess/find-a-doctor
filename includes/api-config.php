<?php
/**
 * API Configuration for Find a Doctor Plugin
 */

defined('ABSPATH') || exit;

// API Configuration
define('FAD_API_HOSTNAME', 'https://providersvc.altais.health');
define('FAD_API_USERNAME', 'sysPvdPrd');
define('FAD_API_PASSWORD', 'Ua|q*.ppg{+([o!CB9NsA_9]%Bw{]V)F');

// SSL Configuration - set to false for development/testing environments with SSL issues
define('FAD_API_SSL_VERIFY', false);

// API Timeout in seconds
define('FAD_API_TIMEOUT', 30);

// API Endpoints
const FAD_API_ENDPOINTS = [
    'search'    => 'api/provider/v1/fad/physicians/search',
    'physician' => 'api/provider/v1/fad/physicians/{id}',
    'term'      => 'api/provider/v1/fad/physicians/{term}',
    'insurance' => 'api/provider/v1/fad/networks',
    'languages' => 'api/provider/v1/fad/provider/languages',
    'hospitals' => 'api/provider/v1/fad/hospitalAffiliation'
];

/**
 * Get full API URL for a specific endpoint
 *
 * @param string $endpoint_key The endpoint key from FAD_API_ENDPOINTS
 * @param array $params Parameters to replace in the URL (e.g., {id}, {term})
 * @return string Full API URL
 */
function fad_get_api_url($endpoint_key, $params = []) {
    if (!isset(FAD_API_ENDPOINTS[$endpoint_key])) {
        return false;
    }
    
    $endpoint = FAD_API_ENDPOINTS[$endpoint_key];
    
    // Replace parameters in the endpoint URL
    foreach ($params as $key => $value) {
        $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
    }
    
    return rtrim(FAD_API_HOSTNAME, '/') . '/' . ltrim($endpoint, '/');
}

/**
 * Get basic auth header for API requests
 *
 * @return string Base64 encoded auth header
 */
function fad_get_auth_header() {
    return base64_encode(FAD_API_USERNAME . ':' . FAD_API_PASSWORD);
}