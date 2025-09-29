<?php
if ( ! defined('ABSPATH') ) { exit; }

class FAD_GraphQL_Activator {
   
    public static function activate() {
        ob_start();
        global $wpdb;
        $t_doctors = $wpdb->prefix . 'doctors';

        $has = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'slug'",
            $t_doctors
        ));
        if ( ! $has ) {
            $wpdb->query("ALTER TABLE {$t_doctors} ADD COLUMN slug VARCHAR(191)");
        }

        @ $wpdb->query("CREATE UNIQUE INDEX idx_doctors_slug ON {$t_doctors} (slug)");
        $t_ds = $wpdb->prefix . 'doctor_specialties';
        $t_dl = $wpdb->prefix . 'doctor_language';
        @ $wpdb->query("CREATE INDEX idx_ds_doctor ON {$t_ds} (doctor_id)");
        @ $wpdb->query("CREATE INDEX idx_dl_doctor ON {$t_dl} (doctor_id)");
        ob_start();
    }
}
