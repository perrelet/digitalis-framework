# Digitalis Framework: Hooks System

Reference for the WordPress hooks integration system.

---

## Table of Contents

- [Overview](#overview)
- [Has_WP_Hooks Trait](#has_wp_hooks-trait)
- [Feature Class](#feature-class)
- [Integration Class](#integration-class)
- [Hook Naming](#hook-naming)
- [Registering Hooks](#registering-hooks)
- [Firing Hooks](#firing-hooks)
- [Framework Hooks](#framework-hooks)
- [Common Patterns](#common-patterns)
- [Quick Reference](#quick-reference)

---

## Overview

The Digitalis Framework provides a trait-based hook system that wraps WordPress's `add_action`, `add_filter`, `do_action`, and `apply_filters` with additional features:

- **Automatic parameter counting** - No need to specify accepted args
- **Method name shortcuts** - Use `'method_name'` instead of `[$this, 'method_name']`
- **Hook name sanitization** - Consistent naming with dot notation
- **Dependency injection** - Hook callbacks can receive injected dependencies
- **Batch registration** - Register multiple hooks via `get_hooks()` array

---

## Has_WP_Hooks Trait

The core trait for working with WordPress hooks.

### Using the Trait

```php
namespace Digitalis;

class My_Class {
    use Has_WP_Hooks;

    public function __construct() {
        // Register hooks
        $this->add_action('init', 'on_init');
        $this->add_filter('the_content', 'filter_content');
    }

    public function on_init() {
        // Called on 'init' action
    }

    public function filter_content($content) {
        // Filter and return content
        return $content;
    }
}
```

### Registering Hooks

#### Single Hook

```php
// Action - method name as string
$this->add_action('init', 'my_method');

// Action - with priority
$this->add_action('init', 'my_method', 20);

// Filter - method name as string
$this->add_filter('the_content', 'filter_content');

// Filter - with priority
$this->add_filter('the_content', 'filter_content', 5);

// Closure
$this->add_action('init', function() {
    // Do something
});

// External callback
$this->add_action('init', [$other_object, 'method']);
```

#### Multiple Hooks

```php
// Batch register
$this->add_hooks([
    'init'        => 'on_init',
    'wp_loaded'   => 'on_loaded',
    'the_content' => ['filter_content', 20],  // With priority
]);

// Batch actions
$this->add_actions([
    'init'      => 'on_init',
    'wp_loaded' => 'on_loaded',
]);

// Batch filters
$this->add_filters([
    'the_title'   => 'filter_title',
    'the_content' => 'filter_content',
]);
```

### Removing Hooks

```php
// Remove single hook
$this->remove_action('init', 'my_method');
$this->remove_filter('the_content', 'filter_content');

// Remove with priority (must match registration)
$this->remove_action('init', 'my_method', 20);

// Remove all hooks at priority
$this->remove_all_actions('init', 10);
$this->remove_all_filters('the_content', 10);

// Remove all hooks (any priority)
$this->remove_all_actions('init');
$this->remove_all_filters('the_content');
```

### Checking Hooks

```php
// Check if hook has any callbacks
$this->has_action('init');
$this->has_filter('the_content');

// Check if specific callback is registered
$this->has_action('init', 'my_method');
$this->has_filter('the_content', 'filter_content');

// Check if currently executing
$this->doing_action('init');
$this->doing_filter('the_content');

// Check if already executed
$this->did_action('init');      // Returns count
$this->did_filter('the_content'); // Returns count
```

### Firing Hooks

```php
// Fire action
$this->do_action('my_custom_action', $arg1, $arg2);

// Apply filter
$value = $this->apply_filters('my_custom_filter', $value, $context);

// With array of args
$this->do_action_ref_array('my_action', [$arg1, $arg2]);
$value = $this->apply_filters_ref_array('my_filter', [$value, $context]);
```

### Auto-Named Filters with `filter_value()`

Automatically generates a hook name from the calling method:

```php
class My_Feature extends Feature {

    public function get_items() {
        $items = ['a', 'b', 'c'];

        // Hook name: digitalis.my_feature.get_items
        return $this->filter_value($items);
    }

    public function get_limit() {
        $limit = 10;

        // Hook name: digitalis.my_feature.get_limit
        // Additional args passed to filter
        return $this->filter_value($limit, $this->context);
    }
}

// External code can filter:
add_filter('digitalis.my_feature.get_items', function($items) {
    $items[] = 'd';
    return $items;
});
```

---

## Feature Class

Features are hook-enabled factories with a `get_hooks()` method.

### Basic Feature

```php
namespace Digitalis;

class Custom_Emails extends Feature {

    public function get_hooks() {
        return [
            'woocommerce_order_status_changed' => 'on_status_change',
            'woocommerce_email_classes'        => 'register_emails',
        ];
    }

    public function on_status_change($order_id, $old_status, $new_status) {
        if ($new_status === 'approved') {
            $this->send_approval_email($order_id);
        }
    }

    public function register_emails($emails) {
        $emails['Approval_Email'] = new Approval_Email();
        return $emails;
    }
}
```

### Feature with Priority

```php
public function get_hooks() {
    return [
        // [callback, priority]
        'init' => ['early_init', 5],
        'wp_loaded' => ['late_loaded', 99],

        // [callback, priority, type]
        'the_content' => ['filter_content', 10, 'filter'],
    ];
}
```

### Feature with `run()` Method

For initialization that doesn't need hooks:

```php
class My_Feature extends Feature {

    public function run() {
        // Called after hooks are registered
        // Good for one-time setup
        $this->setup_defaults();
    }

    public function get_hooks() {
        return [
            'init' => 'on_init',
        ];
    }
}
```

### Loading Features

```php
// Via autoloader (automatic if get_auto_instantiation returns truthy)
class My_Feature extends Feature {
    public static function get_auto_instantiation() {
        return 'get_instance';
    }
}

// Manual loading
My_Feature::load();
My_Feature::get_instance();
```

---

## Integration Class

Integrations are singletons for third-party plugin/service integration.

### Basic Integration

```php
namespace Digitalis;

class Slack_Integration extends Integration {

    public function get_hooks() {
        return [
            'digitalis/project/created'   => 'notify_created',
            'digitalis/project/completed' => 'notify_completed',
        ];
    }

    public function run() {
        // Check if Slack is configured
        if (!$this->is_configured()) {
            return;
        }
    }

    public function notify_created(Project $project) {
        $this->send("New project: {$project->get_title()}");
    }

    private function is_configured() {
        return (bool) get_option('slack_webhook_url');
    }
}
```

### Conditional Integration

```php
class WooCommerce_Integration extends Integration {

    public static function get_auto_instantiation() {
        // Only load if WooCommerce is active
        return class_exists('WooCommerce') ? 'get_instance' : false;
    }

    public function get_hooks() {
        return [
            'woocommerce_order_status_completed' => 'on_complete',
        ];
    }
}
```

---

## Hook Naming

### Sanitization Rules

Hook names are automatically sanitized:

| Input | Output |
|-------|--------|
| `Digitalis\My_Feature` | `digitalis.my_feature` |
| `my-hook-name` | `my.hook.name` |
| `CamelCase` | `camelcase` |
| `multiple___underscores` | `multiple.underscores` |

### Convention

Framework hooks follow the pattern:

```
digitalis/{component}/{action}
digitalis/{component}/{class}/{action}
Digitalis/{Component}/{Class}/{Property}
```

### Building Hook Names

```php
// String
$this->build_hook_name($name);  // Modifies in place

// Array - joined with delimiter
$name = ['digitalis', 'my_feature', 'action'];
$this->build_hook_name($name);  // 'digitalis.my_feature.action'
```

---

## Framework Hooks

### Autoloader Hooks

```php
// Filter instantiation for any class
add_filter('Digitalis/Instantiate/', function($instantiation, $class_name, $path) {
    // Return false to prevent instantiation
    // Return 'method_name' to call that method
    // Return true for new ClassName()
    return $instantiation;
}, 10, 3);

// Filter instantiation for specific class
add_filter('Digitalis/Instantiate/Digitalis/My_Class', function($instantiation, $path) {
    return $instantiation;
}, 10, 2);
```

### Class Resolution Hook

```php
// Modify class name during resolution
add_filter('Digitalis/Class/Digitalis/My_Class', function($class_name, $data) {
    // Return different class name
    if ($data['context'] === 'admin') {
        return 'Digitalis\Admin_My_Class';
    }
    return $class_name;
}, 10, 2);
```

### Post Type Hooks

```php
// Filter post type args
add_filter('Digitalis/Post_Type/Digitalis\Project/Args', function($args) {
    $args['public'] = false;
    return $args;
});

// Filter rewrite rules
add_filter('Digitalis/Post_Type/Digitalis\Project/Rewrite', function($rewrite) {
    $rewrite['slug'] = 'custom-slug';
    return $rewrite;
});

// Filter supports
add_filter('Digitalis/Post_Type/Digitalis\Project/Supports', function($supports) {
    $supports[] = 'page-attributes';
    return $supports;
});

// Filter labels
add_filter('Digitalis/Post_Type/Digitalis\Project/Labels', function($labels) {
    $labels['menu_name'] = 'Custom Menu Name';
    return $labels;
});
```

### Taxonomy Hooks

```php
// Filter taxonomy args
add_filter('Digitalis/Taxonomy/Digitalis/Project_Category/Args', function($args) {
    $args['hierarchical'] = false;
    return $args;
});
```

### Field Group Hook

```php
// Filter fields in Field_Group component
add_filter('Digitalis/Field_Group/Field', function($field, $params, $class) {
    // Modify field configuration
    if ($field['name'] === 'email') {
        $field['required'] = true;
    }
    return $field;
}, 10, 3);
```

### WooCommerce Hooks

```php
// Filter account page icon
add_filter('digitalis_woocommerce_account_page_icon', function($icon, $slug, $page) {
    if ($slug === 'orders') {
        return 'custom-icon';
    }
    return $icon;
}, 10, 3);
```

---

## Common Patterns

### Custom Action Hooks

```php
class Project extends Post {

    public function approve() {
        $this->set_status('approved');
        $this->save();

        // Fire custom action
        do_action('digitalis/project/approved', $this);
    }

    public function complete() {
        $this->update_field('completed_date', date('Y-m-d'));
        $this->set_status('completed');
        $this->save();

        do_action('digitalis/project/completed', $this, User::current());
    }
}

// Listen to custom hooks
class Notifications extends Feature {

    public function get_hooks() {
        return [
            'digitalis/project/approved'  => 'on_approved',
            'digitalis/project/completed' => 'on_completed',
        ];
    }

    public function on_approved(Project $project) {
        // Send notification
    }

    public function on_completed(Project $project, User $user) {
        // Send notification
    }
}
```

### Custom Filter Hooks

```php
class Invoice_Generator extends Feature {

    public function generate(Order $order) {
        $data = [
            'order'    => $order,
            'items'    => $order->get_items(),
            'total'    => $order->get_total(),
            'template' => 'default',
        ];

        // Allow filtering invoice data
        $data = apply_filters('digitalis/invoice/data', $data, $order);

        // Allow filtering template
        $template = apply_filters('digitalis/invoice/template', $data['template'], $order);

        return $this->render($template, $data);
    }
}

// Customize invoice
add_filter('digitalis/invoice/data', function($data, $order) {
    $data['logo'] = get_option('company_logo');
    $data['footer'] = get_option('invoice_footer');
    return $data;
}, 10, 2);
```

### Conditional Hook Registration

```php
class Admin_Features extends Feature {

    public function get_hooks() {
        $hooks = [];

        // Only in admin
        if (is_admin()) {
            $hooks['admin_menu'] = 'add_menu';
            $hooks['admin_init'] = 'admin_init';
        }

        // Only on frontend
        if (!is_admin()) {
            $hooks['wp_enqueue_scripts'] = 'enqueue_scripts';
        }

        // Only for logged-in users
        if (is_user_logged_in()) {
            $hooks['init'] = 'user_init';
        }

        return $hooks;
    }
}
```

### AJAX Hooks

```php
class Ajax_Handler extends Feature {

    public function get_hooks() {
        return [
            // Logged-in users
            'wp_ajax_my_action' => 'handle_ajax',

            // Non-logged-in users
            'wp_ajax_nopriv_my_action' => 'handle_ajax',
        ];
    }

    public function handle_ajax() {
        check_ajax_referer('my_nonce', 'nonce');

        $result = $this->process($_POST);

        wp_send_json_success($result);
    }
}
```

### WooCommerce Hooks

```php
class Order_Customizations extends Feature {

    public function get_hooks() {
        return [
            // Order status changes
            'woocommerce_order_status_changed'     => 'on_status_change',
            'woocommerce_order_status_completed'   => 'on_completed',
            'woocommerce_order_status_processing' => 'on_processing',

            // Checkout
            'woocommerce_checkout_order_processed' => 'on_checkout',
            'woocommerce_payment_complete'         => 'on_payment',

            // Cart
            'woocommerce_add_to_cart'    => 'on_add_to_cart',
            'woocommerce_cart_updated'   => 'on_cart_update',

            // Product
            'woocommerce_product_options_general_product_data' => 'add_product_fields',
            'woocommerce_process_product_meta'                 => 'save_product_fields',

            // Emails
            'woocommerce_email_classes' => 'register_emails',
        ];
    }

    public function on_status_change($order_id, $old_status, $new_status) {
        $order = Order::get_instance($order_id);
        // Handle status change
    }
}
```

### ACF Hooks

```php
class ACF_Customizations extends Feature {

    public function get_hooks() {
        return [
            // Field value filters
            'acf/load_value/name=project_account' => 'load_account_value',
            'acf/update_value/name=project_account' => 'update_account_value',

            // Field display
            'acf/render_field/name=project_status' => 'render_status_field',

            // Validation
            'acf/validate_value/name=email' => 'validate_email',

            // Save post
            'acf/save_post' => ['after_save', 20],
        ];
    }
}
```

---

## Quick Reference

### Has_WP_Hooks Methods

| Method | Description |
|--------|-------------|
| `add_action($hook, $callback, $priority)` | Register action |
| `add_filter($hook, $callback, $priority)` | Register filter |
| `add_hooks($hooks, $type)` | Batch register |
| `add_actions($actions)` | Batch actions |
| `add_filters($filters)` | Batch filters |
| `remove_action($hook, $callback, $priority)` | Remove action |
| `remove_filter($hook, $callback, $priority)` | Remove filter |
| `remove_all_actions($hook, $priority)` | Remove all actions |
| `remove_all_filters($hook, $priority)` | Remove all filters |
| `has_action($hook, $callback)` | Check action exists |
| `has_filter($hook, $callback)` | Check filter exists |
| `do_action($hook, ...$args)` | Fire action |
| `apply_filters($hook, $value, ...$args)` | Apply filter |
| `filter_value($value, ...$args)` | Auto-named filter |
| `doing_action($hook)` | Currently executing |
| `doing_filter($hook)` | Currently executing |
| `did_action($hook)` | Execution count |
| `did_filter($hook)` | Execution count |

### Hook Array Formats

```php
// Basic
'hook_name' => 'method_name'

// With priority
'hook_name' => ['method_name', 20]

// With priority and type
'hook_name' => ['method_name', 20, 'filter']

// Closure
'hook_name' => function($arg) { return $arg; }
```

### Framework Hooks Summary

| Hook | Type | Description |
|------|------|-------------|
| `Digitalis/Instantiate/` | Filter | All class instantiation |
| `Digitalis/Instantiate/{Class}` | Filter | Specific class instantiation |
| `Digitalis/Class/{Class}` | Filter | Class name resolution |
| `Digitalis/Post_Type/{Class}/Args` | Filter | Post type args |
| `Digitalis/Post_Type/{Class}/Rewrite` | Filter | Post type rewrite |
| `Digitalis/Post_Type/{Class}/Supports` | Filter | Post type supports |
| `Digitalis/Post_Type/{Class}/Labels` | Filter | Post type labels |
| `Digitalis/Taxonomy/{Class}/Args` | Filter | Taxonomy args |
| `Digitalis/Field_Group/Field` | Filter | Field_Group fields |
| `digitalis_woocommerce_account_page_icon` | Filter | Account page icons |

### Naming Convention

```
Custom hooks:     digitalis/{feature}/{action}
Class hooks:      digitalis.{class_name}.{method_name}
Framework hooks:  Digitalis/{Component}/{Class}/{Property}
```
