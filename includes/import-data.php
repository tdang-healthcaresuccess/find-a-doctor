<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function fnd_import_page() {
    ?>
    <div class="wrap">
        <h2>Import Doctor Data</h2>
        <form id="import-doctor-form" method="post" enctype="multipart/form-data">
            <input type="file" name="doctor_file" accept=".csv,.xlsx,.xls" required>
            <input type="submit" name="submit_doctor_upload" class="button-primary" value="Upload">
        </form>

        <div id="fnd-import-overlay">
            <div class="spinner"></div>
            <p style="color:#fff; font-size:16px; margin-top:20px;">Importing data, please wait...</p>
        </div>
        <div id="fnd-import-result"></div>
    </div>
    <?php
}


function combine_from_columns($data, $indexes) {
    $values = [];
    foreach ($indexes as $i) {
        if ($i === false) continue; // skip missing headers
        $val = trim($data[$i] ?? '');
        if ($val !== '') $values[] = $val;
    }
    return implode(', ', $values);
}


function fnd_handle_doctor_upload($file) {
    global $wpdb;

    $file_path = $file['tmp_name'];
    $original_name = $file['name'];
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $rows = [];
    $header = [];

    if ($file_ext === 'csv') {
        if (($handle = fopen($file_path, 'r')) !== false) {
            $header = fgetcsv($handle); // Get headers
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
    } elseif (in_array($file_ext, ['xlsx', 'xls'])) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();
        $header = array_map('trim', $data[0]);
        $rows = array_slice($data, 1);
    } else {
        echo "<div class='error'><p>Unsupported file type. Upload CSV or Excel.</p></div>";
        return;
    }

    $header = array_map('trim', $header);

    $col_map_doctor = [
            'atlas_primary_key'  => array_search('ProviderKey', $header),
            'idme'  => array_search('IDME', $header),
            'first_name' =>  array_search('FirstName', $header),
            'last_name'  =>  array_search('Last Name', $header),
            'email'      => array_search('Email Address', $header),
            'phone_number' => array_search('Phone', $header),
            'fax_number' => array_search('Fax', $header),
            'primary_care' => array_search('Primary Care Provider', $header),
            'degree' => array_search('Degree', $header),
            'gender' => array_search('Sex', $header),
            'medical_school' => array_search('Medical School', $header),
            'practice_name' => array_search('Practice Name', $header),
            'prov_status' => array_search('ProvStatus', $header),
            'address' =>array_search('Address', $header),
            'city' => array_search('City', $header),
            'state' => array_search('State', $header),
            'zip' => array_search('Zip', $header),
            'county' => array_search('County', $header),
            'is_ab_directory' => array_search('Mental Health', $header),
            'is_bt_directory' => array_search('BT Directory', $header),
            'aco_active_networks' => array_search('ACO Networks List', $header),
            'ppo_active_network' => array_search('PPO Networks List', $header),

            'internship' => [
                array_search('Internship', $header),
                array_search('Internship Institution', $header),
            ],
            'certification' => [
                array_search('Board Cert 1', $header),
                array_search('Board Cert 2', $header),
                array_search('Board Cert 3', $header),
            ],
            'residency' => [
                array_search('Residency', $header),
                array_search('Residency Institution', $header),
            ],
            'fellowship' => [
                array_search('Fellowship', $header),
                array_search('Fellowship Institution', $header),
            ],
            'Insurances' => [
                 array_search('ACO Networks List', $header),
                 array_search('PPO Networks List', $header),
            ],
            'hospitalNames' => [
                array_search('Hosp Aff1', $header),
                array_search('Hosp Aff2', $header),
                array_search('Hosp Aff3', $header),
                array_search('Hosp Aff4', $header),
                array_search('Hosp Aff5', $header),
                array_search('Hosp Aff6', $header),
                array_search('Hosp Aff7', $header),
                array_search('Hosp Aff8', $header),
                array_search('Hosp Aff9', $header),
            ],
    ];

    $col_map_language = [
        'Provider Languages' => array_search('Provider Languages', $header),
    ];

    $col_map_specialties = [
        'Specialties1'        =>  array_search('Spec1', $header),
        'Specialties2'        =>  array_search('Spec2', $header),
        'Specialties3'        =>  array_search('Spec3', $header),
    ];

     

    $language_cache = [];
    $specialty_cache = [];
    $insert_language_relations = [];
    $insert_specialty_relations = [];
    $imported = 0;


    foreach ($rows as $data) {
        if (empty(array_filter($data))) continue;

        //  Skip if ProvStatus is "Terminated"
        $status = strtolower(trim($data[$col_map_doctor['prov_status']] ?? ''));
        if ($status === 'terminated') continue;


        //  This are required field during import data
        $required_fields = [
            'address' => $col_map_doctor['address'] ?? false,
            'city'    => $col_map_doctor['city'] ?? false,
            'state'   => $col_map_doctor['state'] ?? false,
            'zip'     => $col_map_doctor['zip'] ?? false,
            'county'  => $col_map_doctor['county'] ?? false,
        ];

        $missing = false;
        foreach ($required_fields as $field => $index) {
            if ($index === false || !isset($data[$index])) {
                $missing = true;
                break;
            }

            $value = trim($data[$index]);
            if ($value === '') {
                $missing = true;
                break;
            }
        }

        if ($missing) {
            continue; // Skip this row if any required field is missing or empty
        }
         
         
        // Check if doctor exists by idme
        $doctor_id = $wpdb->get_var($wpdb->prepare(
            "SELECT doctorID FROM {$wpdb->prefix}doctors WHERE idme = %s", $data[$col_map_doctor['idme']]
        ));

        if ($doctor_id) {
            continue; 
        }

         // Insert doctor if not exists
        $boolean_fields = ['is_ab_directory', 'is_bt_directory','primary_care'];
        $doctor_data = [];
        foreach ($col_map_doctor as $column => $index) {
              if (is_array($index)) {
                $doctor_data[$column] = combine_from_columns($data, $index);
            } else {
                $value = trim($data[$index] ?? '');
                if (in_array($column, $boolean_fields)) {
                    $doctor_data[$column] = (strtolower($value) === 'yes') ? 1 : 0;
                } else {
                    $doctor_data[$column] = $value;
                }
            }
        }
        $wpdb->insert("{$wpdb->prefix}doctors", $doctor_data);

        $doctor_id = $wpdb->insert_id;
        fad_upsert_slug_for_doctor($doctor_id, true);
         $imported++;
    
          //  Languages code here 
        $languages_str = $data[$col_map_language['Provider Languages']] ?? '';
        if (!empty($languages_str)) {
            $languages = explode(',', $languages_str);
            foreach ($languages as $lang) {
                $lang = trim($lang);
                if ($lang === '') continue;

                if (!isset($language_cache[$lang])) {
                    $language_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT languageID FROM {$wpdb->prefix}languages WHERE language = %s", $lang
                    ));
                    if (!$language_id) {
                        $wpdb->insert("{$wpdb->prefix}languages", ['language' => $lang]);
                        $language_id = $wpdb->insert_id;
                    }
                    $language_cache[$lang] = $language_id;
                }
                $insert_language_relations[] = $wpdb->prepare("(%d, %d)", $doctor_id, $language_cache[$lang]);
            }
        }


        //  Specialtie code here 
        foreach (['Specialties1', 'Specialties2', 'Specialties3'] as $spec_key) {
            $spec_str = $data[$col_map_specialties[$spec_key]] ?? '';
            if (empty($spec_str)) continue;

            $specialties = explode(',', $spec_str);
            foreach ($specialties as $spec) {
                $spec = trim($spec);
                if ($spec === '') continue;

                if (!isset($specialty_cache[$spec])) {
                    $spec_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT specialtyID FROM {$wpdb->prefix}specialties WHERE specialty_name = %s", $spec
                    ));
                    if (!$spec_id) {
                        $wpdb->insert("{$wpdb->prefix}specialties", ['specialty_name' => $spec]);
                        $spec_id = $wpdb->insert_id;
                    }
                    $specialty_cache[$spec] = $spec_id;
                }

                $insert_specialty_relations[] = $wpdb->prepare("(%d, %d)", $doctor_id, $specialty_cache[$spec]);
            }
        }

    }

     // Batch insert doctor_languages ===
    if (!empty($insert_language_relations)) {
        $values_sql = implode(',', $insert_language_relations);
        $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}doctor_language (doctorID, languageID) VALUES $values_sql");
    }

    // Batch insert doctor_specialties
    if (!empty($insert_specialty_relations)) {
        $values_sql = implode(',', $insert_specialty_relations);
        $wpdb->query("INSERT IGNORE INTO {$wpdb->prefix}doctor_specialties (doctorID, specialtyID) VALUES $values_sql");
    }

    echo "<div class='updated'><p><strong>$imported doctor(s) processed.</strong><br></p></div>";
}

?>




