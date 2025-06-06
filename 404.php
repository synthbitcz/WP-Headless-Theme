<?php
/**
 * 404 Template
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get theme options
$frontend_url = get_option('headless_frontend_url', '');
$redirect_enabled = get_option('headless_redirect_enabled', true);

// Handle 404 redirects
if ($redirect_enabled && !empty($frontend_url)) {
    $frontend_url = esc_url($frontend_url);
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_url = rtrim($frontend_url, '/') . $current_path;
    
    wp_redirect($redirect_url, 301);
    exit;
}

get_header();
?>

<div class="headless-redirect-message">
    <h1><?php esc_html_e('Page Not Found', 'headless-theme'); ?></h1>
    <p><?php esc_html_e('The page you are looking for does not exist.', 'headless-theme'); ?></p>
    
    <?php if (!empty($frontend_url)): ?>
        <p>
            <a href="<?php echo esc_url($frontend_url); ?>">
                <?php esc_html_e('Visit our frontend application', 'headless-theme'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

<?php get_footer(); 