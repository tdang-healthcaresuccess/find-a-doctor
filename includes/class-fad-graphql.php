<?php
if ( ! defined('ABSPATH') ) { exit; }

add_action('graphql_register_types', function () {

        // --- Physician List Query with Filters ---
    register_graphql_field('RootQuery', 'physicians', [
        'type' => ['list_of' => 'Doctor'],
        'description' => __('Fetch physicians with filtering and pagination','fad-graphql'),
        'args' => [
            'first' => ['type' => 'Int', 'default' => 20],
            'after' => ['type' => 'String'],
            'search' => ['type' => 'String'],
            'specialty' => ['type' => 'String'],
            'location' => ['type' => 'String']
        ],
        'resolve' => function ($root, $args) use ($map_row) {
            global $wpdb;
            
            $limit = min($args['first'] ?? 20, 100);
            $offset = 0;
            
            // Handle cursor-based pagination
            if (!empty($args['after'])) {
                $offset = (int)base64_decode($args['after']);
            }
            
            // Build query conditions
            $where_conditions = [];
            $where_values = [];
            
            if (!empty($args['search'])) {
                $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)";
                $search_term = '%' . $args['search'] . '%';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            if (!empty($args['location'])) {
                $where_conditions[] = "(city LIKE %s OR state LIKE %s)";
                $location_term = '%' . $args['location'] . '%';
                $where_values[] = $location_term;
                $where_values[] = $location_term;
            }
            
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            // Handle specialty filter
            $join_clause = '';
            if (!empty($args['specialty'])) {
                $join_clause = "
                    INNER JOIN {$wpdb->prefix}doctor_specialties ds ON d.doctorID = ds.doctorID
                    INNER JOIN {$wpdb->prefix}specialties s ON ds.specialtyID = s.specialtyID
                ";
                if ($where_clause) {
                    $where_clause .= " AND s.specialty_name LIKE %s";
                } else {
                    $where_clause = "WHERE s.specialty_name LIKE %s";
                }
                $where_values[] = '%' . $args['specialty'] . '%';
            }
            
            $query = "
                SELECT DISTINCT d.* 
                FROM {$wpdb->prefix}doctors d 
                {$join_clause} 
                {$where_clause} 
                ORDER BY d.last_name, d.first_name 
                LIMIT %d OFFSET %d
            ";
            
            $query_values = array_merge($where_values, [$limit, $offset]);
            $rows = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
            
            return array_map($map_row, $rows ?: []);
        },
    ]);
    
    // --- Specialties Query ---
    register_graphql_field('RootQuery', 'specialties', [
        'type' => ['list_of' => 'String'],
        'description' => __('Get all available specialties','fad-graphql'),
        'resolve' => function ($root, $args) {
            global $wpdb;
            return $wpdb->get_col("SELECT DISTINCT specialty_name FROM {$wpdb->prefix}specialties ORDER BY specialty_name");
        },
    ]);
    
    // --- Languages Query ---
    register_graphql_field('RootQuery', 'languages', [
        'type' => ['list_of' => 'String'],
        'description' => __('Get all available languages','fad-graphql'),
        'resolve' => function ($root, $args) {
            global $wpdb;
            return $wpdb->get_col("SELECT DISTINCT language FROM {$wpdb->prefix}languages ORDER BY language");
        },
    ]);

    // --- Hospitals Query ---
    register_graphql_field('RootQuery', 'hospitals', [
        'type' => ['list_of' => 'String'],
        'description' => __('Get all available hospitals','fad-graphql'),
        'resolve' => function ($root, $args) {
            global $wpdb;
            return $wpdb->get_col("SELECT DISTINCT hospital_name FROM {$wpdb->prefix}hospitals ORDER BY hospital_name");
        },
    ]);

    // --- Insurances Query ---
    register_graphql_field('RootQuery', 'insurances', [
        'type' => ['list_of' => 'String'],
        'description' => __('Get all available insurance providers','fad-graphql'),
        'resolve' => function ($root, $args) {
            global $wpdb;
            // Get unique insurance names from the normalized insurance table
            return $wpdb->get_col("SELECT DISTINCT insurance_name FROM {$wpdb->prefix}insurances ORDER BY insurance_name");
        },
    ]);

    // --- Degrees Query ---
    register_graphql_field('RootQuery', 'degrees', [
        'type' => ['list_of' => 'String'],
        'description' => __('Get all available degrees','fad-graphql'),
        'resolve' => function ($root, $args) {
            global $wpdb;
            return $wpdb->get_col("SELECT DISTINCT degree_name FROM {$wpdb->prefix}degrees ORDER BY degree_name");
        },
    ]);
    register_graphql_object_type('Doctor', [
        'description' => __('Doctor profile row from custom tables','fad-graphql'),
        'fields'      => [
            'doctorID'        => ['type' => 'Int'],
            'slug'            => ['type' => 'String'],
            'idme'       =>      ['type' => 'String'],
            'firstName'       => ['type' => 'String'],
            'lastName'        => ['type' => 'String'],
            'degree'          => ['type' => 'String'],
            'email'           => ['type' => 'String'],
            'phoneNumber'     => ['type' => 'String'],
            'faxNumber'       => ['type' => 'String'],
            'primaryCare'     => ['type' => 'Boolean'],
            'gender'          => ['type' => 'String'],
            'medicalSchool'   => ['type' => 'String'],
            'internship'      => ['type' => 'String'],
            'certification'   => ['type' => 'String'],
            'residency'       => ['type' => 'String'],
            'fellowship'      => ['type' => 'String'],
            'biography'       => ['type' => 'String'],
            'practiceName'    => ['type' => 'String'],
            'provStatus'      => ['type' => 'Boolean'],
            'address'         => ['type' => 'String'],
            'city'            => ['type' => 'String'],
            'state'           => ['type' => 'String'],
            'zip'             => ['type' => 'String'],
            'county'          => ['type' => 'String'],
            'latitude'        => ['type' => 'Float'],
            'longitude'       => ['type' => 'Float'],
            'accepts_new_patients' => ['type' => 'Boolean'],
            'accept_medi_cal' => ['type' => 'Boolean'],
            'insurances'      => ['type' => ['list_of' => 'String']],
            'hospitalNames'   => ['type' => 'String'], // Deprecated - use hospitals field
            'hospitals'       => ['type' => ['list_of' => 'String']], // New normalized field
            'mentalHealth'    => ['type' => 'Boolean'],
            'btDirectory'     => ['type' => 'Boolean'],
            'profileImageUrl' => ['type' => 'String'],
            'languages'       => ['type' => ['list_of' => 'String']],
            'specialties'     => ['type' => ['list_of' => 'String']],
            'degrees'         => ['type' => ['list_of' => 'String']],
        ],
    ]);

    // --- helper to map a DB row to Doctor shape (used by multiple resolvers) ---
    $map_row = function(array $row) {
        $doctor_id = (int)($row['doctorID'] ?? 0);
        return [
            'doctorID'        => $doctor_id,
            'slug'            => $row['slug'] ?? '',
            'idme'            => $row['idme'] ?? '',
            'firstName'       => $row['first_name'] ?? '',
            'lastName'        => $row['last_name'] ?? '',
            'degree'          => $row['degree'] ?? '',
            'email'           => $row['email'] ?? '',
            'phoneNumber'     => $row['phone_number'] ?? '',
            'faxNumber'       => $row['fax_number'] ?? '',
            'primaryCare'     => ! empty($row['primary_care']),
            'gender'          => $row['gender'] ?? '',
            'medicalSchool'   => $row['medical_school'] ?? '',
            'internship'      => $row['internship'] ?? '',
            'certification'   => $row['certification'] ?? '',
            'residency'       => $row['residency'] ?? '',
            'fellowship'      => $row['fellowship'] ?? '',
            'biography'       => $row['biography'] ?? '',
            'practiceName'    => $row['practice_name'] ?? '',
            'provStatus'      => ! empty($row['prov_status']),
            'address'         => $row['address'] ?? '',
            'city'            => $row['city'] ?? '',
            'state'           => $row['state'] ?? '',
            'zip'             => $row['zip'] ?? '', 
            'county'          => $row['county'] ?? '',
            'latitude'        => isset($row['latitude']) ? (float)$row['latitude'] : null,
            'longitude'       => isset($row['longitude']) ? (float)$row['longitude'] : null,
            'accepts_new_patients' => ! empty($row['accepts_new_patients']),
            'accept_medi_cal' => ! empty($row['accept_medi_cal']),
            'insurances'      => fad_get_terms_for_doctor('insurance', $doctor_id), 
            'hospitalNames'   => $row['hospitalNames'] ?? ($row['hospital_names'] ?? ''),
            'hospitals'       => fad_get_doctor_hospitals($doctor_id, $row['hospitalNames'] ?? ($row['hospital_names'] ?? '')),
            'mentalHealth'    => ! empty($row['is_ab_directory']),
            'btDirectory'     => ! empty($row['is_bt_directory']),
            'profileImageUrl' => $row['profile_img_url'] ?? '',
            'languages'       => fad_get_terms_for_doctor('language', $doctor_id),
            'specialties'     => fad_get_terms_for_doctor('specialty', $doctor_id),
            'degrees'         => fad_get_terms_for_doctor('degree', $doctor_id),
        ];
    };

    // --- doctorBySlug(slug) ---
    register_graphql_field('RootQuery', 'doctorBySlug', [
        'type'        => 'Doctor',
        'description' => __('Fetch single doctor by SEO slug (firstname-lastname-degree)','fad-graphql'),
        'args'        => [
            'slug' => ['type' => 'String', 'required' => true],
        ],
        'resolve'     => function ($root, $args) use ($map_row) {
            global $wpdb;
            $t = $wpdb->prefix . 'doctors';
            $slug = sanitize_title($args['slug']);
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT d.* FROM {$t} d WHERE d.slug = %s LIMIT 1", $slug),
                ARRAY_A
            );
            if ( ! $row ) return null;
            return $map_row($row);
        },
    ]);

    // === NEW: doctorById(id) ===
    register_graphql_field('RootQuery', 'doctorById', [
        'type'        => 'Doctor',
        'description' => __('Fetch single doctor by numeric ID (doctorID column)','fad-graphql'),
        'args'        => [
            'id' => ['type' => 'Int', 'required' => true],
        ],
        'resolve'     => function ($root, $args) use ($map_row) {
            global $wpdb;
            $t = $wpdb->prefix . 'doctors';
            $id = (int)$args['id'];
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT d.* FROM {$t} d WHERE d.doctorID = %d LIMIT 1", $id),
                ARRAY_A
            );
            if ( ! $row ) return null;
            return $map_row($row);
        },
    ]);

    // --- DoctorList wrapper type ---
    register_graphql_object_type('DoctorList', [
        'fields' => [
            'items'   => ['type' => ['list_of' => 'Doctor']],
            'total'   => ['type' => 'Int'],
            'page'    => ['type' => 'Int'],
            'perPage' => ['type' => 'Int'],
        ],
    ]);

    // --- doctors(...) list with filters ---
    register_graphql_field('RootQuery', 'doctorsList', [
        'type'        => 'DoctorList',
        'description' => __('Paginated doctors with common filters (search, specialties array, language, gender, degree, insurance, primaryCare)','fad-graphql'),
        'args'        => [
            'search'      => ['type' => 'String'],
            'specialty'   => ['type' => ['list_of' => 'String']],
            'language'    => ['type' => 'String'],
            'gender'      => ['type' => 'String'],
            'primaryCare' => ['type' => 'Boolean'],
            'degree'      => ['type' => 'String'],
            'insurance'   => ['type' => 'String'],
            'page'        => ['type' => 'Int'],
            'perPage'     => ['type' => 'Int'],
            'orderBy'     => ['type' => 'String'], // first_name|last_name|degree|practice_name
            'order'       => ['type' => 'String'], // ASC|DESC
        ],
        'resolve'     => function ($root, $args) use ($map_row) {
            global $wpdb;
            $t_doctors   = $wpdb->prefix . 'doctors';
            $t_doc_spec  = $wpdb->prefix . 'doctor_specialties';
            $t_specs     = $wpdb->prefix . 'specialties';
            $t_doc_lang  = $wpdb->prefix . 'doctor_language';
            $t_lang      = $wpdb->prefix . 'languages';
            $t_doc_deg   = $wpdb->prefix . 'doctor_degrees';
            $t_degrees   = $wpdb->prefix . 'degrees';
            $t_doc_ins   = $wpdb->prefix . 'doctor_insurance';
            $t_insurance = $wpdb->prefix . 'insurances';

            $where  = [];
            $params = [];
            $join   = [];

            if (!empty($args['search'])) {
                $like = '%' . $wpdb->esc_like($args['search']) . '%';
                $where[]  = '(d.first_name LIKE %s OR d.last_name LIKE %s OR d.practice_name LIKE %s)';
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
            if (isset($args['primaryCare'])) {
                $where[]  = 'd.primary_care = %d';
                $params[] = !empty($args['primaryCare']) ? 1 : 0;
            }
            if (!empty($args['gender'])) {
                $where[]  = 'd.gender = %s';
                $params[] = $args['gender'];
            }

            if (!empty($args['specialty'])) {
                $join[]   = "INNER JOIN {$t_doc_spec} ds ON ds.doctorID = d.doctorID";
                $join[]   = "INNER JOIN {$t_specs} s ON s.specialtyID = ds.specialtyID";
                
                // Handle array of specialties
                if (is_array($args['specialty'])) {
                    $specialty_placeholders = array_fill(0, count($args['specialty']), 'LOWER(s.specialty_name) = LOWER(%s)');
                    $where[] = '(' . implode(' OR ', $specialty_placeholders) . ')';
                    foreach ($args['specialty'] as $specialty) {
                        $params[] = $specialty;
                    }
                } else {
                    // Backwards compatibility: single specialty as string
                    $where[]  = 'LOWER(s.specialty_name) = LOWER(%s)';
                    $params[] = $args['specialty'];
                }
            }
            if (!empty($args['language'])) { 
                $join[]   = "INNER JOIN {$t_doc_lang} dl ON dl.doctorID = d.doctorID";
                $join[]   = "INNER JOIN {$t_lang} l ON l.languageID = dl.languageID";
                $where[]  = 'LOWER(l.language) = LOWER(%s)';
                $params[] = $args['language'];
            }
            if (!empty($args['degree'])) {
                $join[]   = "INNER JOIN {$t_doc_deg} dd ON dd.doctorID = d.doctorID";
                $join[]   = "INNER JOIN {$t_degrees} deg ON deg.degreeID = dd.degreeID";
                $where[]  = 'LOWER(deg.degree_name) = LOWER(%s)';
                $params[] = $args['degree'];
            }
            if (!empty($args['insurance'])) {
                $join[]   = "INNER JOIN {$t_doc_ins} di ON di.doctorID = d.doctorID";
                $join[]   = "INNER JOIN {$t_insurance} ins ON ins.insuranceID = di.insuranceID";
                $where[]  = 'LOWER(ins.insurance_name) = LOWER(%s)';
                $params[] = $args['insurance'];
            }

            $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $join_sql  = $join ? implode(' ', $join) : '';

            $order_by = 'd.last_name';
            $order    = 'ASC';
            if (!empty($args['orderBy']) && in_array($args['orderBy'], ['first_name','last_name','degree','practice_name'], true)) {
                $order_by = 'd.' . $args['orderBy'];
            }
            if (!empty($args['order']) && in_array(strtoupper($args['order']), ['ASC','DESC'], true)) {
                $order = strtoupper($args['order']);
            }

            $page    = max(1, (int)($args['page'] ?? 1));
            $perPage = min(50, max(1, (int)($args['perPage'] ?? 20)));
            $offset  = ($page - 1) * $perPage;

            $sql_count = "SELECT COUNT(DISTINCT d.doctorID)
                          FROM {$t_doctors} d
                          {$join_sql}
                          {$where_sql}";
            $total = (int)$wpdb->get_var($wpdb->prepare($sql_count, $params));

            $sql_items = "SELECT DISTINCT d.*
                          FROM {$t_doctors} d
                          {$join_sql}
                          {$where_sql}
                          ORDER BY {$order_by} {$order}
                          LIMIT %d OFFSET %d";

            $rows = $wpdb->get_results(
                $wpdb->prepare($sql_items, array_merge($params, [$perPage, $offset])),
                ARRAY_A
            );

            $items = array_map($map_row, $rows ?? []);

            return [
                'items'   => $items,
                'total'   => $total,
                'page'    => $page,
                'perPage' => $perPage,
            ];
        },
    ]);
});
