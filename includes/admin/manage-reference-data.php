<?php
function fnd_render_reference_data_page() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    $tables = [
        'languages' => ['name' => 'Languages', 'key' => 'languageID', 'fields' => ['language' => 'text']],
        'specialties' => ['name' => 'Specialties', 'key' => 'specialtyID', 'fields' => ['specialty_name' => 'text']],
        'insurances' => ['name' => 'Insurances', 'key' => 'insuranceID', 'fields' => ['insurance_name' => 'text', 'insurance_type' => 'select']],
        'hospitals' => ['name' => 'Hospitals', 'key' => 'hospitalID', 'fields' => ['hospital_name' => 'text']],
        'zocdoc' => ['name' => 'Zocdoc', 'key' => 'zocdocID', 'fields' => ['zocdoc_name' => 'text']],
        // 'tags' => ['name' => 'Tags', 'key' => 'tagID', 'fields' => ['tag_name' => 'text']],
        // 'aco_active_networks' => ['name' => 'ACO Networks', 'key' => 'acoID', 'fields' => ['acoName' => 'text']],
        // 'ppo_active_network' => ['name' => 'PPO Networks', 'key' => 'ppo_networkID', 'fields' => ['ppo_name' => 'text']],
        // 'hmo_active_networks' => ['name' => 'HMO Networks', 'key' => 'hmoID', 'fields' => ['hmoName' => 'text']],
        // 'zocdoc_affiliation' => ['name' => 'Zocdoc Affiliation', 'key' => 'zocdocID', 'fields' => ['book' => 'checkbox', 'book_url' => 'text']],
        // 'directory' => ['name' => 'Directory', 'key' => 'directoryID', 'fields' => ['is_ab_directory' => 'checkbox', 'is_bt_directory' => 'checkbox']],
    ];

    $selected = $_GET['ref_table'] ?? 'languages';
    if (!isset($tables[$selected])) $selected = 'languages';
    $meta = $tables[$selected];



    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $search = sanitize_text_field($_GET['search'] ?? '');
    $where = '';
    $where_args = [];

     foreach ($meta['fields'] as $field => $type) {
        if ($type === 'text' && $search !== '') {
            $where .= ($where ? ' OR ' : 'WHERE ') . "$field LIKE %s";
            $where_args[] = '%' . $wpdb->esc_like($search) . '%';
        }
    }


    $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}{$selected}");
    $total_pages = ceil($total_rows / $per_page);

   $count_sql = "SELECT COUNT(*) FROM {$prefix}{$selected} " . ($where ? $wpdb->prepare(" $where", ...$where_args) : '');
    $total = $wpdb->get_var($count_sql);
    $pages = ceil($total / $per_page);

    $query = "SELECT * FROM {$prefix}{$selected} " . ($where ? $wpdb->prepare(" $where", ...$where_args) : '') . 
             $wpdb->prepare(" ORDER BY {$meta['key']} DESC LIMIT %d OFFSET %d", $per_page, $offset);
    $rows = $wpdb->get_results($query);

    // Delete
    if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
        $wpdb->delete($prefix . $selected, [$meta['key'] => intval($_GET['delete_id'])]);
        echo "<script>window.location.href='?page=manage-reference-data&ref_table=$selected';</script>";
        exit;
    }

    // Save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fnd_save_table'])) {
        $id = intval($_POST['record_id']);
        $data = [];
        foreach ($meta['fields'] as $field => $type) {
            if ($type === 'checkbox') {
                $data[$field] = isset($_POST[$field]) ? 1 : 0;
            } else {
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
         // Build duplicate check condition
        $where_clause = [];
        $where_values = [];
        foreach ($meta['fields'] as $field => $type) {
            $where_clause[] = "$field = %s";
            $where_values[] = $data[$field];
        }
        $where_sql = implode(' AND ', $where_clause);

        // Skip self in case of update
        if ($id > 0) {
            $where_sql .= " AND {$meta['key']} != %d";
            $where_values[] = $id;
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}{$selected} WHERE $where_sql",
            ...$where_values
        ));

        if ($existing > 0) {
            echo "<div class='error'><p><strong>Error:</strong> Record with same value already exists.</p></div>";
        } else {
            if ($id > 0) {
                $wpdb->update($prefix . $selected, $data, [$meta['key'] => $id]);
                echo "<div class='updated'><p>{$meta['name']} updated successfully.</p></div>";
            } else {
                $wpdb->insert($prefix . $selected, $data);
                echo "<div class='updated'><p>{$meta['name']} added successfully.</p></div>";
            }

            // Refresh data
            $rows = $wpdb->get_results("SELECT * FROM {$prefix}{$selected} ORDER BY {$meta['key']} DESC");
        }
        echo "<script>window.location.href='?page=manage-reference-data&ref_table=$selected';</script>";
        exit;
    }

    echo '<div class="wrap"><h1>Manage Reference Data</h1>';
        add_thickbox();
    echo '<form method="get" style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">';
    echo '<input type="hidden" name="page" value="manage-reference-data">';
    echo '<label><strong>Select Table:</strong></label> <select name="ref_table" onchange="this.form.submit()">';
    foreach ($tables as $key => $val) {
        $sel = $selected === $key ? 'selected' : '';
        echo "<option value='$key' $sel>{$val['name']}</option>";
    }
    echo '</select>';
    echo '<input type="text" name="search" value="' . esc_attr($search) . '" placeholder="Search..." />';
    echo '<button type="submit" class="button">Search</button>';
    echo '</form>';

    echo "<h2>{$meta['name']}</h2>";
    echo "<button class='button add-modal' data-type='$selected' data-fields='" . esc_attr(json_encode($meta['fields'])) . "' data-key='{$meta['key']}'>+ Add {$meta['name']}</button>";
    echo "<button class='button button-secondary' id='sync-reference-data' style='margin-left: 10px;'>Sync from API</button>";
    echo "<div id='sync-result' style='margin-top: 10px;'></div>";
    echo "<table class='wp-list-table widefat striped'><thead><tr><th>ID</th>";
    foreach ($meta['fields'] as $field => $type) echo "<th>" . ucwords(str_replace('_', ' ', $field)) . "</th>";
    echo "<th>Actions</th></tr></thead><tbody>";

    if (empty($rows)) {
    echo '<tr><td colspan="' . (count($meta['fields']) + 2) . '" style="text-align:center;"><em>No data found.</em></td></tr>';
   }
   else
   {
        foreach ($rows as $row) {
            echo "<tr><td>{$row->{$meta['key']}}</td>";
            foreach ($meta['fields'] as $field => $type) {
                $val = $type === 'checkbox' ? ($row->$field ? 'Yes' : 'No') : esc_html($row->$field);
                echo "<td>{$val}</td>";
            }
            echo "<td><button class='button edit-modal' 
            data-type='$selected' 
            data-key='{$meta['key']}' 
            data-id='{$row->{$meta['key']}}' 
            data-fields='" . esc_attr(json_encode($meta['fields'])) . "'";

            foreach ($meta['fields'] as $field => $type) {
                $val = $type === 'checkbox' ? $row->$field : esc_attr($row->$field);
                $html_attr = strtolower($field);
                echo " data-" . $field . "='" . esc_attr($val) . "'";
            }

            echo ">Edit</button> ";


            echo "<a href='#' class='button delete-confirm' data-id='{$row->{$meta['key']}}' data-table='$selected'>Delete</a></td></tr>";
        }
   }
    echo "</tbody></table><hr>";
    // Pagination
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top: 10px;">';

        $max_links = 5;
        $half = floor($max_links / 2);
        $start = max(1, $current_page - $half);
        $end = min($total_pages, $start + $max_links - 1);

        if ($end - $start < $max_links - 1) {
            $start = max(1, $end - $max_links + 1);
        }

        $base_url = admin_url("admin.php?page=manage-reference-data&ref_table={$selected}");
        if (!empty($search)) {
            $base_url .= '&search=' . urlencode($search);
        }

        // Previous
        if ($current_page > 1) {
            echo "<a class='button' href='{$base_url}&paged=" . ($current_page - 1) . "'>&laquo; Prev</a> ";
        }

        for ($i = $start; $i <= $end; $i++) {
            $class = ($i == $current_page) ? 'button current-page' : 'button';
            echo "<a class='$class' href='{$base_url}&paged=$i'>$i</a> ";
        }

        // Next
        if ($current_page < $total_pages) {
            echo "<a class='button' href='{$base_url}&paged=" . ($current_page + 1) . "'>Next &raquo;</a>";
        }

        echo '</div></div>';
    }

?>
<div id="ref-modal" style="display:none;">
  <form method="post" id="ref-form">
    <input type="hidden" name="fnd_save_table" value="1">
    <input type="hidden" name="record_id" id="modal-record-id">
    <div id="modal-fields"></div>
    <p><button type="submit" class="button button-primary" id="fnd-save-btn">Save</button></p>
  </form>
</div>

<script>
jQuery(function($){
    $('.add-modal, .edit-modal').on('click', function(e) {
        e.preventDefault();
        let type = $(this).data('type');
        let fields = $(this).data('fields');
        if (typeof fields === 'string') fields = JSON.parse(fields);
        let isEdit = $(this).hasClass('edit-modal');
        $('#modal-title').text(isEdit ? 'Edit Record' : 'Add Record');
        $('#modal-fields').html('');
        $('#modal-record-id').val($(this).hasClass('edit-modal') ? $(this).data('id') : '');
         console.log(fields);
        $.each(fields, function(field, type) {
            let value = $(e.currentTarget).data(field.toLowerCase()) || '';
            let fieldHtml = '';
            let labelText = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            if (type === 'checkbox') {
                fieldHtml = '<p><label><input type="checkbox" name="'+field+'" '+(value==1?'checked':'')+'> '+labelText+'</label></p>';
            } else if (type === 'select' && field === 'insurance_type') {
                fieldHtml = '<p><label>'+labelText+':</label><br><select name="'+field+'" class="regular-text">' +
                           '<option value="hmo"'+(value=='hmo'?' selected':'')+'>HMO</option>' +
                           '<option value="ppo"'+(value=='ppo'?' selected':'')+'>PPO</option>' +
                           '<option value="acn"'+(value=='acn'?' selected':'')+'>ACN</option>' +
                           '<option value="aco"'+(value=='aco'?' selected':'')+'>ACO</option>' +
                           '<option value="plan_link"'+(value=='plan_link'?' selected':'')+'>Plan Link</option>' +
                           '</select></p>';
            } else {
                fieldHtml = '<p><label>'+labelText+':</label><br><input type="text" name="'+field+'" value="'+value+'" class="regular-text"></p>';
            }
            $('#modal-fields').append(fieldHtml);
        });

        tb_show(isEdit ? 'Edit Record' : 'Add Record', '#TB_inline?inlineId=ref-modal');
    });

    $('.delete-confirm').on('click', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        let table = $(this).data('table');
        if (confirm('Are you sure you want to delete this record?')) {
            window.location.href = '?page=manage-reference-data&ref_table=' + table + '&delete_id=' + id;
        }
    });
});

jQuery(function($){
    // existing modal code...

    $('#fnd-save-btn').on('click', function(e) {
        let valid = true;
        let firstEmpty = null;

        $('#modal-fields input[type="text"]').each(function() {
            if (!$(this).val().trim()) {
                valid = false;
                $(this).css('border', '1px solid red');
                if (!firstEmpty) firstEmpty = this;
            } else {
                $(this).css('border', '');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please fill all required fields.');
            $(firstEmpty).focus();
        }
    });
    
    // Sync reference data from API
    $('#sync-reference-data').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#sync-result');
        
        if (!confirm('This will sync reference data from the API. This may take a moment. Continue?')) {
            return;
        }
        
        button.prop('disabled', true).text('Syncing...');
        resultDiv.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fad_sync_reference_data',
                nonce: '<?php echo wp_create_nonce('fnd_ajax_nonce'); ?>'
            },
            success: function(response) {
                button.prop('disabled', false).text('Sync from API');
                
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p><strong>✓ Success!</strong> ' + response.data.message + '</p></div>');
                    // Refresh the page after 2 seconds to show new data
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p><strong>✗ Error:</strong> ' + (response.data ? response.data.message : 'Sync failed') + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text('Sync from API');
                resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> Failed to sync reference data. Check browser console for details.</p></div>');
            }
        });
    });
});

</script>

<?php } ?>
