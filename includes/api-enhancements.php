<?php
/**
 * REST API Enhancements for Headless Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeadlessAPIEnhancements {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_custom_endpoints']);
        add_filter('rest_pre_dispatch', [$this, 'add_rate_limiting'], 10, 3);
        add_action('rest_api_init', [$this, 'modify_default_endpoints']);
        add_filter('rest_prepare_post', [$this, 'add_custom_post_fields'], 10, 3);
        add_filter('rest_prepare_page', [$this, 'add_custom_post_fields'], 10, 3);
        add_action('rest_api_init', [$this, 'add_meta_fields']);
    }
    
    /**
     * Register custom REST API endpoints
     */
    public function register_custom_endpoints() {
        // Site info endpoint
        register_rest_route('headless/v1', '/site-info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_info'],
            'permission_callback' => '__return_true'
        ]);
        
        // Menu endpoint
        register_rest_route('headless/v1', '/menus/(?P<location>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menu_by_location'],
            'permission_callback' => '__return_true',
            'args' => [
                'location' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Search endpoint with better performance
        register_rest_route('headless/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'enhanced_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'post_type' => [
                    'default' => 'post',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'per_page' => [
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Breadcrumbs endpoint
        register_rest_route('headless/v1', '/breadcrumbs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_breadcrumbs'],
            'permission_callback' => '__return_true',
            'args' => [
                'url' => [
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw'
                ]
            ]
        ]);
    }
    
    /**
     * Get site information
     */
    public function get_site_info($request) {
        return [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'language' => get_bloginfo('language'),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'frontend_url' => get_option('headless_frontend_url', ''),
            'theme_version' => wp_get_theme()->get('Version')
        ];
    }
    
    /**
     * Get menu by location
     */
    public function get_menu_by_location($request) {
        $location = $request->get_param('location');
        $locations = get_nav_menu_locations();
        
        if (!isset($locations[$location])) {
            return new WP_Error('menu_not_found', 'Menu location not found', ['status' => 404]);
        }
        
        $menu = wp_get_nav_menu_object($locations[$location]);
        if (!$menu) {
            return new WP_Error('menu_not_found', 'Menu not found', ['status' => 404]);
        }
        
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        $menu_tree = $this->build_menu_tree($menu_items);
        
        return [
            'id' => $menu->term_id,
            'name' => $menu->name,
            'items' => $menu_tree
        ];
    }
    
    /**
     * Enhanced search functionality
     */
    public function enhanced_search($request) {
        $query = $request->get_param('query');
        $post_type = $request->get_param('post_type');
        $per_page = $request->get_param('per_page');
        
        $args = [
            's' => $query,
            'post_type' => $post_type,
            'posts_per_page' => $per_page,
            'post_status' => 'publish'
        ];
        
        $search_query = new WP_Query($args);
        $results = [];
        
        foreach ($search_query->posts as $post) {
            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 20),
                'link' => get_permalink($post->ID),
                'date' => $post->post_date,
                'type' => $post->post_type,
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium')
            ];
        }
        
        return [
            'results' => $results,
            'total' => $search_query->found_posts,
            'pages' => ceil($search_query->found_posts / $per_page)
        ];
    }
    
    /**
     * Get breadcrumbs for URL
     */
    public function get_breadcrumbs($request) {
        $url = $request->get_param('url');
        // Basic breadcrumb implementation
        // This would need to be expanded based on your needs
        
        return [
            ['title' => 'Home', 'url' => home_url()],
            ['title' => 'Current Page', 'url' => $url]
        ];
    }
    
    /**
     * Build hierarchical menu tree
     */
    private function build_menu_tree($menu_items, $parent_id = 0) {
        $tree = [];
        
        foreach ($menu_items as $item) {
            if ($item->menu_item_parent == $parent_id) {
                $menu_item = [
                    'id' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'target' => $item->target,
                    'classes' => implode(' ', $item->classes),
                    'children' => $this->build_menu_tree($menu_items, $item->ID)
                ];
                $tree[] = $menu_item;
            }
        }
        
        return $tree;
    }
    
    /**
     * Add custom fields to post responses
     */
    public function add_custom_post_fields($response, $post, $request) {
        $data = $response->get_data();
        
        // Add featured image sizes
        if (has_post_thumbnail($post->ID)) {
            $data['featured_image_sizes'] = [
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
                'medium' => get_the_post_thumbnail_url($post->ID, 'medium'),
                'large' => get_the_post_thumbnail_url($post->ID, 'large'),
                'full' => get_the_post_thumbnail_url($post->ID, 'full')
            ];
        }
        
        // Add reading time estimate
        $content = strip_tags($post->post_content);
        $word_count = str_word_count($content);
        $data['reading_time'] = ceil($word_count / 200); // 200 WPM average
        
        // Add next/previous posts
        $data['navigation'] = [
            'previous' => $this->get_adjacent_post_data(get_previous_post()),
            'next' => $this->get_adjacent_post_data(get_next_post())
        ];
        
        $response->set_data($data);
        return $response;
    }
    
    /**
     * Get adjacent post data
     */
    private function get_adjacent_post_data($post) {
        if (!$post) return null;
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'url' => get_permalink($post->ID),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium')
        ];
    }
    
    /**
     * Add meta fields to REST API
     */
    public function add_meta_fields() {
        register_rest_field(['post', 'page'], 'meta_fields', [
            'get_callback' => [$this, 'get_meta_fields'],
            'schema' => [
                'description' => 'Custom meta fields',
                'type' => 'object'
            ]
        ]);
    }
    
    /**
     * Get meta fields for post
     */
    public function get_meta_fields($object) {
        $meta = get_post_meta($object['id']);
        $filtered_meta = [];
        
        // Only return public meta fields (not starting with _)
        foreach ($meta as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $filtered_meta[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }
        
        return $filtered_meta;
    }
    
    /**
     * Basic rate limiting
     */
    public function add_rate_limiting($result, $server, $request) {
        if (!get_option('headless_rate_limiting_enabled', false)) {
            return $result;
        }
        
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_limit_key = 'headless_rate_limit_' . md5($client_ip);
        $current_requests = get_transient($rate_limit_key) ?: 0;
        $rate_limit = get_option('headless_rate_limit_per_minute', 60);
        
        if ($current_requests >= $rate_limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }
        
        set_transient($rate_limit_key, $current_requests + 1, 60);
        
        return $result;
    }
    
    /**
     * Modify default endpoints
     */
    public function modify_default_endpoints() {
        // Add additional fields to default endpoints
        register_rest_field('post', 'author_info', [
            'get_callback' => function($object) {
                $author = get_userdata($object['author']);
                return [
                    'display_name' => $author->display_name,
                    'avatar' => get_avatar_url($author->ID),
                    'bio' => get_user_meta($author->ID, 'description', true)
                ];
            }
        ]);
    }
}

new HeadlessAPIEnhancements(); 