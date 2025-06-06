<?php
/**
 * Main template file for Headless WordPress Theme
 * Handles redirects to frontend application
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get theme options
$frontend_url = get_option('headless_frontend_url', '');
$redirect_enabled = get_option('headless_redirect_enabled', true);
$redirect_delay = get_option('headless_redirect_delay', 0);

// Handle redirect if enabled and URL is set
if ($redirect_enabled && !empty($frontend_url) && !is_admin()) {
    // Clean URL
    $frontend_url = esc_url($frontend_url);
    
    // Get current path
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_url = rtrim($frontend_url, '/') . $current_path;
    
    // Server-side redirect for better SEO
    if ($redirect_delay == 0) {
        wp_redirect($redirect_url, 301);
        exit;
    }
}

get_header();
?>

<div class="headless-redirect-message">
    <h1><?php esc_html_e('Redirecting...', 'headless-theme'); ?></h1>
    <div class="headless-redirect-loader"></div>
    <p><?php esc_html_e('You are being redirected to our frontend application.', 'headless-theme'); ?></p>
    
    <?php if (!empty($frontend_url)): ?>
        <p>
            <a href="<?php echo esc_url($frontend_url); ?>" id="manual-redirect">
                <?php esc_html_e('Click here if you are not redirected automatically', 'headless-theme'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>

<?php if (!empty($frontend_url) && $redirect_delay > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.location.href = <?php echo json_encode(esc_url(rtrim($frontend_url, '/') . ($_SERVER['REQUEST_URI'] ?? ''))); ?>;
    }, <?php echo intval($redirect_delay * 1000); ?>);
});
</script>
<?php endif; ?>

<?php get_footer(); 