# Digitalis Framework Security Checklist

A comprehensive security checklist for WordPress development with the Digitalis Framework.

---

## Table of Contents

- [Pre-PR Security Review](#pre-pr-security-review)
- [Input Validation](#input-validation)
- [Output Escaping](#output-escaping)
- [Database Security](#database-security)
- [Authentication & Authorization](#authentication--authorization)
- [CSRF Protection](#csrf-protection)
- [File Handling](#file-handling)
- [REST API Security](#rest-api-security)
- [WooCommerce Security](#woocommerce-security)
- [Secrets Management](#secrets-management)
- [Common Vulnerabilities](#common-vulnerabilities)

---

## Pre-PR Security Review

Use this checklist before submitting any pull request:

- [ ] All user input validated and sanitized
- [ ] Database queries use prepared statements
- [ ] Output properly escaped (context-appropriate)
- [ ] Nonces implemented for state-changing operations
- [ ] Capability checks before privileged operations
- [ ] File uploads restricted and validated
- [ ] No secrets in code (use wp-config.php or env)
- [ ] CSRF protection on forms
- [ ] No direct file access without `ABSPATH` check
- [ ] Error messages don't leak sensitive information
- [ ] Debug code removed (var_dump, print_r, error_log with sensitive data)

---

## Input Validation

### Sanitization Functions

Always sanitize user input before use:

| Function | Use Case |
|----------|----------|
| `sanitize_text_field()` | Single-line text input |
| `sanitize_textarea_field()` | Multi-line text input |
| `sanitize_email()` | Email addresses |
| `sanitize_url()` | URLs |
| `sanitize_file_name()` | File names |
| `sanitize_title()` | Slugs and titles |
| `sanitize_key()` | Keys, lowercase alphanumeric |
| `absint()` | Positive integers |
| `intval()` | Integers (can be negative) |
| `floatval()` | Floating point numbers |
| `wp_kses_post()` | HTML content (post-like) |
| `wp_kses()` | HTML with custom allowed tags |

### Examples

```php
// Good - sanitized input
$email = sanitize_email($_POST['email'] ?? '');
$name = sanitize_text_field($_POST['name'] ?? '');
$page = absint($_GET['page'] ?? 1);
$content = wp_kses_post($_POST['content'] ?? '');

// Bad - unsanitized input
$email = $_POST['email'];  // Never do this
$name = $_POST['name'];    // Never do this
```

### Validation Checklist

- [ ] Text fields sanitized with `sanitize_text_field()`
- [ ] Email validated with `is_email()` after `sanitize_email()`
- [ ] URLs validated with `wp_http_validate_url()` or `filter_var()`
- [ ] Integers cast with `absint()` or `intval()`
- [ ] Arrays validated element by element
- [ ] JSON decoded and validated before use
- [ ] File paths validated against allowed directories

---

## Output Escaping

### Escaping Functions

Always escape output based on context:

| Function | Context |
|----------|---------|
| `esc_html()` | HTML element content |
| `esc_attr()` | HTML attribute values |
| `esc_url()` | URLs (href, src) |
| `esc_js()` | Inline JavaScript strings |
| `esc_textarea()` | Textarea content |
| `wp_kses_post()` | Trusted HTML content |
| `wp_json_encode()` | JSON output |

### Context Examples

```php
// HTML content
<h1><?php echo esc_html($title); ?></h1>

// HTML attributes
<input value="<?php echo esc_attr($value); ?>">
<div class="<?php echo esc_attr($class); ?>">
<div data-id="<?php echo esc_attr($id); ?>">

// URLs
<a href="<?php echo esc_url($link); ?>">
<img src="<?php echo esc_url($image_url); ?>">
<form action="<?php echo esc_url($action); ?>">

// JavaScript
<script>
var config = <?php echo wp_json_encode($config); ?>;
var message = '<?php echo esc_js($message); ?>';
</script>

// Textarea
<textarea><?php echo esc_textarea($content); ?></textarea>

// Trusted HTML (from editor)
<div class="content"><?php echo wp_kses_post($html_content); ?></div>
```

### Escaping Checklist

- [ ] All dynamic values in HTML escaped with `esc_html()`
- [ ] All attribute values escaped with `esc_attr()`
- [ ] All URLs escaped with `esc_url()`
- [ ] JavaScript variables use `wp_json_encode()`
- [ ] Rich text content uses `wp_kses_post()` or `wp_kses()`
- [ ] Translation strings escaped: `esc_html__()`, `esc_attr__()`

---

## Database Security

### Prepared Statements

Always use prepared statements for database queries:

```php
global $wpdb;

// Good - prepared statement
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
    $user_id,
    'publish'
));

// Good - multiple values
$ids = [1, 2, 3];
$placeholders = implode(',', array_fill(0, count($ids), '%d'));
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
    ...$ids
));

// Bad - direct interpolation (SQL injection vulnerability)
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->posts} WHERE post_author = $user_id"
);
```

### Format Specifiers

| Specifier | Type |
|-----------|------|
| `%d` | Integer |
| `%f` | Float |
| `%s` | String |

### Database Checklist

- [ ] All queries use `$wpdb->prepare()` with user input
- [ ] Table names use `$wpdb->prefix` or `$wpdb->posts` etc.
- [ ] LIKE queries escape wildcards: `$wpdb->esc_like($search) . '%'`
- [ ] No raw `$_GET`/`$_POST` values in queries
- [ ] INSERT/UPDATE use `$wpdb->insert()` / `$wpdb->update()` when possible

---

## Authentication & Authorization

### Capability Checks

Always verify user capabilities before privileged operations:

```php
// Check capability
if (!current_user_can('edit_posts')) {
    wp_die('You do not have permission to perform this action.');
}

// Check capability for specific post
if (!current_user_can('edit_post', $post_id)) {
    wp_die('You cannot edit this post.');
}

// Custom capability check with Digitalis User model
$user = User::current();
if (!$user || !$user->can('approve_estimate', $order_id)) {
    wp_die('Permission denied.');
}
```

### Framework Permission Pattern

```php
namespace Digitalis;

class User extends \Digitalis\User {

    public function can(string $capability, $context = null): bool {
        switch ($capability) {
            case 'view_account_orders':
                return $this->has_account();

            case 'edit_order':
                return $this->owns_order($context) || current_user_can('manage_woocommerce');

            case 'delete_project':
                // Only account admins can delete
                return $this->is_account_admin();

            default:
                return current_user_can($capability);
        }
    }
}
```

### Authorization Checklist

- [ ] All admin actions check `current_user_can()`
- [ ] Object-specific actions verify ownership
- [ ] Custom capabilities defined and checked
- [ ] Failed auth returns proper error (not silent fail)
- [ ] AJAX handlers verify user is logged in when required
- [ ] REST endpoints have `permission_callback`

---

## CSRF Protection

### Nonce Implementation

```php
// Creating a nonce
$nonce = wp_create_nonce('my_action');

// In a form
<form method="post">
    <?php wp_nonce_field('my_action', 'my_nonce'); ?>
    <!-- form fields -->
</form>

// In a URL
$url = wp_nonce_url(admin_url('admin.php?action=delete&id=123'), 'delete_item_123');

// In AJAX
wp_localize_script('my-script', 'myAjax', [
    'nonce' => wp_create_nonce('my_ajax_action'),
]);

// Verification in form handler
if (!wp_verify_nonce($_POST['my_nonce'] ?? '', 'my_action')) {
    wp_die('Security check failed.');
}

// Verification in AJAX handler
if (!check_ajax_referer('my_ajax_action', 'nonce', false)) {
    wp_send_json_error('Invalid nonce', 403);
}
```

### REST API Nonce

```php
// JavaScript
fetch('/wp-json/digitalis/v1/endpoint', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});

// Or use wp.apiFetch which handles nonces automatically
wp.apiFetch({
    path: '/digitalis/v1/endpoint',
    method: 'POST',
    data: { key: 'value' },
});
```

### CSRF Checklist

- [ ] All forms include `wp_nonce_field()`
- [ ] Form handlers verify nonce with `wp_verify_nonce()`
- [ ] AJAX handlers use `check_ajax_referer()`
- [ ] State-changing URLs use `wp_nonce_url()`
- [ ] REST requests include `X-WP-Nonce` header
- [ ] Nonce names are action-specific (not generic)

---

## File Handling

### Upload Security

```php
// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$file_type = wp_check_filetype($file['name']);

if (!in_array($file['type'], $allowed_types)) {
    return new WP_Error('invalid_type', 'File type not allowed.');
}

// Use WordPress upload handling
$upload = wp_handle_upload($file, [
    'test_form' => false,
    'mimes'     => [
        'jpg|jpeg' => 'image/jpeg',
        'png'      => 'image/png',
        'gif'      => 'image/gif',
        'pdf'      => 'application/pdf',
    ],
]);

if (isset($upload['error'])) {
    return new WP_Error('upload_error', $upload['error']);
}

// Validate file size
$max_size = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $max_size) {
    return new WP_Error('file_too_large', 'File exceeds maximum size.');
}
```

### Path Traversal Prevention

```php
// Validate file paths
$base_dir = WP_CONTENT_DIR . '/uploads/my-plugin/';
$requested_file = sanitize_file_name($_GET['file']);
$full_path = realpath($base_dir . $requested_file);

// Ensure path is within allowed directory
if (!$full_path || strpos($full_path, realpath($base_dir)) !== 0) {
    wp_die('Invalid file path.');
}

// Now safe to use $full_path
```

### File Handling Checklist

- [ ] File types validated against whitelist
- [ ] File extensions verified (not just MIME type)
- [ ] File size limits enforced
- [ ] Uploaded files stored outside web root when possible
- [ ] File names sanitized with `sanitize_file_name()`
- [ ] Path traversal prevented with `realpath()` check
- [ ] Direct file access blocked with `.htaccess` or `ABSPATH` check

---

## REST API Security

### Secure Route Definition

```php
namespace Digitalis;

class Secure_Route extends Route {

    protected static $namespace = 'digitalis';
    protected static $route     = 'sensitive/(?P<id>\d+)';
    protected static $methods   = 'POST';

    /**
     * Permission callback - runs before handle()
     */
    public function permission_callback(): bool {
        // Require authentication
        if (!is_user_logged_in()) {
            return false;
        }

        // Verify nonce for non-cookie auth
        if (!wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest')) {
            return false;
        }

        // Check capability
        $user = User::current();
        return $user->can('edit_item', $this->get_param('id'));
    }

    /**
     * Validate and sanitize parameters
     */
    public function get_args(): array {
        return [
            'id' => [
                'required'          => true,
                'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'required'          => true,
                'validate_callback' => fn($v) => in_array($v, ['active', 'inactive']),
                'sanitize_callback' => 'sanitize_key',
            ],
        ];
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response {
        // Parameters are already validated and sanitized
        $id = $request->get_param('id');
        $status = $request->get_param('status');

        // Process...

        return new \WP_REST_Response(['success' => true]);
    }
}
```

### REST API Checklist

- [ ] `permission_callback` implemented (never return `true` without checks)
- [ ] Parameters have `validate_callback` and `sanitize_callback`
- [ ] Sensitive routes require authentication
- [ ] Rate limiting considered for public endpoints
- [ ] Error responses don't leak sensitive data
- [ ] CORS headers restricted if needed

---

## WooCommerce Security

### Order Access Control

```php
// Verify order ownership before access
$order = wc_get_order($order_id);

if (!$order) {
    wp_die('Order not found.');
}

// Check ownership
$user_id = get_current_user_id();
if ($order->get_customer_id() !== $user_id && !current_user_can('manage_woocommerce')) {
    wp_die('You do not have permission to view this order.');
}
```

### Payment Data

```php
// Never store full card numbers
// Never log payment responses with sensitive data

// Good - log safely
error_log(sprintf(
    'Payment processed for order %d, transaction ID: %s',
    $order_id,
    $transaction_id
));

// Bad - logging sensitive data
error_log(print_r($payment_response, true));  // May contain card data
```

### WooCommerce Checklist

- [ ] Order access verified against customer ID or capability
- [ ] Payment gateway secrets in wp-config.php, not code
- [ ] Webhook endpoints verify signature
- [ ] Customer data access respects privacy settings
- [ ] Admin-only WC actions check `manage_woocommerce`

---

## Secrets Management

### Environment Configuration

```php
// wp-config.php
define('MY_API_KEY', 'your-api-key-here');
define('MY_SECRET', 'your-secret-here');

// Usage in plugin
$api_key = defined('MY_API_KEY') ? MY_API_KEY : '';

// Or use environment variables
$api_key = getenv('MY_API_KEY') ?: '';
```

### What NOT to Commit

```
# .gitignore
wp-config.php
.env
*.pem
*.key
credentials.json
service-account.json
```

### Secrets Checklist

- [ ] API keys in options table or wp-config.php or environment variables
- [ ] No hardcoded passwords or tokens in source code
- [ ] Sensitive files in `.gitignore`
- [ ] Production credentials different from development
- [ ] Secrets rotated if accidentally committed
- [ ] Debug logs don't contain secrets

---

## Common Vulnerabilities

### OWASP Top 10 Quick Reference

| Vulnerability | Prevention |
|---------------|------------|
| **Injection** | Prepared statements, sanitization |
| **Broken Auth** | Capability checks, session management |
| **Sensitive Data** | Encryption, proper escaping |
| **XXE** | Disable external entities in XML parsing |
| **Broken Access** | Authorization on every request |
| **Misconfig** | Disable debug in production |
| **XSS** | Output escaping, CSP headers |
| **Insecure Deserialization** | Validate before `unserialize()` |
| **Vulnerable Components** | Keep WordPress/plugins updated |
| **Logging/Monitoring** | Log security events, don't log secrets |

### WordPress-Specific Vulnerabilities

```php
// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Disable XML-RPC if not needed (in functions.php or plugin)
add_filter('xmlrpc_enabled', '__return_false');

// Disable file editing in admin
// wp-config.php
define('DISALLOW_FILE_EDIT', true);

// Hide WordPress version
remove_action('wp_head', 'wp_generator');

// Limit login attempts (use plugin or custom)
// Implement rate limiting on sensitive endpoints
```

### Dangerous Functions to Avoid

```php
// Never use with user input
eval($user_input);                    // Code execution
unserialize($user_input);             // Object injection
extract($user_input);                 // Variable injection
include($user_input);                 // File inclusion
shell_exec($user_input);              // Command execution
system($user_input);                  // Command execution
passthru($user_input);                // Command execution

// Safe alternatives
// For dynamic includes, use whitelist
$allowed = ['template-a', 'template-b'];
if (in_array($template, $allowed, true)) {
    include __DIR__ . "/templates/{$template}.php";
}
```

---

## Security Review Process

### Before Every PR

1. **Run static analysis** (if configured)
2. **Search for common issues:**
   ```bash
   # Search for potential SQL injection
   grep -r "\$wpdb->query.*\$_" --include="*.php"

   # Search for unescaped output
   grep -r "echo \$" --include="*.php"

   # Search for direct superglobal use
   grep -r "\$_POST\[" --include="*.php" | grep -v "sanitize\|esc_\|wp_verify"
   ```

3. **Check new files** for `ABSPATH` check
4. **Review all user input** flows
5. **Verify nonces** on new forms/actions

### Periodic Security Audit

- [ ] Review all `$wpdb` queries for prepared statements
- [ ] Audit REST API endpoints for auth
- [ ] Check file upload handlers
- [ ] Review custom capability implementations
- [ ] Verify all forms have CSRF protection
- [ ] Check for hardcoded secrets
- [ ] Update dependencies

---

## Resources

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress VIP Code Analysis](https://docs.wpvip.com/technical-references/code-review/)
- [WPScan Vulnerability Database](https://wpscan.com/vulnerabilities)
