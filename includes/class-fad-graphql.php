<?php
if ( ! defined('ABSPATH') ) { exit; }

add_action('graphql_register_types', function () {

    // --- Doctor type ---
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
            'insurances'      => ['type' => 'String'],
            'hospitalNames'   => ['type' => 'String'],
            'mentalHealth'    => ['type' => 'Boolean'],
            'btDirectory'     => ['type' => 'Boolean'],
            'profileImageUrl' => ['type' => 'String'],
            'languages'       => ['type' => ['list_of' => 'String']],
            'specialties'     => ['type' => ['list_of' => 'String']],
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
            'insurances'      => $row['insurances'] ?? ($row['Insurances'] ?? ''), 
            'hospitalNames'   => $row['hospitalNames'] ?? ($row['hospital_names'] ?? ''),
            'mentalHealth'    => ! empty($row['is_ab_directory']),
            'btDirectory'     => ! empty($row['is_bt_directory']),
            'profileImageUrl' => $row['profile_img_url'] ?? '',
            'languages'       => fad_get_terms_for_doctor('language', $doctor_id),
            'specialties'     => fad_get_terms_for_doctor('specialty', $doctor_id),
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
        'description' => __('Paginated doctors with common filters','fad-graphql'),
        'args'        => [
            'search'      => ['type' => 'String'],
            'specialty'   => ['type' => 'String'],
            'language'    => ['type' => 'String'],
            'gender'      => ['type' => 'String'],
            'primaryCare' => ['type' => 'Boolean'],
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
                $where[]  = 'LOWER(s.specialty_name) = LOWER(%s)';
                $params[] = $args['specialty'];
            }
            if (!empty($args['language'])) { 
                $join[]   = "INNER JOIN {$t_doc_lang} dl ON dl.doctorID = d.doctorID";
                $join[]   = "INNER JOIN {$t_lang} l ON l.languageID = dl.languageID";
                $where[]  = 'LOWER(l.language) = LOWER(%s)';
                $params[] = $args['language'];
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
