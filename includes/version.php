<?php
/**
 * Version Management Utility
 * 
 * This file contains version-related constants and utilities for the Find a Doctor plugin.
 * Update these values when releasing new versions.
 */

defined('ABSPATH') || exit;

// Plugin version constants
define('FAD_PLUGIN_VERSION', '2.4.0');
define('FAD_GRAPHQL_VERSION', '1.1.0');
define('FAD_DB_VERSION', '1.0.0'); // For database schema versioning if needed

/**
 * Get the current plugin version
 * 
 * @return string Plugin version
 */
function fad_get_plugin_version() {
    return FAD_PLUGIN_VERSION;
}

/**
 * Get the current GraphQL API version
 * 
 * @return string GraphQL version
 */
function fad_get_graphql_version() {
    return FAD_GRAPHQL_VERSION;
}

/**
 * Check if plugin needs database update
 * 
 * @return bool True if update needed
 */
function fad_needs_db_update() {
    $current_version = get_option('fad_db_version', '0.0.0');
    return version_compare($current_version, FAD_DB_VERSION, '<');
}

/**
 * Update database version option
 */
function fad_update_db_version() {
    update_option('fad_db_version', FAD_DB_VERSION);
}

/**
 * Get version information for debugging
 * 
 * @return array Version information
 */
function fad_get_version_info() {
    return [
        'plugin_version' => FAD_PLUGIN_VERSION,
        'graphql_version' => FAD_GRAPHQL_VERSION,
        'db_version' => FAD_DB_VERSION,
        'stored_db_version' => get_option('fad_db_version', 'not set'),
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
    ];
}