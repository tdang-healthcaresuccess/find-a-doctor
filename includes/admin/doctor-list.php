<?php
function fnd_render_doctor_list_page() {
    global $wpdb;
    $doctors_table = $wpdb->prefix . 'doctors';

    if (isset($_GET['fnd_delete']) && is_numeric($_GET['fnd_delete'])) {
        $wpdb->delete($doctors_table, ['doctorID' => intval($_GET['fnd_delete'])]);
        echo '<div class="updated"><p>Doctor deleted successfully.</p></div>';
    }

    if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    echo '<div class="updated notice is-dismissible"><p><strong>Doctor updated successfully.</strong></p></div>';
}

    $per_page = 25;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Handle search input
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $where_clause = '';
    $where_args = [];

    if (!empty($search)) {
        $where_clause = "WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s";
        $where_args = ["%$search%", "%$search%", "%$search%"];
    }

    // Count total rows
    $count_sql = "SELECT COUNT(*) FROM $doctors_table ";
    if ($where_clause) {
        $count_sql .= $wpdb->prepare(" $where_clause", ...$where_args);
    }
    $total_doctors = $wpdb->get_var($count_sql);
    $total_pages = ceil($total_doctors / $per_page);

    // Fetch paginated data
    $sql = "SELECT * FROM $doctors_table ";
    if ($where_clause) {
        $sql .= $wpdb->prepare(" $where_clause", ...$where_args);
    }
    $sql .= $wpdb->prepare(" ORDER BY doctorID DESC LIMIT %d OFFSET %d", $per_page, $offset);

    $doctors = $wpdb->get_results($sql);


    echo '<div class="wrap"><h1>All Doctors</h1>';
    echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
   echo '<h3 style="margin: 0;">Total Doctors: <span id="doctor-count">' . $total_doctors . '</span></h3>';
    echo '<form method="get"><input type="hidden" name="page" value="doctor-list">';
    echo '<input type="text" name="search" placeholder="Search by name or email..." value="' . esc_attr($search) . '">';
    echo ' <input type="submit" class="button" value="Search">';
    echo '</form></div><br>';
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Gender</th><th>Degree</th><th>Location</th><th>Actions</th>
    </tr></thead><tbody>';

    if (!empty($doctors)) {
        foreach ($doctors as $doc) {
            $full_name = esc_html($doc->first_name . ' ' . $doc->last_name);
            
            // Format location info
            $location_info = '';
            if (!empty($doc->city) && !empty($doc->state)) {
                $location_info = esc_html($doc->city . ', ' . $doc->state);
                
                // Add geolocation if available
                if (fad_has_valid_geolocation($doc->latitude, $doc->longitude)) {
                    $location_info .= '<br><small style="color: #666;">📍 ' . 
                                    fad_format_geolocation($doc->latitude, $doc->longitude, 4) . '</small>';
                }
            } elseif (fad_has_valid_geolocation($doc->latitude, $doc->longitude)) {
                $location_info = '<small style="color: #666;">📍 ' . 
                               fad_format_geolocation($doc->latitude, $doc->longitude, 4) . '</small>';
            } else {
                $location_info = '<em style="color: #999;">No location</em>';
            }
            
            echo "<tr>
                <td>{$doc->doctorID}</td>
                <td>{$full_name}</td>
                <td>{$doc->email}</td>
                <td>{$doc->phone_number}</td>
                <td>{$doc->gender}</td>
                <td>{$doc->degree}</td>
                <td>{$location_info}</td>
                <td>
                    <a href='admin.php?page=doctor-edit&id={$doc->doctorID}' class='button'>Edit</a>
                    <a href='admin.php?page=doctor-list&fnd_delete={$doc->doctorID}' class='button' onclick=\"return confirm('Are you sure you want to delete this doctor?')\">Delete</a>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='8' style = 'text-align:center'>No data found.</td></tr>";
    }

    echo '</tbody></table>';

   // Pagination logic
    echo '<div class="tablenav"><div class="tablenav-pages">';

    $max_links = 5; // Number of page links to display
    $half = floor($max_links / 2);

    // Calculate start and end
    $start = max(1, $current_page - $half);
    $end = min($total_pages, $start + $max_links - 1);

    if ($end - $start < $max_links - 1) {
        $start = max(1, $end - $max_links + 1);
    }

    // Previous link
    if ($current_page > 1) {
        echo "<a class='button' href='admin.php?page=doctor-list&paged=" . ($current_page - 1) . "'>&laquo; Prev</a> ";
    }

    // Page links
    for ($i = $start; $i <= $end; $i++) {
        $class = ($i == $current_page) ? 'button current-page' : 'button';
        echo "<a class='$class' href='admin.php?page=doctor-list&paged=$i'>$i</a> ";
    }

    // Next link
    if ($current_page < $total_pages) {
        echo "<a class='button' href='admin.php?page=doctor-list&paged=" . ($current_page + 1) . "'>Next &raquo;</a>";
    }

    echo '</div></div></div>';
}
?>
