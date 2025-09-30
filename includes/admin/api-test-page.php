<?php
/**
 * Simple API Test Page for WordPress Admin
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . '../api-client.php';

function fnd_render_api_test_page() {
    ?>
    <div class="wrap">
        <h1>API Connection Test</h1>
        
        <div class="card">
            <h2>API Configuration</h2>
            <table class="form-table">
                <tr>
                    <th>Hostname:</th>
                    <td><?php echo esc_html(FAD_API_HOSTNAME); ?></td>
                </tr>
                <tr>
                    <th>Username:</th>
                    <td><?php echo esc_html(FAD_API_USERNAME); ?></td>
                </tr>
                <tr>
                    <th>Password:</th>
                    <td><?php echo str_repeat('*', strlen(FAD_API_PASSWORD)); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>API Endpoints</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (FAD_API_ENDPOINTS as $key => $endpoint): ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst($key)); ?></td>
                        <td><?php echo esc_html(fad_get_api_url($key)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>Connection Test</h2>
            <p>Click the button below to test the API connection:</p>
            <button id="test-connection" class="button button-primary">Test API Connection</button>
            <div id="test-results" style="margin-top: 20px;"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-connection').on('click', function() {
            var button = $(this);
            var results = $('#test-results');
            
            button.prop('disabled', true).text('Testing...');
            results.html('<div class="spinner is-active" style="float:none;"></div> Testing connection...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fad_admin_test_connection',
                    nonce: '<?php echo wp_create_nonce('fad_admin_test'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div class="notice notice-success"><p><strong>✅ Connection Successful!</strong><br>API is responding correctly.</p></div>');
                    } else {
                        results.html('<div class="notice notice-error"><p><strong>❌ Connection Failed:</strong><br>' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    results.html('<div class="notice notice-error"><p><strong>❌ Request Failed:</strong><br>' + error + '</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test API Connection');
                }
            });
        });
    });
    </script>
    <?php
}