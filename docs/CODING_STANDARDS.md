# Digitalis Framework Coding Standards

## Overview

This document outlines the coding standards and best practices for developing with the Digitalis Framework. Following these standards ensures consistency, maintainability, and security across all projects.

---

## Table of Contents

- [PHP Standards](#php-standards)
- [File Naming Conventions](#file-naming-conventions)
- [Class Naming Conventions](#class-naming-conventions)
- [Method & Property Naming](#method--property-naming)
- [Code Formatting](#code-formatting)
- [Documentation](#documentation)
- [Security Practices](#security-practices)
- [WordPress Integration](#wordpress-integration)
- [Error Handling](#error-handling)
- [Performance Guidelines](#performance-guidelines)

---

## PHP Standards

### PHP Version

- **Minimum:** PHP 7.4
- **Recommended:** PHP 8.0+

### PSR Compliance

The framework follows PSR standards with WordPress-specific adaptations:

| Standard | Compliance | Notes |
|----------|------------|-------|
| PSR-1 | Partial | Follows basic coding standard |
| PSR-4 | Modified | Custom autoloader with file suffixes |
| PSR-12 | Partial | Adapted for WordPress conventions |

### Namespace

All framework classes use the `Digitalis` namespace:

```php
namespace Digitalis;

class My_Class extends Model {
    // ...
}
```

### Strict Types (Recommended)

```php
<?php
declare(strict_types=1);

namespace Digitalis;
```

---

## File Naming Conventions

### Standard Suffixes

| Suffix | Type | Example |
|--------|------|---------|
| `.class.php` | Standard class | `Customer.class.php` |
| `.abstract.php` | Abstract class | `Model.abstract.php` |
| `.interface.php` | Interface | `Renderable.interface.php` |
| `.trait.php` | Trait | `Has_Meta.trait.php` |
| `.singleton.php` | Singleton pattern | `Logger.singleton.php` |
| `.factory.php` | Factory pattern | `ACF_Block.factory.php` |
| `.feature.php` | Feature class | `Meta_Box.feature.php` |
| `.model.php` | Model class | `Product.model.php` |

### File Structure

```
include/
├── objects/
│   ├── Model.abstract.php      # Base abstract
│   └── Feature.abstract.php
├── models/
│   ├── Customer.class.php      # Concrete models
│   └── Order.class.php
├── traits/
│   └── Has_Meta.trait.php      # Traits
└── features/
    └── Export.feature.php      # Features
```

### File Header

Every PHP file should begin with:

```php
<?php
/**
 * Class_Name
 *
 * Brief description of the class purpose.
 *
 * @package Digitalis
 * @since   1.0.0
 */

namespace Digitalis;
```

---

## Class Naming Conventions

### General Rules

- **PascalCase** for class names
- **Underscores** for compound words
- Descriptive, noun-based names

```php
// Good
class Customer_Order extends Model {}
class Product_Category extends Term {}
class Email_Notification extends Feature {}

// Avoid
class customerOrder {}      // Wrong case
class ProcessData {}        // Too vague
class CO {}                 // Too abbreviated
```

### Pattern-Specific Names

```php
// Singletons - typically managers or services
class Cache_Manager extends Singleton {}
class Email_Service extends Singleton {}

// Factories - things that create instances
class ACF_Block extends Factory {}
class View_Factory extends Factory {}

// Models - represent entities
class Customer extends User {}
class Product extends Post {}

// Features - add functionality
class Export_Orders extends Feature {}
class Send_Notifications extends Feature {}
```

### WordPress Entity Classes

Follow the entity they represent:

```php
// Post types
class Event extends Post {}
class Resource extends Post {}

// Taxonomies
class Event_Category extends Term {}

// Users
class Subscriber extends User {}
class Administrator extends User {}
```

---

## Method & Property Naming

### Methods

Use **snake_case** for consistency with WordPress:

```php
class Customer extends User {
    // Getters - prefix with get_
    public function get_full_name(): string {}
    public function get_order_count(): int {}

    // Setters - prefix with set_
    public function set_billing_address(array $address): void {}

    // Booleans - prefix with is_, has_, can_, should_
    public function is_active(): bool {}
    public function has_subscription(): bool {}
    public function can_purchase(): bool {}

    // Actions - use verb
    public function send_notification(): void {}
    public function process_order(): bool {}
    public function calculate_total(): float {}
}
```

### Properties

Use **snake_case** for properties:

```php
class Product extends Post {
    // Static configuration
    protected static $post_type = 'product';
    protected static $default_status = 'publish';

    // Instance properties
    protected $price;
    protected $stock_quantity;
    protected $is_featured;

    // Private properties - prefix with underscore
    private $_cache = [];
    private $_initialized = false;
}
```

### Constants

Use **UPPER_SNAKE_CASE**:

```php
class Config {
    public const VERSION = '1.0.0';
    public const MAX_UPLOAD_SIZE = 10485760;
    public const DEFAULT_CURRENCY = 'USD';

    private const INTERNAL_KEY = 'secret';
}
```

---

## Code Formatting

### Indentation

- Use **tabs** for indentation (WordPress standard)
- Use **spaces** for alignment within lines

```php
class Example {
	public function method() {
		$array = [
			'short'      => 'value',    // Aligned with spaces
			'longer_key' => 'value',
		];
	}
}
```

### Braces

Opening braces on same line (K&R style):

```php
// Classes
class Example extends Base {
    // ...
}

// Methods
public function example() {
    // ...
}

// Control structures
if ($condition) {
    // ...
} elseif ($other) {
    // ...
} else {
    // ...
}
```

### Line Length

- **Soft limit:** 100 characters
- **Hard limit:** 120 characters
- Break long lines logically

```php
// Long method chains - break at arrows
$query = Post::query()
    ->where('status', 'publish')
    ->where('author', $user_id)
    ->order_by('date', 'desc')
    ->limit(10)
    ->get();

// Long arrays - one item per line
$config = [
    'option_one'   => 'value',
    'option_two'   => 'value',
    'option_three' => 'value',
];

// Long function calls - break at parameters
$result = $this->process_complex_data(
    $first_parameter,
    $second_parameter,
    $third_parameter
);
```

### Spacing

```php
// Around operators
$sum = $a + $b;
$concat = $str1 . ' ' . $str2;

// After commas
$array = [1, 2, 3, 4];
function example($a, $b, $c) {}

// No space before semicolons
$value = 42;

// Control structures - space before parenthesis
if ($condition) {}
foreach ($items as $item) {}
while ($running) {}

// Functions - no space before parenthesis
function example() {}
$object->method();
```

---

## Documentation

### DocBlocks

All classes and public methods require DocBlocks:

```php
/**
 * Represents a customer in the system.
 *
 * Extends the base User model with customer-specific
 * functionality like order history and preferences.
 *
 * @package Digitalis
 * @since   1.0.0
 */
class Customer extends User {

    /**
     * Gets the customer's order history.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type string $status Order status filter.
     *     @type int    $limit  Maximum orders to return.
     *     @type string $order  Sort order ('asc' or 'desc').
     * }
     * @return Order[] Array of Order objects.
     */
    public function get_orders(array $args = []): array {
        // ...
    }

    /**
     * Checks if customer has an active subscription.
     *
     * @return bool True if subscription is active.
     */
    public function has_subscription(): bool {
        // ...
    }
}
```

### Inline Comments

Use sparingly for complex logic:

```php
public function calculate_discount(): float {
    $discount = 0;

    // Apply volume discount for bulk orders
    if ($this->item_count > 10) {
        $discount += 0.05;
    }

    // Loyalty discount for returning customers
    if ($this->customer->get_order_count() > 5) {
        $discount += 0.03;
    }

    // Cap maximum discount at 20%
    return min($discount, 0.20);
}
```

### TODO/FIXME Comments

```php
// TODO: Implement caching for performance
// FIXME: Handle edge case when user is null
// HACK: Temporary workaround for WC bug #1234
```

---

## Security Practices

### Input Validation

Always validate and sanitize input:

```php
class Form_Handler {
    public function process(array $data): bool {
        // Validate required fields
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }

        // Sanitize input
        $email = sanitize_email($data['email']);
        $name = sanitize_text_field($data['name']);
        $content = wp_kses_post($data['content']);

        // Validate format
        if (!is_email($email)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        return $this->save($email, $name, $content);
    }
}
```

### Output Escaping

Always escape output:

```php
// In templates
<h1><?php echo esc_html($title); ?></h1>
<a href="<?php echo esc_url($link); ?>">
    <?php echo esc_html($text); ?>
</a>
<div class="<?php echo esc_attr($class); ?>">
    <?php echo wp_kses_post($content); ?>
</div>

// In PHP
echo '<input value="' . esc_attr($value) . '">';
echo '<script>var data = ' . wp_json_encode($data) . ';</script>';
```

### Nonce Verification

```php
class Ajax_Handler extends Feature {
    public function get_hooks(): array {
        return [
            'wp_ajax_my_action' => 'handle_ajax',
        ];
    }

    public function handle_ajax(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'my_action_nonce')) {
            wp_send_json_error('Invalid nonce', 403);
        }

        // Verify capability
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied', 403);
        }

        // Process request...
        wp_send_json_success($result);
    }
}
```

### SQL Queries

Use prepared statements:

```php
global $wpdb;

// Good - prepared statement
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
        $user_id,
        'publish'
    )
);

// Bad - direct interpolation
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->posts} WHERE post_author = {$user_id}"
);
```

### Capability Checks

```php
class Admin_Action extends Feature {
    public function delete_item(int $id): bool {
        // Always check capabilities
        if (!current_user_can('delete_posts')) {
            return false;
        }

        // Additional ownership check
        $post = get_post($id);
        if ($post->post_author !== get_current_user_id()
            && !current_user_can('delete_others_posts')) {
            return false;
        }

        return wp_delete_post($id, true);
    }
}
```

---

## WordPress Integration

### Hook Priority

Use named constants or clear values:

```php
class My_Feature extends Feature {
    private const EARLY_PRIORITY = 5;
    private const LATE_PRIORITY = 99;

    public function get_hooks(): array {
        return [
            'init'              => ['early_init', self::EARLY_PRIORITY],
            'template_redirect' => ['late_redirect', self::LATE_PRIORITY],
        ];
    }
}
```

### Prefixing

Prefix all global functions, options, and meta keys:

```php
// Functions
function digitalis_get_option($key) {}

// Options
update_option('digitalis_settings', $value);

// Meta keys
update_post_meta($id, '_digitalis_custom_field', $value);

// Transients
set_transient('digitalis_cache_key', $data, HOUR_IN_SECONDS);
```

### Database Operations

```php
class Database_Migration {
    public function run(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'digitalis_custom';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
```

---

## Error Handling

### Exceptions

Use typed exceptions:

```php
namespace Digitalis\Exceptions;

class ValidationException extends \Exception {}
class NotFoundException extends \Exception {}
class PermissionException extends \Exception {}

// Usage
class Order extends Model {
    public static function get_instance($id): ?static {
        $order = wc_get_order($id);

        if (!$order) {
            throw new NotFoundException("Order {$id} not found");
        }

        return parent::get_instance($id);
    }
}
```

### Error Logging

```php
class Logger {
    public static function error(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Digitalis Error] %s | Context: %s',
                $message,
                wp_json_encode($context)
            ));
        }
    }

    public static function debug(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[Digitalis Debug] %s | Context: %s',
                $message,
                wp_json_encode($context)
            ));
        }
    }
}
```

---

## Performance Guidelines

### Caching

```php
class Expensive_Query {
    public function get_results(): array {
        $cache_key = 'digitalis_expensive_results';

        // Check cache first
        $cached = wp_cache_get($cache_key, 'digitalis');
        if ($cached !== false) {
            return $cached;
        }

        // Perform expensive operation
        $results = $this->run_query();

        // Cache for 1 hour
        wp_cache_set($cache_key, $results, 'digitalis', HOUR_IN_SECONDS);

        return $results;
    }
}
```

### Lazy Loading

```php
class Customer extends User {
    private $_orders = null;

    public function get_orders(): array {
        // Lazy load on first access
        if ($this->_orders === null) {
            $this->_orders = Order::query([
                'customer' => $this->get_id(),
            ]);
        }

        return $this->_orders;
    }
}
```

### Query Optimization

```php
// Bad - N+1 query problem
$posts = Post::query(['limit' => 100]);
foreach ($posts as $post) {
    $author = User::get_instance($post->post_author); // 100 queries!
}

// Good - Eager load authors
$posts = Post::query(['limit' => 100]);
$author_ids = array_unique(wp_list_pluck($posts, 'post_author'));
$authors = User::get_instances($author_ids); // 1 query

foreach ($posts as $post) {
    $author = $authors[$post->post_author] ?? null;
}
```

### Asset Loading

```php
class My_Feature extends Feature {
    public function get_hooks(): array {
        return [
            'wp_enqueue_scripts' => 'enqueue_assets',
        ];
    }

    public function enqueue_assets(): void {
        // Only load when needed
        if (!is_singular('product')) {
            return;
        }

        wp_enqueue_script(
            'digitalis-product',
            DIGITALIS_URL . 'assets/js/product.js',
            ['jquery'],
            DIGITALIS_VERSION,
            true  // Load in footer
        );

        wp_enqueue_style(
            'digitalis-product',
            DIGITALIS_URL . 'assets/css/product.css',
            [],
            DIGITALIS_VERSION
        );
    }
}
```
