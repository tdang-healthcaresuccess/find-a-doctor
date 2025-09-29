<?php
function fnd_render_doctor_edit_page() {
    global $wpdb;
    $doctors_table = $wpdb->prefix . 'doctors';
    $languages_table      = $wpdb->prefix . 'languages';      
    $specialties_table    = $wpdb->prefix . 'specialties';     
    $doctor_lang_table    = $wpdb->prefix . 'doctor_language';
    $doctor_spec_table    = $wpdb->prefix . 'doctor_specialties';

    $doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $languages = $wpdb->get_results("SELECT languageID, language FROM {$languages_table} ORDER BY language ASC");
    $specialties = $wpdb->get_results("SELECT specialtyID, specialty_name FROM {$specialties_table} ORDER BY specialty_name ASC");
  
    $doctor_language_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT languageID FROM {$doctor_lang_table} WHERE doctorID = %d", $doctor_id
    ));
    $doctor_specialty_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT specialtyID  FROM {$doctor_spec_table} WHERE doctorID = %d", $doctor_id
    ));

    $doctor_language_ids  = array_map('intval', (array)$doctor_language_ids);
    $doctor_specialty_ids = array_map('intval', (array)$doctor_specialty_ids);


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fnd_update_doctor'])) {

        $ab_raw = isset($_POST['is_ab_directory']) ? $_POST['is_ab_directory'] : '';
        $bt_raw = isset($_POST['is_bt_directory']) ? $_POST['is_bt_directory'] : '';

        $is_ab_directory = ($ab_raw === '1') ? 1 : (($ab_raw === '0') ? 0 : null);
        $is_bt_directory = ($bt_raw === '1') ? 1 : (($bt_raw === '0') ? 0 : null);

        $t  = fn($k) => isset($_POST[$k]) ? sanitize_text_field( wp_unslash($_POST[$k]) ) : '';
        $ta = fn($k) => isset($_POST[$k]) ? sanitize_textarea_field( wp_unslash($_POST[$k]) ) : '';


        $update_data = [
        'first_name'          => $t('first_name'),
        'last_name'           => $t('last_name'),
        'email'               => sanitize_email( isset($_POST['email']) ? wp_unslash($_POST['email']) : '' ),
        'phone_number'        => $t('phone_number'),
        'fax_number'          => $t('fax_number'),
        'gender'              => $t('gender'),
        'degree'              => $t('degree'),
        'medical_school'      => $t('medical_school'),
        'practice_name'       => $t('practice_name'),
        'prov_status'         => $t('prov_status'),
        'address'             => $t('address'),
        'city'                => $t('city'),
        'state'               => $t('state'),
        'zip'                 => $t('zip'),
        'county'              => $t('county'),
        'is_ab_directory'     => $t('is_ab_directory'),
        'is_bt_directory'     => $t('is_bt_directory'),
        'aco_active_networks' => $t('aco_active_networks'),
        'ppo_active_network'  => $t('ppo_active_network'),

        // TEXTAREAS → unslash first, then sanitize
        'internship'          => $ta('internship'),
        'certification'       => $ta('certification'),
        'residency'           => $ta('residency'),
        'fellowship'          => $ta('fellowship'),
        'Insurances'          => $ta('Insurances'),
        'hospitalNames'       => $ta('hospitalNames'),
        'biography'           => $ta('biography'),
    ];


        if (!empty($_POST['remove_profile_img']) && $_POST['remove_profile_img'] === '1') {
            $update_data['profile_img_url'] = '';
        }
        
        elseif (!empty($_FILES['profile_img']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            if (current_user_can('upload_files')) {
                $attachment_id = media_handle_upload('profile_img', 0);
                if (!is_wp_error($attachment_id)) {
                    $update_data['profile_img_url'] = esc_url_raw(wp_get_attachment_url($attachment_id));
                } else {
                 echo '<div class="notice notice-error"><p>Image upload failed: ' .
                         esc_html($attachment_id->get_error_message()) . '</p></div>';
                }
            }
        }
          $wpdb->update($doctors_table, $update_data, ['doctorID' => $doctor_id]);
          // update slug
          fad_upsert_slug_for_doctor($doctor_id, true);
        if (!isset($_POST['fnd_update_doctor_nonce']) || !wp_verify_nonce($_POST['fnd_update_doctor_nonce'], 'fnd_update_doctor')) {
            wp_die('Security check failed');
        }

        $new_language_ids  = isset($_POST['languages'])   ? array_map('intval', (array) $_POST['languages'])   : [];
        $new_specialty_ids = isset($_POST['specialties']) ? array_map('intval', (array) $_POST['specialties']) : [];

        // Replace strategy: delete existing, insert new (simple & safe)
        $wpdb->delete($doctor_lang_table, ['doctorID' => $doctor_id], ['%d']);
        $wpdb->delete($doctor_spec_table, ['doctorID' => $doctor_id], ['%d']);

        foreach ($new_language_ids as $lang_id) {
            if ($lang_id > 0) {
                $wpdb->insert($doctor_lang_table, ['doctorID' => $doctor_id, 'languageID' => $lang_id], ['%d','%d']);
            }
        }
        foreach ($new_specialty_ids as $spec_id) {
            if ($spec_id > 0) {
                $wpdb->insert($doctor_spec_table, ['doctorID' => $doctor_id, 'specialtyID' => $spec_id], ['%d','%d']);
            }
        }

        $redirect_url = admin_url('admin.php?page=doctor-list&updated=1');
        if (!headers_sent()) {
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            echo "<script>window.location.href='" . esc_url($redirect_url) . "';</script>";
            exit;
        }
    }

    $doctor = $wpdb->get_row($wpdb->prepare("SELECT * FROM $doctors_table WHERE doctorID = %d", $doctor_id));
    if (!$doctor) {
        echo '<div class="notice notice-error"><p>Doctor not found.</p></div>';
        return;
    }

    $ab_current     = is_null($doctor->is_ab_directory) ? '' : (string)(int)$doctor->is_ab_directory; 
    $bt_current     = is_null($doctor->is_bt_directory) ? '' : (string)(int)$doctor->is_bt_directory; 

    ?>
    <div class="wrap">
        <h1>Edit Doctor</h1>
        <form method="post" enctype="multipart/form-data">
            <!-- Two-column inputs -->
            <div class="fnd-grid">
                <div class="fnd-field">
                    <label for="first_name">First Name</label>
                    <input id="first_name" name="first_name" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->first_name); ?>">
                </div>
                <div class="fnd-field">
                    <label for="last_name">Last Name</label>
                    <input id="last_name" name="last_name" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->last_name); ?>">
                </div>

                <div class="fnd-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->email); ?>">
                </div>
                <div class="fnd-field">
                    <label for="phone_number">Phone Number</label>
                    <input id="phone_number" name="phone_number" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->phone_number); ?>">
                </div>

                <div class="fnd-field">
                    <label for="fax_number">Fax Number</label>
                    <input id="fax_number" name="fax_number" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->fax_number); ?>">
                </div>
                <div class="fnd-field">
                    <label for="degree">Degree</label>
                    <input id="degree" name="degree" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->degree); ?>">
                </div>

                <div class="fnd-field">
                    <label for="gender">Gender</label>
                   <input id="gender" name="gender" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->gender); ?>">
                </div>
                <div class="fnd-field">
                    <label for="medical_school">Medical School</label>
                     <input id="medical_school" name="medical_school" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->medical_school); ?>">
                </div>

                <div class="fnd-field">
                    <label for="practice_name">Practice Name</label>
                    <input id="practice_name" name="practice_name" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->practice_name); ?>">
                </div>
                <div class="fnd-field">
                    <label for="prov_status">ProvStatus</label>
                    <input id="prov_status" name="prov_status" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->prov_status); ?>">
                </div>

                <div class="fnd-field">
                    <label for="address">Address</label>
                    <input id="address" name="address" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->address); ?>">
                </div>
                <div class="fnd-field">
                    <label for="city">City</label>
                    <input id="city" name="city" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->city); ?>">
                </div>

                <div class="fnd-field">
                    <label for="state">State</label>
                    <input id="state" name="state" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->state); ?>">
                </div>
                <div class="fnd-field">
                    <label for="zip">Zip</label>
                    <input id="zip" name="zip" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->zip); ?>">
                </div>

                <div class="fnd-field">
                    <label for="county">County</label>
                    <input id="county" name="county" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->county); ?>">
                </div>
                <div class="fnd-field">
                    <label for="is_ab_directory">Mental Health</label>
                     <select id="is_ab_directory" name="is_ab_directory" class="regular-text">
                    <option value=""  <?php selected($ab_current, '');  ?>>Select</option>
                    <option value="1" <?php selected($ab_current, '1'); ?>>Yes</option>
                    <option value="0" <?php selected($ab_current, '0'); ?>>No</option>
                </select>
                </div>

                <div class="fnd-field">
                    <label for="is_bt_directory">BT Directory</label>
                    <select id="is_bt_directory" name="is_bt_directory" class="regular-text">
                        <option value=""  <?php selected($bt_current, '');  ?>>Select</option>
                        <option value="1" <?php selected($bt_current, '1'); ?>>Yes</option>
                        <option value="0" <?php selected($bt_current, '0'); ?>>No</option>
                    </select>
                </div>
                <div class="fnd-field">
                    <label for="aco_active_networks">ACO Networks List</label>
                    <input id="aco_active_networks" name="aco_active_networks" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->aco_active_networks); ?>">
                </div>

                <div class="fnd-field">
                    <label for="ppo_active_network">PPO Networks List</label>
                    <input id="ppo_active_network" name="ppo_active_network" type="text" class="regular-text"
                           value="<?php echo esc_attr($doctor->ppo_active_network); ?>">
                </div>
                <!-- If you ever need a spacer to keep pairs aligned:
                <div></div>
                -->
            </div>

            <!-- Full-width textareas -->
            <div class="fnd-textareas">
                <div class="fnd-field">
                    <label for="internship">Internship</label>
                    <textarea id="internship" name="internship" rows="3" class="large-text"><?php echo esc_textarea($doctor->internship); ?></textarea>
                </div>
                <div class="fnd-field">
                    <label for="certification">Certification</label>
                    <textarea id="certification" name="certification" rows="3" class="large-text"><?php echo esc_textarea($doctor->certification); ?></textarea>
                </div>
                <div class="fnd-field">
                    <label for="residency">Residency</label>
                    <textarea id="residency" name="residency" rows="3" class="large-text"><?php echo esc_textarea($doctor->residency); ?></textarea>
                </div>
                <div class="fnd-field">
                    <label for="fellowship">Fellowship</label>
                    <textarea id="fellowship" name="fellowship" rows="3" class="large-text"><?php echo esc_textarea($doctor->fellowship); ?></textarea>
                </div>
                <div class="fnd-field">
                    <label for="Insurances">Insurances</label>
                    <textarea id="Insurances" name="Insurances" rows="3" class="large-text"><?php echo esc_textarea($doctor->Insurances); ?></textarea>
                </div>
                <div class="fnd-field">
                    <label for="hospitalNames">Hospital Names</label>
                    <textarea id="hospitalNames" name="hospitalNames" rows="3" class="large-text"><?php echo esc_textarea($doctor->hospitalNames); ?></textarea>
                </div>
                 <div class="fnd-field">
                    <label for="hospitalNames">Biography</label>
                    <textarea id="biography" name="biography" rows="5" class="large-text"><?php echo esc_textarea($doctor->biography); ?></textarea>
                </div>
            </div>

                <?php
                // Prepare values for selected() helper
                $doctor_language_ids_map  = array_fill_keys($doctor_language_ids, true);
                $doctor_specialty_ids_map = array_fill_keys($doctor_specialty_ids, true);
                ?>

                <!-- Languages (multi-select) -->
            <div class="fnd-multilselect">
                <div class="fnd-field">
                    <label for="languages">Languages</label>
                    <select id="languages" name="languages[]" class="regular-text fnd-multi" multiple>
                        <?php if (!empty($languages)): ?>
                            <?php foreach ($languages as $lang): 
                                $lang_id = (int) $lang->languageID;
                               $is_selected = in_array($lang_id, $doctor_language_ids, true);
                            ?>
                                <option value="<?php echo esc_attr($lang_id); ?>" <?php echo $is_selected ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html($lang->language); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option disabled>(No languages found)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Specialties (multi-select) -->
                <div class="fnd-field">
                    <label for="specialties">Specialties</label>
                    <select id="specialties" name="specialties[]" class="regular-text fnd-multi" multiple>
                        <?php if (!empty($specialties)): ?>
                            <?php foreach ($specialties as $spec): 
                                $spec_id = (int) $spec->specialtyID;
                                $is_selected = isset($doctor_specialty_ids_map[$spec_id]);
                            ?>
                                <option value="<?php echo esc_attr($spec_id); ?>" <?php selected($is_selected, true); ?>>
                                    <?php echo esc_html($spec->specialty_name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option disabled>(No specialties found)</option>
                        <?php endif; ?>
                    </select>
                </div>
             </div>
             <?php $current_img = !empty($doctor->profile_img_url) ? esc_url($doctor->profile_img_url) : ''; ?>

            <div class="fnd-field" style="grid-column: 1 / -1;">
                <label>Profile Image</label>
                <div class="fnd-img-row">
                    <div class="fnd-img-preview">
                        <?php if ($current_img): ?>
                            <img id="fnd-profile-preview" src="<?php echo $current_img; ?>" alt="Profile image" />
                        <?php else: ?>
                            <img id="fnd-profile-preview" src="<?php echo esc_url(includes_url('images/media/default.png')); ?>" alt="No image" />
                        <?php endif; ?>
                    </div>
                    <div class="fnd-img-controls">
                        <input type="file" id="profile_img" name="profile_img" accept="image/*" />
                        <label style="display:block;margin-top:8px;">
                            <input type="checkbox" name="remove_profile_img" value="1">
                            Remove current image
                        </label>
                        <?php if ($current_img): ?>
                            <div style="margin-top:6px;">
                                <a href="<?php echo $current_img; ?>" target="_blank" rel="noopener">Open current image</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php wp_nonce_field('fnd_update_doctor', 'fnd_update_doctor_nonce'); ?>
            <?php submit_button('Update Doctor', 'primary', 'fnd_update_doctor'); ?>
        </form>
    </div>
<?php
}
?>
