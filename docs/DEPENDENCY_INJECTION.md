# Digitalis Framework Dependency Injection

The Digitalis Framework provides lightweight dependency injection through the `Dependency_Injection` trait. It uses PHP reflection to automatically resolve type-hinted parameters to model instances via their `get_instance()` methods.

---

## Table of Contents

- [Overview](#overview)
- [The Dependency_Injection Trait](#the-dependency_injection-trait)
- [Injection Methods](#injection-methods)
- [View Injection](#view-injection)
- [Route Injection](#route-injection)
- [Admin Table Injection](#admin-table-injection)
- [Meta Box Injection](#meta-box-injection)
- [Hook Injection](#hook-injection)
- [Factory/Constructor Injection](#factoryconstructor-injection)
- [Real-World Examples](#real-world-examples)
- [Troubleshooting](#troubleshooting)

---

## Overview

### The Problem

WordPress often passes IDs instead of objects:
- Post columns receive `$post_id`, not `WP_Post`
- REST routes receive request params as scalars
- Views receive user input as primitive values

### The Solution

The DI system automatically converts:
```
ID (scalar) → Model Instance (object)
```

This is done by:
1. Inspecting method type hints via reflection
2. Finding classes with `get_instance()` methods
3. Calling `Class::get_instance($value)` to resolve instances

### Key Principle

**Any class with a `get_instance()` method can be injected.**

This includes all framework models: `Post`, `User`, `Term`, `Order`, and custom models extending them.

---

## The Dependency_Injection Trait

### Location

```
framework/include/patterns/dependency-injection.trait.php
```

### Core Methods

| Method | Purpose |
|--------|---------|
| `inject($call, $args, $values)` | Execute callable with injected args |
| `get_inject_args($call, $args, $values)` | Resolve args without executing |
| `function_inject($reflection, $args, $values)` | Inject into function parameters |
| `method_inject($reflection, $args, $values)` | Inject into method parameters |
| `constructor_inject($class, $args, $values)` | Inject into constructor |
| `array_inject(&$array, $defaults)` | Inject into array values |
| `value_inject($class, &$value)` | Inject single value |

### Classes Using the Trait

| Class | Injection Context |
|-------|-------------------|
| `View` | Default parameters → model instances |
| `Route` | Request params → method parameters |
| `Posts_Table` | Post ID → model in column methods |
| `Screen_Table` | Object ID → model in column methods |
| `Meta_Box` | Render callback parameters |
| `Factory` | Constructor parameters |
| `Has_WP_Hooks` | Hook callback parameters |

---

## Injection Methods

### `inject($call, $args, $values)`

Execute a callable with type-hinted parameters resolved.

```php
// Without injection
$result = call_user_func([$this, 'process'], $order_id);

// With injection - $order_id becomes Order instance
$result = static::inject([$this, 'process'], [$order_id]);

public function process(Order $order) {
    // $order is now an Order instance
}
```

### `value_inject($class, &$value)`

Convert a single value to a class instance.

```php
$order_id = 721;
static::value_inject(Order::class, $order_id);
// $order_id is now Order::get_instance(721)
```

### `array_inject(&$array, $defaults)`

Inject multiple values based on a defaults map.

```php
$params = ['order' => 721, 'user' => 1];
$defaults = ['order' => Order::class, 'user' => User::class];

static::array_inject($params, $defaults);
// $params['order'] is now Order instance
// $params['user'] is now User instance
```

### `constructor_inject($class, $args, $values)`

Create instance with constructor dependencies resolved.

```php
class MyService {
    public function __construct(Logger $logger, Config $config) {
        // ...
    }
}

// Dependencies resolved via get_instance()
$service = static::constructor_inject(MyService::class, []);
```

---

## View Injection

Views inject dependencies when default values are class names.

### How It Works

1. Define class names in `$defaults`
2. Pass scalar values (IDs) when creating view
3. Framework calls `Class::get_instance($value)` automatically

### Basic Example

```php
namespace Digitalis_Co;

class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,  // Class name = injection target
        'user'  => User::class,
        'title' => 'Invoice',     // Regular default (not injected)
    ];
}

// Create with IDs
$view = new Invoice_View([
    'order' => 721,
    'user'  => 1,
]);

// Access as instances
$view['order'];  // Order::get_instance(721)
$view['user'];   // User::get_instance(1)
$view['title'];  // 'Invoice' (unchanged)
```

### Skipping Injection

Some parameters shouldn't be injected even if they have class defaults:

```php
class My_View extends View {
    protected static $defaults = [
        'order'    => Order::class,
        'raw_data' => SomeClass::class,  // Don't inject this
    ];

    protected static $skip_inject = ['raw_data'];
}
```

### Validation with Required

Combine with `$required` to ensure instances exist:

```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,
        'user'  => User::class,
    ];

    protected static $required = ['order', 'user'];

    public function required() {
        // Parent checks that 'order' instanceof Order
        // and 'user' instanceof User
        return parent::required();
    }
}
```

### Conditional Rendering

Use `condition()` for complex validation:

```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,
        'user'  => User::class,
    ];

    public function condition() {
        if (!($this['order'] instanceof Order)) return false;
        if (!($this['user'] instanceof User)) return false;
        if (!$this['user']->can('view_invoice', $this['order'])) return false;

        return true;
    }
}
```

### Derived Parameters

Use `params()` to derive additional parameters from injected ones:

```php
class Document_View extends View {
    protected static $defaults = [
        'document' => Document::class,
        'user'     => User::class,
    ];

    public function params(&$p) {
        // Derive account from document
        if ($p['document'] instanceof Document) {
            $p['account'] = $p['document']->get_account();
        }
    }
}
```

---

## Route Injection

REST routes inject models into `permission()` and `callback()` methods.

### How It Works

1. Define params with `'class'` key in `get_params()`
2. Type-hint method parameters
3. Framework resolves via `request_inject()`

### Parameter Definition

```php
class Order_Route extends Route {
    protected $route = 'order/(?P<order>\d+)';

    protected function get_params() {
        return [
            'order' => [
                'required'          => true,
                'sanitize_callback' => 'absint',
                'class'             => Order::class,  // Enable injection
            ],
        ];
    }
}
```

### Method Injection

```php
class Order_Route extends Route {
    // Both methods receive injected Order instance

    public function permission(WP_REST_Request $request, ?Order $order = null) {
        // $order is Order::get_instance($request->get_param('order'))
        return User::inst()->can('view_order', $order->get_id());
    }

    public function callback(WP_REST_Request $request, ?Order $order = null) {
        // Same $order instance
        return $order->to_array();
    }
}
```

### Error Handling

If injection fails (model not found), the framework returns a `WP_Error`:

```php
// Automatically returned if Order::get_instance(999) returns null
{
    "code": "missing_resource",
    "message": "Unable to locate a 'Order' with 'order' = 999.",
    "data": { "status": 404 }
}
```

### Full Route Example

```php
namespace Digitalis_Co;

use WP_REST_Request;

class Approve_Estimate_Route extends Order_Route {
    protected $route = 'approve-estimate';

    public function permission(WP_REST_Request $request, Order $order = null) {
        $user = User::inst();

        if (!$order->is_estimate()) return false;
        if (!$user->can('approve_estimate', $order->get_id())) return false;

        return true;
    }

    public function callback(WP_REST_Request $request, Order $order = null) {
        $user = User::inst();

        $order->set_status('approved', "Approved by {$user->get_display_name()}");
        $order->save();

        return "<div class='notice ok'>Estimate Approved!</div>";
    }
}
```

---

## Admin Table Injection

Admin tables inject models into column rendering methods.

### Posts_Table

```php
class Projects_Table extends Posts_Table {
    protected $post_type = 'project';

    public function columns(&$columns) {
        $columns['account'] = 'Account';
    }

    // Receives Project instance, not post_id
    public function column_account(Project $project) {
        $account = $project->get_account();

        if ($account) {
            $url = $account->get_edit_url();
            $label = $account->get_name();
            echo "<a href='{$url}'>{$label}</a>";
        }
    }
}
```

### Screen_Table

```php
class Users_Table extends Screen_Table {
    protected $slug = 'users';

    // Receives User instance, not user_id
    public function column_account($output, User $user) {
        $account = $user->get_account();
        return $account ? $account->get_name() : '—';
    }
}
```

### How It Works

The `post_column()` or `column()` method calls:

```php
// In Posts_Table
public function post_column($column, $post_id) {
    $call = [$this, "column_" . str_replace('-', '_', $column)];
    static::inject($call, [$post_id]);
}

// In Screen_Table
public function column($output, $column, $object_id) {
    $call = [$this, "column_" . str_replace('-', '_', $column)];
    return static::inject($call, [$output, $object_id]);
}
```

---

## Meta Box Injection

Meta boxes inject models into render callbacks.

### Example

```php
class Project_Details_Meta_Box extends Meta_Box {
    protected $id     = 'project-details';
    protected $title  = 'Project Details';
    protected $screen = 'project';

    // $post is injected as Project instance
    public function render(Project $project, $args) {
        $account = $project->get_account();
        ?>
        <p><strong>Account:</strong> <?= $account->get_name() ?></p>
        <p><strong>Status:</strong> <?= $project->get_status() ?></p>
        <?php
    }
}
```

### How It Works

```php
// In Meta_Box
public function render_wrap($object, $args) {
    if (is_callable($this->callback)) {
        static::inject($this->callback, [$object, $args]);
    }
}
```

---

## Hook Injection

The `Has_WP_Hooks` trait supports injection in hook execution.

### Custom Hook Execution

```php
class My_Feature extends Feature {
    use Has_WP_Hooks;

    public function run() {
        // Register hook that will receive injected params
        $this->add_filter('my_custom_hook', 'process_order');
    }

    // When hook fires, Order is injected from ID
    public function process_order(Order $order, $extra_data) {
        return $order->get_total() + $extra_data;
    }
}

// Firing the hook with injection
$result = $this->apply_filters('my_custom_hook', $order_id, $extra);
```

### How It Works

```php
// In Has_WP_Hooks
public function do_hook($hook_name, $type = 'filter', ...$args) {
    // Prepare injection args for each callback
    foreach ($wp_hook->callbacks as $priority) {
        foreach ($priority as $callback) {
            $args = static::get_inject_args($callback['function'], $args);
        }
    }

    return static::inject($call, [$hook_name, ...$args]);
}
```

---

## Factory/Constructor Injection

The `Factory` pattern supports constructor injection.

### Example

```php
class My_Service extends Factory {
    protected $logger;
    protected $config;

    public function __construct(Logger $logger, Config $config = null) {
        $this->logger = $logger;
        $this->config = $config ?? new Config();
    }
}

// Constructor params are injected if classes have get_instance()
$service = My_Service::create();
```

### How It Works

```php
// In Factory
public static function create($data = []) {
    // constructor_inject resolves type-hinted params
    $instance = static::constructor_inject(static::class, array_slice(func_get_args(), 2));
    // ...
}
```

---

## Real-World Examples

### Complete View with Injection

```php
namespace Digitalis_Co;

class Document_View extends View {
    protected static $defaults = [
        'document' => Document::class,
        'user'     => User::class,
    ];

    protected static $required = ['document', 'user'];

    public function params(&$p) {
        if ($p['document'] instanceof Document) {
            $p['account'] = $p['document']->get_account();
        }
    }

    public function condition() {
        if (!($this->document instanceof Document)) return false;
        if (!($this->user instanceof User)) return false;
        if (!($this->account instanceof Account)) return false;
        if (!$this->user->can('view_document', $this['document'])) return false;

        return true;
    }

    public function view() {
        ?>
        <div class="document">
            <h1><?= esc_html($this->document->get_title()) ?></h1>
            <p>Account: <?= esc_html($this->account->get_name()) ?></p>
            <?= $this->document->get_content() ?>
        </div>
        <?php
    }
}
```

### Complete Route with Injection

```php
namespace Digitalis_Co;

use WP_REST_Request;

abstract class Order_Route extends Route {
    protected function get_params() {
        return [
            'order' => [
                'required'          => true,
                'sanitize_callback' => 'absint',
                'class'             => Order::class,
            ],
        ];
    }

    public function permission(WP_REST_Request $request, Order $order = null) {
        $user = User::inst();
        return $user->can('view_order', $order->get_id());
    }
}

class Generate_Invoice_Route extends Order_Route {
    protected $route = 'generate_invoice';

    public function permission(WP_REST_Request $request, Order $order = null) {
        $user = User::inst();
        if (!$user->can('view_invoice', $order->get_id())) return false;
        return parent::permission($request, $order);
    }

    public function callback(WP_REST_Request $request, Order $order = null) {
        return $order->generate_invoice();
    }
}
```

### Complete Admin Table with Injection

```php
namespace Digitalis_Co;

use Digitalis\Posts_Table;

class Projects_Table extends Posts_Table {
    protected $post_type = 'project';

    public function columns(&$columns) {
        $this->remove_column('date');
        $columns['account'] = 'Account';
        $columns['status'] = 'Status';
    }

    public function column_account(Project $project) {
        if ($account = $project->get_account()) {
            printf(
                '<a href="%s">%s</a>',
                esc_url($account->get_edit_url()),
                esc_html($account->get_name())
            );
        }
    }

    public function column_status(Project $project) {
        echo esc_html($project->get_status());
    }
}
```

---

## Troubleshooting

### Injection Not Working

**Symptom:** Receiving ID instead of instance

**Causes:**
1. Class doesn't have `get_instance()` method
2. Type hint missing or incorrect
3. For Views: class name not in `$defaults`
4. For Routes: `'class'` not set in params

**Solution:**
```php
// Verify class has get_instance()
if (method_exists(Order::class, 'get_instance')) {
    // Should work
}

// Verify type hint matches defaults
protected static $defaults = [
    'order' => Order::class,  // Must match type hint exactly
];
```

### Instance is Null

**Symptom:** Injected parameter is `null`

**Causes:**
1. `get_instance()` returned null (object not found)
2. Parameter is nullable (`?Order $order`)

**Solution:**
```php
// Check for null in your code
public function callback(WP_REST_Request $request, ?Order $order = null) {
    if (!$order) {
        return new WP_Error('not_found', 'Order not found');
    }
    // ...
}
```

### Union Types

The framework handles union types by using the first type:

```php
// Uses Order for injection (first type)
public function process(Order|Product $item) {
    // If passing Order ID, resolves as Order
}
```

### Skip Injection Issues

**Symptom:** Value being injected when it shouldn't be

**Solution:**
```php
protected static $skip_inject = ['raw_value'];
```

### Performance Considerations

Reflection has overhead. For hot paths:
- Cache reflection results
- Avoid injection in loops
- Pre-resolve instances when possible

---

## Quick Reference

### View Injection

```php
protected static $defaults = [
    'model' => Model::class,  // Enable injection
];
protected static $skip_inject = ['no_inject'];  // Disable for specific keys
```

### Route Injection

```php
protected function get_params() {
    return [
        'param' => ['class' => Model::class],  // Enable injection
    ];
}
public function callback(Request $request, ?Model $model = null) {}
```

### Table Injection

```php
// Just type-hint the column method
public function column_name(Model $model) {}
```

### Manual Injection

```php
// Single value
static::value_inject(Model::class, $value);

// Array of values
static::array_inject($array, ['key' => Model::class]);

// Call with injection
static::inject([$this, 'method'], [$arg1, $arg2]);
```
