<?php
/**
 * Headless WordPress Theme Functions
 * 
 * @package HeadlessTheme
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class HeadlessTheme {
    
    public function __construct() {
        add_action('after_setup_theme', [$this, 'theme_setup']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'handle_cors']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'add_security_headers']);
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 4);
        add_action('template_redirect', [$this, 'handle_redirects']);
        add_action('wp_loaded', [$this, 'disable_admin_bar_for_non_admins']);
    }
    
    /**
     * Theme setup
     */
    public function theme_setup() {
        // Add theme support
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        
        // Load text domain
        load_theme_textdomain('headless-theme', get_template_directory() . '/languages');
        
        // Remove unnecessary theme features
        remove_theme_support('widgets-block-editor');
        
        // Clean head
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('headless-theme-style', get_stylesheet_uri(), [], '1.0.0');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Headless Settings', 'headless-theme'),
            __('Headless Settings', 'headless-theme'),
            'manage_options',
            'headless-settings',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('headless_settings', 'headless_frontend_url', [
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ]);
        
        register_setting('headless_settings', 'headless_redirect_enabled', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        
        register_setting('headless_settings', 'headless_redirect_delay', [
            'sanitize_callback' => 'absint',
            'default' => 0
        ]);
        
        register_setting('headless_settings', 'headless_cors_enabled', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        
        register_setting('headless_settings', 'headless_allowed_origins', [
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ]);
        
        register_setting('headless_settings', 'headless_cors_credentials', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        
        register_setting('headless_settings', 'headless_security_headers', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        
        // Webhook settings
        register_setting('headless_settings', 'headless_webhook_url', [
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ]);
        
        register_setting('headless_settings', 'headless_webhook_secret', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        // Cache settings
        register_setting('headless_settings', 'headless_cache_enabled', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true
        ]);
        
        register_setting('headless_settings', 'headless_post_cache_time', [
            'sanitize_callback' => 'absint',
            'default' => 300
        ]);
        
        register_setting('headless_settings', 'headless_menu_cache_time', [
            'sanitize_callback' => 'absint',
            'default' => 3600
        ]);
        
        // Rate limiting
        register_setting('headless_settings', 'headless_rate_limiting_enabled', [
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        
        register_setting('headless_settings', 'headless_rate_limit_per_minute', [
            'sanitize_callback' => 'absint',
            'default' => 60
        ]);
    }
    
    /**
     * Admin page template
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'headless-theme') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('headless_settings');
                do_settings_sections('headless_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="headless_frontend_url"><?php _e('Frontend Application URL', 'headless-theme'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="headless_frontend_url" 
                                   name="headless_frontend_url" 
                                   value="<?php echo esc_attr(get_option('headless_frontend_url')); ?>" 
                                   class="regular-text" 
                                   placeholder="https://your-frontend-app.com" />
                            <p class="description"><?php _e('The URL of your frontend application where users will be redirected.', 'headless-theme'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Redirect Settings', 'headless-theme'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="headless_redirect_enabled" 
                                           value="1" 
                                           <?php checked(get_option('headless_redirect_enabled', true)); ?> />
                                    <?php _e('Enable automatic redirects', 'headless-theme'); ?>
                                </label>
                                <br><br>
                                
                                <label for="headless_redirect_delay">
                                    <?php _e('Redirect delay (seconds):', 'headless-theme'); ?>
                                    <input type="number" 
                                           id="headless_redirect_delay" 
                                           name="headless_redirect_delay" 
                                           value="<?php echo esc_attr(get_option('headless_redirect_delay', 0)); ?>" 
                                           min="0" 
                                           max="10" 
                                           step="0.5" />
                                </label>
                                <p class="description"><?php _e('0 = Immediate server-side redirect (recommended for SEO)', 'headless-theme'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('CORS Settings', 'headless-theme'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="headless_cors_enabled" 
                                           value="1" 
                                           <?php checked(get_option('headless_cors_enabled', true)); ?> />
                                    <?php _e('Enable CORS headers', 'headless-theme'); ?>
                                </label>
                                <br><br>
                                
                                <label>
                                    <input type="checkbox" 
                                           name="headless_cors_credentials" 
                                           value="1" 
                                           <?php checked(get_option('headless_cors_credentials', false)); ?> />
                                    <?php _e('Allow credentials in CORS requests', 'headless-theme'); ?>
                                </label>
                                <p class="description"><?php _e('Enable this if your frontend needs to send cookies or authentication headers', 'headless-theme'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="headless_allowed_origins"><?php _e('Allowed Origins', 'headless-theme'); ?></label>
                        </th>
                        <td>
                            <textarea id="headless_allowed_origins" 
                                      name="headless_allowed_origins" 
                                      rows="5" 
                                      class="large-text"
                                      placeholder="https://your-frontend-app.com&#10;https://your-staging-app.com&#10;http://localhost:3000"><?php echo esc_textarea(get_option('headless_allowed_origins')); ?></textarea>
                            <p class="description"><?php _e('Enter one origin per line. Leave empty to allow all origins (*)', 'headless-theme'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Security Settings', 'headless-theme'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="headless_security_headers" 
                                           value="1" 
                                           <?php checked(get_option('headless_security_headers', true)); ?> />
                                    <?php _e('Add security headers', 'headless-theme'); ?>
                                </label>
                                <p class="description"><?php _e('Adds security headers like X-Frame-Options, X-Content-Type-Options, etc.', 'headless-theme'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="headless_webhook_url"><?php _e('Webhook URL', 'headless-theme'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="headless_webhook_url" 
                                   name="headless_webhook_url" 
                                   value="<?php echo esc_attr(get_option('headless_webhook_url')); ?>" 
                                   class="regular-text" 
                                   placeholder="https://your-frontend-app.com/api/webhooks" />
                            <p class="description"><?php _e('URL to notify your frontend when content changes', 'headless-theme'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="headless_webhook_secret"><?php _e('Webhook Secret', 'headless-theme'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="headless_webhook_secret" 
                                   name="headless_webhook_secret" 
                                   value="<?php echo esc_attr(get_option('headless_webhook_secret')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Secret key for webhook signature verification', 'headless-theme'); ?></p>
                            <button type="button" id="test-webhook" class="button"><?php _e('Test Webhook', 'headless-theme'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache Settings', 'headless-theme'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="headless_cache_enabled" 
                                           value="1" 
                                           <?php checked(get_option('headless_cache_enabled', true)); ?> />
                                    <?php _e('Enable API response caching', 'headless-theme'); ?>
                                </label>
                                <br><br>
                                
                                <label for="headless_post_cache_time">
                                    <?php _e('Posts cache time (seconds):', 'headless-theme'); ?>
                                    <input type="number" 
                                           id="headless_post_cache_time" 
                                           name="headless_post_cache_time" 
                                           value="<?php echo esc_attr(get_option('headless_post_cache_time', 300)); ?>" 
                                           min="0" />
                                </label>
                                <br><br>
                                
                                <label for="headless_menu_cache_time">
                                    <?php _e('Menus cache time (seconds):', 'headless-theme'); ?>
                                    <input type="number" 
                                           id="headless_menu_cache_time" 
                                           name="headless_menu_cache_time" 
                                           value="<?php echo esc_attr(get_option('headless_menu_cache_time', 3600)); ?>" 
                                           min="0" />
                                </label>
                                <br><br>
                                
                                <button type="button" id="clear-cache" class="button"><?php _e('Clear Cache', 'headless-theme'); ?></button>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Rate Limiting', 'headless-theme'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                           name="headless_rate_limiting_enabled" 
                                           value="1" 
                                           <?php checked(get_option('headless_rate_limiting_enabled', false)); ?> />
                                    <?php _e('Enable API rate limiting', 'headless-theme'); ?>
                                </label>
                                <br><br>
                                
                                <label for="headless_rate_limit_per_minute">
                                    <?php _e('Requests per minute per IP:', 'headless-theme'); ?>
                                    <input type="number" 
                                           id="headless_rate_limit_per_minute" 
                                           name="headless_rate_limit_per_minute" 
                                           value="<?php echo esc_attr(get_option('headless_rate_limit_per_minute', 60)); ?>" 
                                           min="1" />
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Test webhook
                    document.getElementById('test-webhook').addEventListener('click', function() {
                        const button = this;
                        button.disabled = true;
                        button.textContent = 'Testing...';
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'headless_test_webhook',
                                nonce: '<?php echo wp_create_nonce("headless_webhook_test"); ?>'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.success ? 'Webhook test successful!' : 'Webhook test failed: ' + data.data);
                            button.disabled = false;
                            button.textContent = 'Test Webhook';
                        })
                        .catch(error => {
                            alert('Error: ' + error.message);
                            button.disabled = false;
                            button.textContent = 'Test Webhook';
                        });
                    });
                    
                    // Clear cache
                    document.getElementById('clear-cache').addEventListener('click', function() {
                        const button = this;
                        button.disabled = true;
                        button.textContent = 'Clearing...';
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'headless_clear_cache',
                                nonce: '<?php echo wp_create_nonce("headless_clear_cache"); ?>'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.success ? 'Cache cleared successfully!' : 'Cache clear failed: ' + data.data);
                            button.disabled = false;
                            button.textContent = 'Clear Cache';
                        })
                        .catch(error => {
                            alert('Error: ' + error.message);
                            button.disabled = false;
                            button.textContent = 'Clear Cache';
                        });
                    });
                });
                </script>
            </form>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('Configuration Tips', 'headless-theme'); ?></h2>
                </div>
                <div class="inside">
                    <h4><?php _e('Recommended Settings:', 'headless-theme'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Production:', 'headless-theme'); ?></strong> <?php _e('Use immediate redirect (0 seconds) for best SEO', 'headless-theme'); ?></li>
                        <li><strong><?php _e('Development:', 'headless-theme'); ?></strong> <?php _e('Add localhost origins for local development', 'headless-theme'); ?></li>
                        <li><strong><?php _e('Security:', 'headless-theme'); ?></strong> <?php _e('Always specify exact origins instead of wildcard (*)', 'headless-theme'); ?></li>
                        <li><strong><?php _e('CORS Credentials:', 'headless-theme'); ?></strong> <?php _e('Enable only if your frontend sends authentication', 'headless-theme'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle CORS
     */
    public function handle_cors() {
        if (!get_option('headless_cors_enabled', true)) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed_origins = $this->get_allowed_origins();
        
        if (empty($allowed_origins) || in_array($origin, $allowed_origins)) {
            $allow_origin = empty($allowed_origins) ? '*' : $origin;
            
            header("Access-Control-Allow-Origin: $allow_origin");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            
            if (get_option('headless_cors_credentials', false)) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Add CORS headers to REST API responses
     */
    public function add_cors_headers($served, $result, $request, $server) {
        if (!get_option('headless_cors_enabled', true)) {
            return $served;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed_origins = $this->get_allowed_origins();
        
        if (empty($allowed_origins) || in_array($origin, $allowed_origins)) {
            $allow_origin = empty($allowed_origins) ? '*' : $origin;
            
            header("Access-Control-Allow-Origin: $allow_origin");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            
            if (get_option('headless_cors_credentials', false)) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        return $served;
    }
    
    /**
     * Get allowed origins as array
     */
    private function get_allowed_origins() {
        $origins = get_option('headless_allowed_origins', '');
        
        if (empty($origins)) {
            return [];
        }
        
        return array_filter(array_map('trim', explode("\n", $origins)));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!get_option('headless_security_headers', true)) {
            return;
        }
        
        ?>
        <meta http-equiv="X-Content-Type-Options" content="nosniff">
        <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
        <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
        <?php
    }
    
    /**
     * Handle redirects for specific cases
     */
    public function handle_redirects() {
        // Skip redirects for admin, login, API endpoints
        if (is_admin() || 
            strpos($_SERVER['REQUEST_URI'], '/wp-login') !== false ||
            strpos($_SERVER['REQUEST_URI'], '/wp-json') !== false ||
            strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
            return;
        }
        
        // Allow favicon and robots.txt
        if (strpos($_SERVER['REQUEST_URI'], '/favicon.ico') !== false ||
            strpos($_SERVER['REQUEST_URI'], '/robots.txt') !== false) {
            return;
        }
    }
    
    /**
     * Disable admin bar for non-admins
     */
    public function disable_admin_bar_for_non_admins() {
        if (!current_user_can('administrator')) {
            show_admin_bar(false);
        }
    }
}

// Initialize the theme
new HeadlessTheme();

// Load additional modules
require_once get_template_directory() . '/includes/api-enhancements.php';
require_once get_template_directory() . '/includes/webhooks-cache.php';

/**
 * Required template functions
 */
function wp_head() {
    do_action('wp_head');
}

function wp_footer() {
    do_action('wp_footer');
}

function get_header() {
    echo '<!DOCTYPE html>';
    echo '<html ' . get_language_attributes() . '>';
    echo '<head>';
    echo '<meta charset="' . get_bloginfo('charset') . '">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    wp_head();
    echo '</head>';
    echo '<body ' . get_body_class() . '>';
}

function get_footer() {
    wp_footer();
    echo '</body>';
    echo '</html>';
}

function get_body_class() {
    return 'class="headless-theme"';
}

function get_language_attributes() {
    return 'lang="' . get_bloginfo('language') . '"';
} 