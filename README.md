# Headless WordPress Theme

Professional WordPress headless theme for redirecting to external frontend application with advanced CORS and security settings.

## Features

### Core Features
- **Automatic redirects** to external frontend application
- **Configurable CORS settings** for REST API
- **Security headers** for protection
- **SEO optimized** 301 redirects
- **Admin panel** for easy configuration
- **Multi-environment support** (development, staging, production)

### Advanced Features
- **Custom REST API endpoints** for headless-specific data
- **Webhook notifications** for real-time frontend updates
- **Intelligent caching** with configurable cache times
- **Rate limiting** for API protection
- **Enhanced search** functionality
- **Menu API** with hierarchical structure
- **Site info endpoint** for configuration data
- **Reading time estimates** and post navigation
- **Featured image sizes** optimization

## Installation

1. Copy all files to `wp-content/themes/headless-theme/`
2. Activate theme in WordPress admin panel
3. Go to **Settings → Headless Settings**
4. Configure your frontend application URL

## Configuration

### Basic Settings

1. **Frontend Application URL**: Enter the URL of your frontend application
   - Example: `https://your-app.com`

2. **Redirect Settings**:
   - Enable/disable automatic redirects
   - Set time delay (0 = immediate, recommended)

### CORS Settings

1. **Enable CORS headers**: Enable for REST API
2. **Allowed Origins**: Enter allowed domains (one per line)
   ```
   https://your-app.com
   https://staging.your-app.com
   http://localhost:3000
   ```
3. **Allow credentials**: Enable only if frontend sends cookies/authentication

### Security Settings

- **Security headers**: Adds X-Frame-Options, X-Content-Type-Options, etc.

## Recommended Settings

### Production Environment
```
Frontend URL: https://your-app.com
Redirect Delay: 0 seconds (immediate)
CORS Enabled: Yes
Allowed Origins: https://your-app.com
CORS Credentials: No (unless needed)
Security Headers: Yes
```

### Development Environment
```
Frontend URL: http://localhost:3000
Redirect Delay: 1 second (for debugging)
CORS Enabled: Yes
Allowed Origins: 
  http://localhost:3000
  http://localhost:3001
  https://staging.your-app.com
CORS Credentials: Yes (if needed)
Security Headers: Yes
```

## Theme Files

### Core Files
- `style.css` - Theme definition and basic styles
- `index.php` - Main template with redirect logic
- `functions.php` - Main functionality and admin panel
- `header.php` - Header template
- `footer.php` - Footer template
- `404.php` - 404 template with redirect support
- `.htaccess` - Server-level optimizations

### Advanced Modules
- `includes/api-enhancements.php` - Custom REST API endpoints
- `includes/webhooks-cache.php` - Webhook and cache management

## API Access

### Standard WordPress API
Theme automatically configures CORS for WordPress REST API:
- `GET /wp-json/wp/v2/posts` - Posts with enhanced fields
- `GET /wp-json/wp/v2/pages` - Pages with enhanced fields
- All standard endpoints with custom enhancements

### Custom Headless API
- `GET /wp-json/headless/v1/site-info` - Site configuration and metadata
- `GET /wp-json/headless/v1/menus/{location}` - Menu by location with hierarchy
- `GET /wp-json/headless/v1/search?query=term` - Enhanced search functionality
- `GET /wp-json/headless/v1/breadcrumbs?url=path` - Breadcrumb navigation

### Enhanced Data
All post/page endpoints now include:
- Multiple featured image sizes
- Reading time estimates
- Previous/next post navigation
- Author information with avatar
- Custom meta fields (public only)

## Security

Theme implements:
- **Input sanitization** for all settings
- **Escape output** for all outputs
- **Security headers** for protection
- **Origin validation** for CORS
- **Nonce verification** for admin forms
- **Rate limiting** for API protection
- **Webhook signature verification** for secure notifications

## Webhooks

The theme can automatically notify your frontend application when content changes:

### Supported Events
- `post_created` - New post published
- `post_updated` - Existing post updated
- `post_deleted` - Post deleted
- `cache_cleared` - Cache manually cleared
- `test` - Manual webhook test

### Webhook Payload Example
```json
{
  "event": "post_updated",
  "post_id": 123,
  "post_type": "post",
  "post_title": "Sample Post",
  "post_url": "https://yoursite.com/sample-post",
  "timestamp": 1625097600,
  "site_url": "https://yoursite.com"
}
```

### Security
Webhooks include HMAC-SHA256 signature verification using your secret key in the `X-Webhook-Signature` header.

## Caching

### Automatic Cache Headers
- **Posts/Pages**: 5 minutes (configurable)
- **Menus**: 1 hour (configurable)
- **Site Info**: 1 hour
- **Search Results**: No cache

### Cache Management
- Manual cache clearing via admin panel
- Automatic cache clearing on content updates
- ETag support for conditional requests

## Development Notes

### Hooks and Filters

```php
// Disable redirects for specific pages
add_filter('headless_skip_redirect', function($skip, $url) {
    // Custom logic
    return $skip;
}, 10, 2);

// Modify CORS headers
add_filter('headless_cors_headers', function($headers) {
    // Custom headers
    return $headers;
});
```

### Debugging

For debugging, enable WP_DEBUG in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Common Issues

### 1. Redirect loop
- Check that frontend URL is not the same as WordPress URL
- Verify CORS settings

### 2. CORS errors
- Add all necessary origins
- Check that CORS is enabled
- Verify in browser network tab

### 3. 404 errors on API
- Check permalink settings
- Verify .htaccess file
- Refresh rewrite rules

## Support

For support and updates:
- Documentation: This README
- Issues: Contact developer

## Changelog

### 1.0.0
- Basic functionality
- Admin panel
- CORS support
- Security features
- Custom REST API endpoints
- Webhook notifications
- Intelligent caching
- Rate limiting
- Enhanced search
- Menu API support

## License

This theme is licensed under GPL v2 or later. 