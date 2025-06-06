<?php
/**
 * Webhooks and Cache Management for Headless Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeadlessWebhooksCache {
    
    public function __construct() {
        add_action('save_post', [$this, 'trigger_content_webhook'], 10, 3);
        add_action('delete_post', [$this, 'trigger_delete_webhook']);
        add_action('wp_ajax_headless_test_webhook', [$this, 'test_webhook']);
        add_action('wp_ajax_headless_clear_cache', [$this, 'clear_cache']);
        add_action('init', [$this, 'setup_cache_headers']);
        add_filter('rest_pre_serve_request', [$this, 'add_cache_headers'], 10, 4);
    }
    
    /**
     * Trigger webhook when content is saved
     */
    public function trigger_content_webhook($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || $post->post_status !== 'publish') {
            return;
        }
        
        $webhook_url = get_option('headless_webhook_url', '');
        if (empty($webhook_url)) {
            return;
        }
        
        $payload = [
            'event' => $update ? 'post_updated' : 'post_created',
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post_id),
            'timestamp' => current_time('timestamp'),
            'site_url' => home_url()
        ];
        
        $this->send_webhook($webhook_url, $payload);
    }
    
    /**
     * Trigger webhook when content is deleted
     */
    public function trigger_delete_webhook($post_id) {
        $webhook_url = get_option('headless_webhook_url', '');
        if (empty($webhook_url)) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $payload = [
            'event' => 'post_deleted',
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'timestamp' => current_time('timestamp'),
            'site_url' => home_url()
        ];
        
        $this->send_webhook($webhook_url, $payload);
    }
    
    /**
     * Send webhook request
     */
    private function send_webhook($url, $payload) {
        $secret = get_option('headless_webhook_secret', '');
        
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress-Headless-Theme/1.0'
        ];
        
        if (!empty($secret)) {
            $headers['X-Webhook-Signature'] = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
        }
        
        wp_remote_post($url, [
            'body' => json_encode($payload),
            'headers' => $headers,
            'timeout' => 10,
            'blocking' => false // Non-blocking for performance
        ]);
    }
    
    /**
     * Test webhook endpoint
     */
    public function test_webhook() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('headless_webhook_test', 'nonce');
        
        $webhook_url = get_option('headless_webhook_url', '');
        if (empty($webhook_url)) {
            wp_send_json_error('Webhook URL not configured');
        }
        
        $test_payload = [
            'event' => 'test',
            'message' => 'This is a test webhook from your WordPress site',
            'timestamp' => current_time('timestamp'),
            'site_url' => home_url()
        ];
        
        $response = wp_remote_post($webhook_url, [
            'body' => json_encode($test_payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-Headless-Theme/1.0'
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Webhook failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        wp_send_json_success([
            'status_code' => $response_code,
            'response' => $response_body,
            'success' => $response_code >= 200 && $response_code < 300
        ]);
    }
    
    /**
     * Clear cache endpoint
     */
    public function clear_cache() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_ajax_referer('headless_clear_cache', 'nonce');
        
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        $this->clear_headless_transients();
        
        // Trigger webhook for cache clear
        $webhook_url = get_option('headless_webhook_url', '');
        if (!empty($webhook_url)) {
            $payload = [
                'event' => 'cache_cleared',
                'timestamp' => current_time('timestamp'),
                'site_url' => home_url()
            ];
            $this->send_webhook($webhook_url, $payload);
        }
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * Clear headless-specific transients
     */
    private function clear_headless_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_headless_%' 
             OR option_name LIKE '_transient_timeout_headless_%'"
        );
    }
    
    /**
     * Setup cache headers
     */
    public function setup_cache_headers() {
        if (!get_option('headless_cache_enabled', true)) {
            return;
        }
        
        // Set cache headers for different content types
        add_action('rest_api_init', function() {
            // Posts/Pages - cache for 5 minutes
            add_filter('rest_post_dispatch', [$this, 'set_post_cache_headers'], 10, 3);
            
            // Menus - cache for 1 hour
            add_filter('rest_pre_serve_request', [$this, 'set_menu_cache_headers'], 10, 4);
        });
    }
    
    /**
     * Add cache headers to REST responses
     */
    public function add_cache_headers($served, $result, $request, $server) {
        if (!get_option('headless_cache_enabled', true)) {
            return $served;
        }
        
        $route = $request->get_route();
        
        // Different cache times for different endpoints
        if (strpos($route, '/wp/v2/posts') !== false || strpos($route, '/wp/v2/pages') !== false) {
            $cache_time = 300; // 5 minutes
        } elseif (strpos($route, '/headless/v1/site-info') !== false) {
            $cache_time = 3600; // 1 hour
        } elseif (strpos($route, '/headless/v1/menus') !== false) {
            $cache_time = 3600; // 1 hour
        } else {
            $cache_time = 300; // 5 minutes default
        }
        
        header("Cache-Control: public, max-age={$cache_time}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_time) . ' GMT');
        header('ETag: "' . md5($request->get_route() . serialize($request->get_params())) . '"');
        
        return $served;
    }
    
    /**
     * Set post cache headers
     */
    public function set_post_cache_headers($response, $server, $request) {
        $cache_time = get_option('headless_post_cache_time', 300);
        header("Cache-Control: public, max-age={$cache_time}");
        return $response;
    }
    
    /**
     * Set menu cache headers
     */
    public function set_menu_cache_headers($served, $result, $request, $server) {
        if (strpos($request->get_route(), '/headless/v1/menus') !== false) {
            $cache_time = get_option('headless_menu_cache_time', 3600);
            header("Cache-Control: public, max-age={$cache_time}");
        }
        return $served;
    }
}

new HeadlessWebhooksCache(); 