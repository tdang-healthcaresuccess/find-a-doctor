<?php
function fnd_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Disable FK checks during table creation
        $wpdb->query("SET foreign_key_checks = 0");

        // Table creation function
        $tables = [

            // Doctors
            "Doctors" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctors (
                doctorID INT AUTO_INCREMENT PRIMARY KEY,
                atlas_primary_key INT,
                idme VARCHAR(100),
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                full_name VARCHAR(255),
                email VARCHAR(255),
                phone_number VARCHAR(50),
                fax_number VARCHAR(50),
                primary_care BOOLEAN,
                degree VARCHAR(255),
                gender VARCHAR(50),
                medical_school VARCHAR(255),
                internship VARCHAR(1500),
                certification VARCHAR(1500),
                residency VARCHAR(1500),
                fellowship VARCHAR(1500),
                biography LONGTEXT,
                profile_img_url TEXT,
                practice_name VARCHAR(255),
                prov_status  VARCHAR(255),
                address VARCHAR(255),
                city VARCHAR(20),
                state VARCHAR(20),
                zip VARCHAR(10),
                county VARCHAR(20),
                latitude DECIMAL(10, 8) NULL,
                longitude DECIMAL(11, 8) NULL,
                is_ab_directory BOOLEAN,
                is_bt_directory BOOLEAN,
                Insurances LONGTEXT,
                hospitalNames LONGTEXT,
                aco_active_networks LONGTEXT,
                hmo_active_networks LONGTEXT,
                ppo_active_network LONGTEXT,
                slug VARCHAR(100),
                npi VARCHAR(20),
                accept_medi_cal BOOLEAN DEFAULT FALSE,
                accepts_new_patients BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB $charset_collate;",

            // Languages
            "Languages" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}languages (
                languageID INT AUTO_INCREMENT PRIMARY KEY,
                language VARCHAR(255)
            ) ENGINE=InnoDB $charset_collate;",
            
            // Insurances
            "Insurances" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}insurances (
                insuranceID INT AUTO_INCREMENT PRIMARY KEY,
                insurance_name VARCHAR(255),
                insurance_type ENUM('hmo', 'ppo', 'acn', 'aco', 'medi_cal', 'plan_link', 'network') DEFAULT 'network'
            ) ENGINE=InnoDB $charset_collate;",

            // Hospitals
            "Hospitals" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hospitals (
                hospitalID INT AUTO_INCREMENT PRIMARY KEY,
                hospital_name VARCHAR(255)
            ) ENGINE=InnoDB $charset_collate;",

            // Degrees
            "Degrees" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}degrees (
                degreeID INT AUTO_INCREMENT PRIMARY KEY,
                degree_name VARCHAR(255),
                degree_type ENUM('medical', 'doctoral', 'masters', 'other') DEFAULT 'medical'
            ) ENGINE=InnoDB $charset_collate;",

            // Doctor_Language
            "Doctor_Language" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_language (
                doctorID INT,
                languageID INT,
                PRIMARY KEY (doctorID, languageID),
                FOREIGN KEY (doctorID) REFERENCES {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (languageID) REFERENCES {$wpdb->prefix}languages(languageID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;",
            
            // Doctor_Insurance
            "Doctor_Insurance" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_insurance (
                doctorID INT,
                insuranceID INT,
                PRIMARY KEY (doctorID, insuranceID),
                FOREIGN KEY (doctorID) REFERENCES {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (insuranceID) REFERENCES {$wpdb->prefix}insurances(insuranceID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;",

            // Doctor_Hospital
            "Doctor_Hospital" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_hospital (
                doctorID INT,
                hospitalID INT,
                PRIMARY KEY (doctorID, hospitalID),
                FOREIGN KEY (doctorID) REFERENCES {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (hospitalID) REFERENCES {$wpdb->prefix}hospitals(hospitalID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;",
            
            // Doctor_Degrees
            "Doctor_Degrees" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_degrees (
                doctorID INT,
                degreeID INT,
                PRIMARY KEY (doctorID, degreeID),
                FOREIGN KEY (doctorID) REFERENCES {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (degreeID) REFERENCES {$wpdb->prefix}degrees(degreeID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;",
            
            // Specialties
            "Specialties" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}specialties (
                specialtyID INT AUTO_INCREMENT PRIMARY KEY,                
                specialty_name VARCHAR(255)
            ) ENGINE=InnoDB $charset_collate;",

            // Doctor_Specialties
            "Doctor_Specialties" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_specialties (
                specialtyID INT,
                doctorID INT,
                PRIMARY KEY (specialtyID, doctorID),
                FOREIGN KEY (doctorID) REFERENCES {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (specialtyID) REFERENCES {$wpdb->prefix}specialties(specialtyID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;",            

            // Zocdoc
            "Zocdoc" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zocdoc (
                zocdocID INT AUTO_INCREMENT PRIMARY KEY,
                book BOOLEAN,
                book_url VARCHAR(255)
            ) ENGINE=InnoDB $charset_collate;",
            
            "Doctor_Zocdoc" => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}doctor_zocdoc (
                zocdocID INT,
                doctorID INT,                
                PRIMARY KEY (zocdocID, doctorID),
                FOREIGN KEY (zocdocID) REFERENCES  {$wpdb->prefix}zocdoc(zocdocID)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                FOREIGN KEY (doctorID) REFERENCES  {$wpdb->prefix}doctors(doctorID)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB $charset_collate;"
            
        ];

        // Execute each CREATE TABLE statement
        foreach ($tables as $name => $sql) {
            $wpdb->query($sql);
        }

        $wpdb->query("SET foreign_key_checks = 1");
    }


