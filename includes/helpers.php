<?php
if ( ! defined('ABSPATH') ) { exit; }


function fad_degree_code($degree) {
    $code = strtolower($degree ?? '');
    // Remove all special characters, keep only letters and numbers
    $code = preg_replace('/[^a-z0-9]+/', '', $code);
    return $code;
}

// Build slug as firstname-lastname-degreeCode (all lowercase, no special chars)
function fad_slugify_doctor($first, $last, $degree) {
    $first  = sanitize_title($first);
    $last   = sanitize_title($last);
    $deg    = fad_degree_code($degree);
    
    // Ensure all parts are lowercase and clean
    $first = strtolower(preg_replace('/[^a-z0-9-]/', '', $first));
    $last = strtolower(preg_replace('/[^a-z0-9-]/', '', $last));
    
    $parts  = array_filter([$first, $last, $deg]);
    return implode('-', $parts);
}


  function fad_upsert_slug_for_doctor($doctor_id, $overwrite_existing = true) {
        global $wpdb;
        $t = $wpdb->prefix . 'doctors';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT doctorID, first_name, last_name, degree, slug FROM {$t} WHERE doctorID=%d",
            (int)$doctor_id
        ), ARRAY_A);
        if (!$row) return false;

        if (!$overwrite_existing && !empty($row['slug'])) return $row['slug'];

        $base = fad_slugify_doctor($row['first_name'] ?? '', $row['last_name'] ?? '', $row['degree'] ?? '');
        $slug = fad_ensure_unique_slug($base, (int)$doctor_id);
        $wpdb->update($t, ['slug' => $slug], ['doctorID' => (int)$doctor_id]);
        return $slug;
    }


function fad_ensure_unique_slug($slug, $doctor_id = 0) {
    global $wpdb;
    $t = $wpdb->prefix . 'doctors';
    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE slug = %s AND doctorID <> %d", $slug, $doctor_id));
    if ( ! $exists ) { return $slug; }
    $i = 2;
    while (true) {
        $try = $slug . '-' . $i;
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE slug = %s AND doctorID <> %d", $try, $doctor_id));
        if ( ! $exists ) { return $try; }
        $i++;
    }
}

function fad_get_terms_for_doctor($type, $doctor_id) {
    global $wpdb;
    switch ($type) {
        case 'language':
            $link = $wpdb->prefix . 'doctor_language';
            $term = $wpdb->prefix . 'languages';
            $sql  = "SELECT l.language FROM {$link} dl JOIN {$term} l ON l.languageID  = dl.languageID WHERE dl.doctorID  = %d ORDER BY l.language";
            break;
        case 'specialty':
            $link = $wpdb->prefix . 'doctor_specialties';
            $term = $wpdb->prefix . 'specialties';
            $sql  = "SELECT s.specialty_name FROM {$link} ds JOIN {$term} s ON s.specialtyID  = ds.specialtyID  WHERE ds.doctorID = %d ORDER BY s.specialty_name";
            break;
        default:
            return [];
    }
    $names = $wpdb->get_col($wpdb->prepare($sql, $doctor_id));
    return $names ? array_values($names) : [];
}

/**
 * Get hospitals for a doctor from normalized tables
 * Falls back to hospitalNames field if no normalized data exists
 *
 * @param int $doctor_id Doctor ID
 * @param string $hospital_names_fallback hospitalNames field value for fallback
 * @return array Array of hospital names
 */
function fad_get_doctor_hospitals($doctor_id, $hospital_names_fallback = '') {
    global $wpdb;
    
    // Try to get from normalized tables first
    $hospitals = $wpdb->get_col($wpdb->prepare("
        SELECT h.hospital_name 
        FROM {$wpdb->prefix}hospitals h
        INNER JOIN {$wpdb->prefix}doctor_hospital dh ON h.hospitalID = dh.hospitalID
        WHERE dh.doctorID = %d
        ORDER BY h.hospital_name
    ", $doctor_id));
    
    // If no normalized data, fall back to hospitalNames field
    if (empty($hospitals) && !empty($hospital_names_fallback)) {
        $hospitals = array_map('trim', explode(',', $hospital_names_fallback));
        $hospitals = array_filter($hospitals); // Remove empty values
    }
    
    return $hospitals;
}

/**
 * Format geolocation coordinates for display
 *
 * @param float|null $latitude
 * @param float|null $longitude
 * @param int $precision Number of decimal places
 * @return string Formatted coordinates or empty string
 */
function fad_format_geolocation($latitude, $longitude, $precision = 6) {
    if ($latitude === null || $longitude === null) {
        return '';
    }
    
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return '';
    }
    
    return number_format((float)$latitude, $precision, '.', '') . ', ' . 
           number_format((float)$longitude, $precision, '.', '');
}

/**
 * Check if geolocation coordinates are valid
 *
 * @param float|null $latitude
 * @param float|null $longitude
 * @return bool
 */
function fad_has_valid_geolocation($latitude, $longitude) {
    return $latitude !== null && $longitude !== null && 
           is_numeric($latitude) && is_numeric($longitude) &&
           $latitude >= -90 && $latitude <= 90 &&
           $longitude >= -180 && $longitude <= 180;
}
