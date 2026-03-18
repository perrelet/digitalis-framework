# Digitalis Framework Autoloader

The Digitalis Framework uses a custom inheritance-aware autoloader that automatically resolves class dependencies and loads files in the correct order based on inheritance hierarchies encoded in file names.

---

## Table of Contents

- [Overview](#overview)
- [File Naming Convention](#file-naming-convention)
- [Inheritance Resolution](#inheritance-resolution)
- [Directory Conventions](#directory-conventions)
- [Auto-Instantiation](#auto-instantiation)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Filters](#filters)
- [App Class Integration](#app-class-integration)
- [Real-World Examples](#real-world-examples)
- [Troubleshooting](#troubleshooting)

---

## Overview

The autoloader is implemented as the `Autoloader` trait, used by the `App` abstract class. It provides:

- **Inheritance-aware loading** - Parent classes loaded before children
- **Recursive directory scanning** - Processes subdirectories automatically
- **Conditional loading** - Skip or conditionally load directories
- **Auto-instantiation** - Optionally instantiate classes after loading
- **Lifecycle hooks** - Call `hello()` and `static_init()` methods

### Core Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `Autoloader` trait | `include/traits/Autoloader.trait.php` | Main autoloading logic |
| `App` abstract | `include/objects/App.abstract.php` | Base plugin class using trait |
| `Auto_Instantiate` trait | `include/traits/auto-instantiate.trait.php` | Default instantiation behavior |

---

## File Naming Convention

### Format

```
class-name.parent-class.php
```

The file name encodes the class inheritance relationship:

| Part | Description |
|------|-------------|
| `class-name` | The class being defined (kebab-case) |
| `parent-class` | The class it extends (kebab-case) |
| `.php` | File extension |

### Standard Suffixes

When a class doesn't extend another project class, use these identifiers:

| Suffix | Use Case | Example |
|--------|----------|---------|
| `.abstract.php` | Abstract class | `model.abstract.php` |
| `.interface.php` | Interface | `renderable.interface.php` |
| `.trait.php` | Trait | `has-meta.trait.php` |
| `.class.php` | Standalone class | `helper.class.php` |
| `.php` | Simple file (2-part name) | `functions.php` |

### Framework Base Classes

Common parent class identifiers used in file names:

| Identifier | Framework Class | Example File |
|------------|-----------------|--------------|
| `post` | `Digitalis\Post` | `project.post.php` |
| `user` | `Digitalis\User` | `customer.user.php` |
| `term` | `Digitalis\Term` | `category.term.php` |
| `order` | `Digitalis\Order` | `subscription.order.php` |
| `view` | `Digitalis\View` | `card.view.php` |
| `route` | `Digitalis\Route` | `api-endpoint.route.php` |
| `feature` | `Digitalis\Feature` | `notifications.feature.php` |
| `integration` | `Digitalis\Integration` | `stripe.integration.php` |
| `singleton` | `Digitalis\Singleton` | `cache-manager.singleton.php` |
| `post-type` | `Digitalis\Post_Type` | `event.post-type.php` |
| `taxonomy` | `Digitalis\Taxonomy` | `event-category.taxonomy.php` |
| `order-status` | `Digitalis\Order_Status` | `estimate.order-status.php` |
| `woo-account-page` | `Digitalis\Woo_Account_Page` | `orders.woo-account-page.php` |

---

## Inheritance Resolution

### How It Works

The autoloader parses file names to build an inheritance graph, then topologically sorts to ensure parent classes load first.

### Algorithm

1. **Parse file names** - Extract class name and parent from each `.php` file
2. **Build dependency graph** - Map each class to its parent
3. **Prioritize primitives** - Traits, interfaces, and standalone classes first
4. **Topological sort** - Order remaining classes by inheritance depth
5. **Load in order** - Include files from most primitive to most derived

### Example

Given these files in a directory:

```
include/models/
├── antique-book.book.php      → Antique_Book extends Book
├── book.post.php              → Book extends Post
├── rare-book.antique-book.php → Rare_Book extends Antique_Book
└── post.post.php              → Post (references framework Post)
```

**Load order:**

```
1. post.post.php              # No local parent dependency
2. book.post.php              # Depends on post
3. antique-book.book.php      # Depends on book
4. rare-book.antique-book.php # Depends on antique-book
```

### Priority Rules

1. **Traits and interfaces** - Loaded first (no inheritance)
2. **Abstract classes** - Next priority
3. **Classes with external parents** - Before their children
4. **Derived classes** - Loaded after their parents

---

## Directory Conventions

### Recursive Loading

By default, `autoload()` recursively processes subdirectories. Sorting happens at each directory level independently.

```php
$this->autoload($this->path . 'include');  // Recursive by default
$this->autoload($this->path . 'include', false);  // Non-recursive
```

### Underscore Prefix (`_`)

Directories starting with `_` are **skipped** during recursive autoloading.

**Use cases:**

- Templates that shouldn't be autoloaded
- Admin-only code loaded separately
- Context-specific code (CLI, REST, etc.)

```
include/
├── models/          # ✓ Autoloaded
├── features/        # ✓ Autoloaded
├── _admin/          # ✗ Skipped (load separately)
├── _templates/      # ✗ Skipped (not PHP classes)
└── _cli/            # ✗ Skipped (load conditionally)
```

**Loading skipped directories:**

```php
public function load_admin() {
    // Explicitly load admin directory
    $this->autoload($this->path . 'include/_admin');
}
```

### Tilde Prefix (`~`)

Directories starting with `~` are **conditionally loaded** based on plugin activation.

The directory name (minus the `~`) is matched against active plugin directory names.

```
include/
├── models/              # Always loaded
├── ~woocommerce/        # Only if WooCommerce active
├── ~gravityforms/       # Only if Gravity Forms active
└── ~advanced-custom-fields/  # Only if ACF active
```

**How it works:**

```php
// Pseudocode from Autoloader trait
$plugin_dir = substr(basename($dir), 1);  // Remove ~

foreach (get_plugins() as $plugin_name => $plugin) {
    if (dirname($plugin_name) == $plugin_dir && is_plugin_active($plugin_name)) {
        // Load directory
    }
}
```

**Benefits:**

- Prevents fatal errors when optional plugins are deactivated
- Clean separation of plugin-specific code
- Automatic activation/deactivation handling

---

## Auto-Instantiation

### Overview

After loading a class, the autoloader can optionally instantiate it. This is controlled by the `get_auto_instantiation()` static method.

### The `Auto_Instantiate` Trait

```php
namespace Digitalis;

trait Auto_Instantiate {
    public static function get_auto_instantiation() {
        return 'get_instance';
    }
}
```

Use this trait for classes that should auto-instantiate via `get_instance()`.

### Return Values

| Value | Instantiation Method |
|-------|---------------------|
| `false` | No instantiation |
| `true` | `new ClassName()` |
| `'method_name'` | `ClassName::method_name()` |
| `array` | `ClassName::get_instance($array)` |

### Examples

```php
// No auto-instantiation (default for most classes)
class My_Model extends Post {
    public static function get_auto_instantiation() {
        return false;
    }
}

// Instantiate with new
class My_Simple_Class {
    public static function get_auto_instantiation() {
        return true;  // new My_Simple_Class()
    }
}

// Singleton pattern
class My_Singleton extends Singleton {
    public static function get_auto_instantiation() {
        return 'get_instance';  // My_Singleton::get_instance()
    }
}

// Factory with arguments
class My_Factory extends Factory {
    public static function get_auto_instantiation() {
        return ['config' => 'value'];  // My_Factory::get_instance(['config' => 'value'])
    }
}
```

### Abstract Class Protection

The autoloader automatically prevents instantiation of abstract classes:

```php
// From Autoloader trait
$reflection = new ReflectionClass($class_name);
if ($reflection->isAbstract()) $instantiation = false;
if (strpos(basename($path), '.abstract.') !== false) $instantiation = false;
```

---

## Lifecycle Hooks

After including a file, the autoloader calls two optional static methods:

### `hello()`

Called immediately after the class is loaded. Use for registration or setup that doesn't require instantiation.

```php
class My_Post_Type extends Post_Type {
    public static function hello() {
        // Register post type immediately
        register_post_type(static::$slug, static::get_args());
    }
}
```

### `static_init()`

Called after `hello()`. Use for additional static initialization.

```php
class My_Feature extends Feature {
    public static function static_init() {
        // Add static hooks
        add_action('init', [static::class, 'register']);
    }
}
```

### Call Order

```
1. include_once $path
2. $class_name::hello()       (if method exists)
3. $class_name::static_init() (if method exists)
4. Instantiation              (if configured)
```

---

## Filters

The autoloader provides filters to modify instantiation behavior:

### Global Filter

```php
// Fires for every class
add_filter('Digitalis/Instantiate/', function($instantiation, $class_name, $path) {
    // Disable all auto-instantiation
    if ($some_condition) return false;

    return $instantiation;
}, 10, 3);
```

### Class-Specific Filter

```php
// Filter format: 'Digitalis/Instantiate/' . namespace/class_name
add_filter('Digitalis/Instantiate/Digitalis_Co/My_Feature', function($instantiation, $path) {
    // Override instantiation for specific class
    return 'custom_factory_method';
}, 10, 2);
```

---

## App Class Integration

### Basic Plugin Setup

```php
<?php
namespace My_Plugin;

use Digitalis\App;

class My_Plugin extends App {

    public function load() {
        parent::load();

        // Plugin-specific loading
        $this->load_feature('wp/custom-feature.feature.php');
    }

    public function load_admin() {
        parent::load_admin();

        // Admin-specific code
    }
}

// Bootstrap
new My_Plugin();
```

### App Lifecycle

```php
// App constructor
public function __construct() {
    $this->path = plugin_dir_path(...);
    $this->url = plugin_dir_url(...);

    add_action('plugins_loaded', [$this, 'load']);
    if (is_admin()) add_action('plugins_loaded', [$this, 'load_admin']);
}

// Default load() method
public function load() {
    $this->autoload();  // Loads from $this->path
}

// Default load_admin() method
public function load_admin() {
    $this->autoload($this->path . '_admin');
}
```

---

## Real-World Examples

### digitalis-co Plugin Structure

```
digitalis-co/
├── include/
│   ├── digitalis-co.app.php           # Main App class
│   │
│   ├── models/
│   │   ├── user.user.php              # User extends \Digitalis\User
│   │   ├── account.user.php           # Account extends User
│   │   ├── project.post.php           # Project extends Post
│   │   └── order.order.php            # Order extends \Digitalis\Order
│   │
│   ├── post-types/
│   │   ├── project.post-type.php
│   │   ├── document.post-type.php
│   │   └── gateway.post-type.php
│   │
│   ├── post-statuses/
│   │   ├── estimate.order-status.php
│   │   ├── approved.order-status.php
│   │   └── payment-sent.order-status.php
│   │
│   ├── routes/
│   │   ├── route.route.php            # Base Route class
│   │   ├── order-route.route.php      # Order_Route extends Route
│   │   ├── approve-estimate.order-route.php
│   │   └── generate-invoice.order-route.php
│   │
│   ├── _admin/                        # Admin-only (skipped)
│   │   ├── pages/
│   │   └── tables/
│   │
│   └── ~woocommerce/                  # Conditional (WooCommerce)
│       ├── account-pages/
│       │   ├── account-page.woo-account-page.php
│       │   ├── dashboard.account-page.php
│       │   ├── billing.account-page.php
│       │   └── projects.account-page.php
│       │
│       ├── models/
│       └── _emails/                   # Skipped within conditional
```

### Inheritance Chain Example

**WooCommerce Account Pages:**

```
Framework:
  Digitalis\Woo_Account_Page (base)

Plugin (loaded in order):
  1. account-page.woo-account-page.php
     → Account_Page extends Woo_Account_Page

  2. dashboard.account-page.php
     → Dashboard_Account_Page extends Account_Page

  3. billing.account-page.php
     → Billing_Account_Page extends Account_Page

  4. projects.account-page.php
     → Projects_Account_Page extends Account_Page
```

**REST Routes:**

```
Framework:
  Digitalis\Route (base)

Plugin (loaded in order):
  1. route.route.php
     → Route extends \Digitalis\Route (namespace override)

  2. order-route.route.php
     → Order_Route extends Route

  3. approve-estimate.order-route.php
     → Approve_Estimate extends Order_Route

  4. generate-invoice.order-route.php
     → Generate_Invoice extends Order_Route
```

---

## Troubleshooting

### Common Issues

#### Class Not Found

**Symptom:** `Class 'Namespace\ClassName' not found`

**Causes:**
- File name doesn't match class name
- Parent class file name doesn't match inheritance
- Directory excluded with `_` prefix

**Solution:**
```php
// Verify file naming
// Class: My_Custom_Post
// File should be: my-custom-post.post.php (if extends Post)
```

#### Wrong Load Order

**Symptom:** `Class 'ParentClass' not found` when loading child

**Causes:**
- Parent file name doesn't indicate it's a base class
- Circular dependency in naming

**Solution:**
```php
// Ensure parent's file name is correct
// parent-class.class.php or parent-class.abstract.php
```

#### Conditional Directory Not Loading

**Symptom:** `~pluginname/` directory not loading

**Causes:**
- Plugin not active
- Directory name doesn't match plugin directory

**Solution:**
```php
// Check plugin directory name matches exactly
// ~woocommerce/     → matches plugins/woocommerce/
// ~my-plugin/       → matches plugins/my-plugin/
```

#### Class Instantiated Multiple Times

**Symptom:** Constructor called multiple times

**Causes:**
- Not using Singleton pattern
- `get_auto_instantiation()` returning `true` instead of `'get_instance'`

**Solution:**
```php
class My_Singleton extends Singleton {
    public static function get_auto_instantiation() {
        return 'get_instance';  // Not true!
    }
}
```

### Debugging

```php
// Temporarily add to autoload to debug load order
public function autoload($path = null, $recursive = true, $ext = 'php', &$objs = [], $depth = 0) {
    error_log("Autoloading: $path");
    return parent::autoload($path, $recursive, $ext, $objs, $depth);
}

// Or filter to see what's being instantiated
add_filter('Digitalis/Instantiate/', function($inst, $class, $path) {
    error_log("Loading: $class from $path (instantiate: " . var_export($inst, true) . ")");
    return $inst;
}, 10, 3);
```

---

## Quick Reference

### File Naming

```
class-name.parent-class.php     # Standard inheritance
class-name.abstract.php         # Abstract class
class-name.interface.php        # Interface
class-name.trait.php            # Trait
class-name.class.php            # Standalone class
```

### Directory Prefixes

```
include/regular/      # ✓ Always loaded
include/_skipped/     # ✗ Skipped (load manually)
include/~plugin/      # ? Conditional on plugin active
```

### Auto-Instantiation

```php
return false;           // Don't instantiate
return true;            // new ClassName()
return 'method';        // ClassName::method()
return ['args'];        // ClassName::get_instance(['args'])
```

### Lifecycle

```
include → hello() → static_init() → instantiate
```
