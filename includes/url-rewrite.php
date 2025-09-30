<?php
/**
 * URL Rewrite Rules for Physician Profiles
 * Creates URLs like: /physicians/john-smith-md/
 */

defined('ABSPATH') || exit;

class FAD_URL_Rewrite {
    
    /**
     * Check if we're in headless mode
     */
    public static function is_headless_mode() {
        return defined('HEADLESS_MODE_CLIENT_URL') || 
               isset($_SERVER['HTTP_X_FAUST_SECRET']) ||
               (function_exists('is_faust') && is_faust()) ||
               defined('FAUSTWP_SECRET_KEY') ||
               (defined('WP_ENV') && WP_ENV === 'headless') ||
               (isset($_SERVER['HTTP_X_HEADLESS']) && $_SERVER['HTTP_X_HEADLESS'] === '1');
    }
    
    /**
     * Initialize URL rewriting
     */
    public static function init() {
        // Double-check headless mode before initializing
        if (self::is_headless_mode()) {
            // Skip URL rewrite initialization in headless mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FAD_URL_Rewrite::init() - Skipping initialization due to headless mode');
            }
            return;
        }
        
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_action('wp', [__CLASS__, 'handle_physician_page']);
        add_action('wp_loaded', [__CLASS__, 'flush_rules_if_needed']);
    }
    
    /**
     * Add custom rewrite rules
     */
    public static function add_rewrite_rules() {
        // Add rewrite rule for physician profiles
        // URL: /physicians/john-smith-md/
        add_rewrite_rule(
            '^physicians/([^/]+)/?$',
            'index.php?physician_slug=$matches[1]',
            'top'
        );
        
        // Add rewrite rule for physician listing page
        // URL: /physicians/
        add_rewrite_rule(
            '^physicians/?$',
            'index.php?physician_listing=1',
            'top'
        );
        
        // Debug: Log that rules are being added
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FAD: Rewrite rules added');
        }
    }
    
    /**
     * Add custom query variables
     */
    public static function add_query_vars($vars) {
        $vars[] = 'physician_slug';
        $vars[] = 'physician_listing';
        return $vars;
    }
    
    /**
     * Handle physician page requests
     */
    public static function handle_physician_page() {
        global $wp_query;
        
        // Handle individual physician page
        $physician_slug = get_query_var('physician_slug');
        if ($physician_slug) {
            // Clean any output that might have been generated
            if (ob_get_length()) {
                ob_clean();
            }
            self::load_physician_template($physician_slug);
            return;
        }
        
        // Handle physician listing page
        if (get_query_var('physician_listing')) {
            // Clean any output that might have been generated
            if (ob_get_length()) {
                ob_clean();
            }
            self::load_physician_listing_template();
            return;
        }
    }
    
    /**
     * Load individual physician template
     */
    private static function load_physician_template($slug) {
        if (!$slug) {
            return;
        }
        
        $physician = self::get_physician_by_slug($slug);
        
        if (!$physician) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            include(get_404_template());
            exit;
        }
        
        // Set up global data for the template
        global $fad_current_physician;
        $fad_current_physician = $physician;
        
        // Set up WordPress query for SEO
        global $wp_query;
        $wp_query->is_home = false;
        $wp_query->is_page = true;
        $wp_query->is_single = true;
        $wp_query->is_404 = false;
        
        // Set page title
        add_filter('wp_title', function($title) use ($physician) {
            return $physician['first_name'] . ' ' . $physician['last_name'] . ' ' . $physician['degree'] . ' | Find a Doctor';
        });
        
        add_filter('document_title_parts', function($parts) use ($physician) {
            $parts['title'] = $physician['first_name'] . ' ' . $physician['last_name'] . ' ' . $physician['degree'];
            return $parts;
        });
        
        // Load template
        $template_path = self::get_template_path('single-physician.php');
        
        if ($template_path) {
            include $template_path;
        } else {
            // Fallback to default template
            self::render_default_physician_template($physician);
        }
        exit;
    }
    
    /**
     * Load physician listing template
     */
    private static function load_physician_listing_template() {
        // Get all physicians with pagination
        $page = max(1, get_query_var('paged', 1));
        $per_page = 20;
        $physicians = self::get_physicians_list($page, $per_page);
        
        // Set up global data for the template
        global $fad_physicians_list, $fad_pagination;
        $fad_physicians_list = $physicians['physicians'];
        $fad_pagination = $physicians['pagination'];
        
        // Set up WordPress query for SEO
        global $wp_query;
        $wp_query->is_home = false;
        $wp_query->is_page = true;
        $wp_query->is_archive = true;
        $wp_query->is_404 = false;
        
        // Set page title
        add_filter('wp_title', function($title) {
            return 'Find a Doctor | Physician Directory';
        });
        
        add_filter('document_title_parts', function($parts) {
            $parts['title'] = 'Find a Doctor';
            return $parts;
        });
        
        // Load template
        $template_path = self::get_template_path('archive-physicians.php');
        
        if ($template_path) {
            include $template_path;
        } else {
            // Fallback to default template
            self::render_default_listing_template($physicians);
        }
        exit;
    }
    
    /**
     * Get physician by slug
     */
    private static function get_physician_by_slug($slug) {
        global $wpdb;
        
        $physician = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}doctors WHERE slug = %s",
            sanitize_text_field($slug)
        ), ARRAY_A);
        
        if (!$physician) {
            return null;
        }
        
        // Add related data
        $physician['languages'] = fad_get_terms_for_doctor('language', $physician['doctorID']);
        $physician['specialties'] = fad_get_terms_for_doctor('specialty', $physician['doctorID']);
        
        return $physician;
    }
    
    /**
     * Get physicians list with pagination
     */
    private static function get_physicians_list($page = 1, $per_page = 20) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}doctors");
        
        // Get physicians for current page
        $physicians = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}doctors 
             ORDER BY last_name, first_name 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);
        
        // Add related data for each physician
        foreach ($physicians as &$physician) {
            $physician['languages'] = fad_get_terms_for_doctor('language', $physician['doctorID']);
            $physician['specialties'] = fad_get_terms_for_doctor('specialty', $physician['doctorID']);
        }
        
        return [
            'physicians' => $physicians,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            ]
        ];
    }
    
    /**
     * Get template path (check theme first, then plugin)
     */
    private static function get_template_path($template_name) {
        // Check if theme has custom template
        $theme_template = locate_template(['find-a-doctor/' . $template_name]);
        if ($theme_template) {
            return $theme_template;
        }
        
        // Check plugin templates
        $plugin_template = plugin_dir_path(__FILE__) . '../templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Render default physician template
     */
    private static function render_default_physician_template($physician) {
        get_header();
        ?>
        <div class="fad-physician-profile">
            <div class="container">
                <h1><?php echo esc_html($physician['first_name'] . ' ' . $physician['last_name']); ?>
                    <?php if ($physician['degree']): ?>
                        <span class="degree"><?php echo esc_html($physician['degree']); ?></span>
                    <?php endif; ?>
                </h1>
                
                <?php if ($physician['profile_img_url']): ?>
                    <div class="physician-photo">
                        <img src="<?php echo esc_url($physician['profile_img_url']); ?>" 
                             alt="<?php echo esc_attr($physician['first_name'] . ' ' . $physician['last_name']); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="physician-info">
                    <?php if (!empty($physician['specialties'])): ?>
                        <p><strong>Specialties:</strong> <?php echo esc_html(implode(', ', $physician['specialties'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($physician['languages'])): ?>
                        <p><strong>Languages:</strong> <?php echo esc_html(implode(', ', $physician['languages'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($physician['phone_number']): ?>
                        <p><strong>Phone:</strong> <?php echo esc_html($physician['phone_number']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($physician['address'] || $physician['city'] || $physician['state']): ?>
                        <p><strong>Address:</strong> 
                            <?php 
                            $address_parts = array_filter([
                                $physician['address'],
                                $physician['city'],
                                $physician['state'] . ' ' . $physician['zip']
                            ]);
                            echo esc_html(implode(', ', $address_parts));
                            ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($physician['biography']): ?>
                        <div class="physician-bio">
                            <h3>About</h3>
                            <p><?php echo wp_kses_post($physician['biography']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <style>
        .fad-physician-profile { padding: 20px 0; }
        .fad-physician-profile .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .fad-physician-profile h1 { margin-bottom: 20px; }
        .fad-physician-profile .degree { color: #666; font-weight: normal; }
        .fad-physician-profile .physician-photo { float: right; margin: 0 0 20px 20px; }
        .fad-physician-profile .physician-photo img { max-width: 200px; height: auto; border-radius: 8px; }
        .fad-physician-profile .physician-info { line-height: 1.6; }
        .fad-physician-profile .physician-bio { margin-top: 30px; clear: both; }
        </style>
        <?php
        get_footer();
    }
    
    /**
     * Render default listing template
     */
    private static function render_default_listing_template($data) {
        get_header();
        ?>
        <div class="fad-physicians-listing">
            <div class="container">
                <h1>Find a Doctor</h1>
                
                <div class="physicians-grid">
                    <?php foreach ($data['physicians'] as $physician): ?>
                        <div class="physician-card">
                            <h3>
                                <a href="/physicians/<?php echo esc_attr($physician['slug']); ?>/">
                                    <?php echo esc_html($physician['first_name'] . ' ' . $physician['last_name']); ?>
                                    <?php if ($physician['degree']): ?>
                                        <span class="degree"><?php echo esc_html($physician['degree']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </h3>
                            
                            <?php if (!empty($physician['specialties'])): ?>
                                <p class="specialties"><?php echo esc_html(implode(', ', $physician['specialties'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($physician['city'] && $physician['state']): ?>
                                <p class="location"><?php echo esc_html($physician['city'] . ', ' . $physician['state']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($data['pagination']['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $data['pagination']['total_pages']; $i++): ?>
                            <a href="/physicians/?paged=<?php echo $i; ?>" 
                               class="<?php echo $i == $data['pagination']['current_page'] ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <style>
        .fad-physicians-listing { padding: 20px 0; }
        .fad-physicians-listing .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .physicians-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin: 30px 0; }
        .physician-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; }
        .physician-card h3 { margin: 0 0 10px 0; }
        .physician-card .degree { color: #666; font-weight: normal; }
        .physician-card .specialties { color: #0066cc; margin: 5px 0; }
        .physician-card .location { color: #666; margin: 5px 0; }
        .pagination { text-align: center; margin: 30px 0; }
        .pagination a { display: inline-block; padding: 8px 12px; margin: 0 2px; border: 1px solid #ddd; text-decoration: none; }
        .pagination a.current { background: #0066cc; color: white; border-color: #0066cc; }
        </style>
        <?php
        get_footer();
    }
    
    /**
     * Flush rewrite rules if needed
     */
    public static function flush_rules_if_needed() {
        $flushed = get_option('fad_rewrite_rules_flushed');
        $current_version = '1.0'; // Increment this when you change rewrite rules
        
        if ($flushed !== $current_version) {
            flush_rewrite_rules(false); // false = soft flush
            update_option('fad_rewrite_rules_flushed', $current_version);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FAD: Rewrite rules flushed (version ' . $current_version . ')');
            }
        }
    }
    
    /**
     * Reset flush flag (call this when plugin is activated/updated)
     */
    public static function reset_flush_flag() {
        delete_option('fad_rewrite_rules_flushed');
    }
    
    /**
     * Force flush rewrite rules (for debugging)
     */
    public static function force_flush_rules() {
        flush_rewrite_rules(false);
        update_option('fad_rewrite_rules_flushed', '1.0');
    }
}

// Initialize URL rewriting
FAD_URL_Rewrite::init();