# Digitalis Framework Architecture

## Overview

The Digitalis Framework is a modern, object-oriented PHP framework built on top of WordPress. It implements MVC-like patterns with a focus on:

- **Model abstraction** for WordPress entities (posts, users, terms)
- **Factory patterns** for efficient instance management
- **Trait-based composition** for reusable functionality
- **Hook-driven architecture** for WordPress integration

---

## Directory Structure

```
framework/
├── include/
│   ├── objects/           # Core abstract base classes
│   │   ├── Model.abstract.php
│   │   ├── Service.abstract.php
│   │   ├── Feature.abstract.php
│   │   ├── Integration.abstract.php
│   │   └── App.abstract.php
│   │
│   ├── patterns/          # Design pattern implementations
│   │   ├── Design_Pattern.abstract.php
│   │   ├── Creational.abstract.php
│   │   ├── Singleton.abstract.php
│   │   └── Factory.abstract.php
│   │
│   ├── traits/            # Reusable trait mixins
│   │   ├── Has_WP_Hooks.trait.php
│   │   ├── Has_Meta.trait.php
│   │   ├── Has_Models.trait.php
│   │   └── Dependency_Injection.trait.php
│   │
│   ├── utils/             # Utility classes
│   │   ├── Utility.abstract.php
│   │   ├── Call.utility.php
│   │   └── List_Utility.utility.php
│   │
│   ├── wordpress/         # WordPress entity models
│   │   ├── WP_Model.abstract.php
│   │   ├── Post.class.php
│   │   ├── User.class.php
│   │   ├── Term.class.php
│   │   ├── Post_Type.abstract.php
│   │   ├── Taxonomy.abstract.php
│   │   └── Digitalis_Query.class.php
│   │
│   ├── woocommerce/       # WooCommerce integration
│   │   ├── Customer.class.php
│   │   ├── Order.class.php
│   │   ├── Order_Item.class.php
│   │   └── Product_Type.abstract.php
│   │
│   ├── views/             # View rendering system
│   │   ├── View.abstract.php
│   │   ├── Route.abstract.php
│   │   ├── components/    # UI components
│   │   └── fields/        # Form field classes
│   │
│   ├── admin/             # Admin interface classes
│   │   ├── Admin_Page.abstract.php
│   │   ├── Admin_Sub_Page.abstract.php
│   │   ├── Admin_Table.abstract.php
│   │   └── Meta_Box.feature.php
│   │
│   ├── iterators/         # Batch processing
│   │   ├── Iterator.abstract.php
│   │   ├── CSV_Iterator.abstract.php
│   │   ├── Post_Iterator.abstract.php
│   │   └── User_Iterator.abstract.php
│   │
│   ├── acf/               # Advanced Custom Fields
│   │   ├── ACF_Block.factory.php
│   │   └── Bidirectional_Relationship.feature.php
│   │
│   ├── page-builders/     # Page builder support
│   │   ├── Page_Builder.abstract.php
│   │   └── Page_Builder_Manager.singleton.php
│   │
│   └── features/          # Optional features
│
├── templates/             # PHP template files
├── assets/                # Static assets (CSS, JS, images)
├── scss/                  # SCSS stylesheets
└── load.php               # Bootstrap entry point
```

---

## Core Design Patterns

### 1. Singleton Pattern

Ensures only one instance of a class exists. Used for services and managers.

```php
namespace Digitalis;

abstract class Singleton extends Design_Pattern {
    protected static $instances = [];

    public static function get_instance() {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    // Prevents cloning and unserialization
    public function __clone() { throw new Exception("Cannot clone singleton"); }
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}
```

### 2. Factory Pattern

Creates and caches instances with optional property-based caching.

```php
namespace Digitalis;

abstract class Factory extends Design_Pattern {
    protected static $cache_property = null;  // Property to use as cache key
    protected static $cache = [];

    public static function create($data = []) {
        // Creates new instance with optional caching
    }

    public static function get_instance($identifier) {
        // Returns cached instance or creates new one
    }
}
```

### 3. Model Pattern

Entity abstraction with automatic class resolution based on specificity.

```php
namespace Digitalis;

abstract class Model extends Factory {
    protected static $class_map = [];  // Maps IDs to specific classes

    // Automatically resolves to most specific subclass
    public static function get_instance($id) {
        $class = static::resolve_class($id);
        return $class::create(['id' => $id]);
    }
}
```

---

## MVC-like Architecture

### Models

Models represent WordPress entities with automatic caching and validation.

**Hierarchy:**
```
Design_Pattern
└── Factory
    └── Model
        └── WP_Model
            ├── Post
            ├── User
            ├── Term
            └── Comment
```

**Key Features:**
- Instance caching per class/ID
- Dirty state tracking
- Automatic validation via static properties
- Query builders via `Digitalis_Query`

### Views

Views handle rendering with dependency injection, template inheritance, and parameter management. The system provides a declarative way to define UI components with automatic model injection.

> **Full Documentation:** See [VIEW_SYSTEM.md](./VIEW_SYSTEM.md) for complete documentation.
> **Built-in Views Reference:** See [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md) for all included views.

**Hierarchy:**
```
View (implements ArrayAccess)
├── Component          # HTML element with attribute handling
│   └── Field          # Form fields with validation
│       ├── Input, Textarea, Select...
│       └── (19+ field types)
└── [Custom Views]     # Application-specific views
```

**Key Features:**
- **Static Properties** - `$defaults`, `$required`, `$merge`, `$skip_inject` inherited up the chain
- **Inherited Props** - Parent defaults merge with child defaults automatically
- **Dependency Injection** - Class names in defaults resolve to model instances
- **Dual Rendering** - Use `view()` method or separate template file
- **Lifecycle Hooks** - `before_first()`, `before()`, `view()`, `after_first()`, `after()`
- **Parameter Access** - ArrayAccess (`$view['param']`) and property overloading (`$view->param`)
- **Validation** - `required()`, `permission()`, `condition()` checks before render

**Quick Example:**
```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,  // DI: ID → Order instance
        'user'  => User::class,   // DI: ID → User instance
    ];

    protected static $required = ['order'];

    public function condition() {
        return $this['user']->can('view_invoice', $this['order']);
    }
}

// Usage
Invoice_View::render(['order' => 721, 'user' => 1]);
```

**Rendering:**
```php
// Static factory
View::render(['param' => 'value']);

// Direct instantiation
$view = new My_View(['param' => 'value']);
$view->print();

// String cast (__toString)
echo new My_View(['param' => 'value']);
```

### Controllers (Features/Integrations)

Business logic is handled by Features and Integrations that hook into WordPress.

```
Design_Pattern
├── Feature (Factory)    # Optional functionality with hooks
└── Integration (Singleton)  # Plugin/service integrations
```

---

## Hook System

The framework uses a trait-based hook system for WordPress integration.

### Has_WP_Hooks Trait

```php
trait Has_WP_Hooks {
    protected $hooks = [];

    public function add_hook($name, $callback, $priority = 10, $type = 'filter') {
        // Registers WordPress filter or action
    }

    // Override to return hooks array
    public function get_hooks(): array {
        return [
            'hook_name' => 'method_name',
            'hook_name' => ['method_name', $priority],
        ];
    }
}
```

### Hook Naming Convention

Hooks are namespaced using the class name:

```php
// Class: Digitalis\My_Feature
// Hook name: digitalis.my_feature.method_name
$this->add_hook('my_event', 'handle_event');
// Registers: digitalis.my_feature.my_event
```

---

## WordPress Integration Layer

### Post Type Registration

```php
namespace Digitalis;

abstract class Post_Type extends Singleton {
    protected static $slug = '';        // Post type slug
    protected static $singular = '';    // Singular label
    protected static $plural = '';      // Plural label
    protected static $icon = '';        // Dashicon or URL
    protected static $archive = true;   // Has archive
    protected static $position = 25;    // Menu position

    // Auto-hooks for insert, update, delete
    // Admin column customization
    // Query modification support
}
```

### Custom Query System

```php
namespace Digitalis;

class Digitalis_Query extends WP_Query {
    // Deferred execution - call query() explicitly
    // Query_Vars helper for building queries
    // Meta and tax query helpers
}
```

---

## Autoloading System

The framework uses a custom inheritance-aware autoloader via the `Autoloader` trait (used by `App`). This system automatically resolves class dependencies and loads files in the correct order based on inheritance hierarchies encoded in file names.

> **Full Documentation:** See [AUTOLOADER.md](./AUTOLOADER.md) for complete documentation.

### File Naming Convention

Files follow the format: `class-name.parent-class.php`

| Pattern | Meaning | Example |
|---------|---------|---------|
| `name.parent.php` | Class extends parent | `dashboard.account-page.php` |
| `name.abstract.php` | Abstract class | `model.abstract.php` |
| `name.interface.php` | Interface | `renderable.interface.php` |
| `name.trait.php` | Trait | `has-meta.trait.php` |
| `name.class.php` | Standard class | `helper.class.php` |

### Inheritance-Based Load Order

The autoloader parses file names to determine inheritance and sorts accordingly:

```
Given files:
  antique-book.book.php    → Antique_Book extends Book
  book.post.php            → Book extends Post
  post.post.php            → Post (base class)

Load order becomes:
  1. post.post.php         → Base loaded first
  2. book.post.php         → Child loaded second
  3. antique-book.book.php → Grandchild loaded last
```

### Directory Conventions

| Prefix | Behavior |
|--------|----------|
| `_` | Skipped by autoloader (templates, separate contexts) |
| `~` | Conditional loading based on plugin activation |

```
include/
├── models/              # Always loaded
├── _admin/              # Skipped; loaded separately via load_admin()
├── _templates/          # Skipped; not autoloaded
└── ~woocommerce/        # Only loaded if WooCommerce is active
```

### Auto-Instantiation

Classes can define automatic instantiation via `get_auto_instantiation()`:

```php
class My_Feature extends Feature {
    public static function get_auto_instantiation() {
        return 'get_instance';  // Calls My_Feature::get_instance()
    }
}
```

| Return Value | Behavior |
|--------------|----------|
| `false` | No instantiation |
| `true` | `new ClassName()` |
| `'method_name'` | `ClassName::method_name()` |
| `array` | `ClassName::get_instance($array)` |

### Autoload Usage

```php
class My_Plugin extends App {
    public function load() {
        parent::load();

        // Autoload all classes in /include (recursive)
        $this->autoload($this->path . 'include');
    }

    public function load_admin() {
        parent::load_admin();

        // Load admin-only classes
        $this->autoload($this->path . 'include/_admin');
    }
}
```

---

## Dependency Injection

The framework provides lightweight dependency injection through the `Dependency_Injection` trait, using PHP reflection to automatically resolve type-hinted parameters to model instances.

> **Full Documentation:** See [DEPENDENCY_INJECTION.md](./DEPENDENCY_INJECTION.md) for complete documentation.

### Core Concept

When a method parameter is type-hinted with a class that has a `get_instance()` method, the framework automatically resolves scalar values (like IDs) to full model instances.

### Injection Contexts

| Context | Trigger | Example |
|---------|---------|---------|
| **View defaults** | Class name in `$defaults` array | `'order' => Order::class` |
| **Route methods** | Type-hinted `permission()` / `callback()` params | `function callback(Order $order)` |
| **Admin columns** | Type-hinted `column_*()` params | `function column_account(Project $project)` |
| **Constructor** | Type-hinted constructor params | `function __construct(Logger $logger)` |

### View Injection Example

```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,  // Class name as default
        'user'  => User::class,
    ];
}

// Usage - pass IDs, receive instances
$view = new Invoice_View([
    'order' => 721,  // Becomes Order::get_instance(721)
    'user'  => 1,    // Becomes User::get_instance(1)
]);

$view['order'];  // Order instance
$view['user'];   // User instance
```

### Route Injection Example

```php
class Order_Route extends Route {
    protected function get_params() {
        return [
            'order' => [
                'required' => true,
                'class'    => Order::class,  // Enable injection
            ],
        ];
    }

    // $order is automatically injected from request param
    public function permission(WP_REST_Request $request, ?Order $order = null) {
        return User::inst()->can('view_order', $order->get_id());
    }

    public function callback(WP_REST_Request $request, ?Order $order = null) {
        return $order->to_array();
    }
}
```

### Admin Table Injection Example

```php
class Projects_Table extends Posts_Table {
    protected $post_type = 'project';

    // $project is injected from post_id via Project::get_instance($post_id)
    public function column_account(Project $project) {
        $account = $project->get_account();
        echo "<a href='{$account->get_edit_url()}'>{$account->get_name()}</a>";
    }
}
```

### Skipping Injection

Views can skip injection for specific parameters:

```php
class My_View extends View {
    protected static $defaults = [
        'order'    => Order::class,
        'raw_data' => SomeClass::class,
    ];

    // raw_data won't be injected
    protected static $skip_inject = ['raw_data'];
}
```

---

## Model Class Resolution

The framework provides automatic class resolution that determines the most specific subclass when retrieving model instances. This enables polymorphic model handling where `Post::get_instance($id)` automatically returns a `Project` instance if the post is of type 'project'.

> **Full Documentation:** See [CLASS_RESOLUTION.md](./CLASS_RESOLUTION.md) for complete documentation.

### Core Concept

When calling `Model::get_instance($id)`, the framework:
1. Checks all registered subclasses in the class map
2. Evaluates each subclass's specificity and validation
3. Returns an instance of the most specific matching class

### Specificity System

Each model type calculates specificity based on its defining properties:

| Model | Properties | Specificity Formula | Max Value |
|-------|-----------|---------------------|-----------|
| **Post** | `$post_type`, `$post_status`, `$term` | `(bool)type + (bool)status*10 + (bool)term*100` | 111 |
| **User** | `$role` | `(bool)role` | 1 |
| **Term** | `$taxonomy` | `(bool)taxonomy` | 1 |

### How It Works

```php
// Model defines these properties
class Project extends Post {
    protected static $post_type = 'project';  // Specificity: 1
}

class Active_Project extends Project {
    protected static $post_type   = 'project';
    protected static $post_status = 'active';  // Specificity: 11
}

// When called on base class
$model = Post::get_instance(123);

// Framework checks:
// 1. Is post 123 a 'project'? If yes, Project validates (specificity 1)
// 2. Is post 123 also 'active' status? If yes, Active_Project validates (specificity 11)
// 3. Returns Active_Project instance (highest specificity that validates)
```

### Class Map Registration

Classes register themselves during autoload via `static_init()`:

```php
// Pseudocode of what happens during autoload
class Project extends Post {
    public static function static_init() {
        // Registers with all parent classes
        Model::$class_map[Post::class][Project::class] = 1;    // specificity
        Model::$class_map[Model::class][Project::class] = 1;
    }
}
```

### Practical Examples

```php
// Define specific models
class Account extends User {
    protected static $role = 'account';
}

class Document extends Post {
    protected static $post_type = 'document';
}

// Usage - automatic resolution
$user = User::get_instance(5);
// If user 5 has 'account' role → returns Account instance
// Otherwise → returns User instance

$post = Post::get_instance(100);
// If post 100 is type 'document' → returns Document instance
// If post 100 is type 'project' → returns Project instance
// Otherwise → returns Post instance
```

### Controlling Resolution

```php
// Force base class (skip resolution)
$post = Post::get_instance(100, false);  // Always returns Post

// Enable resolution on specific class
class My_Post extends Post {
    protected static $auto_resolve = true;  // Enable for this class
}
```

---

## Data Flow

```
Request
    │
    ▼
┌─────────────────┐
│  WordPress      │
│  (wp-load.php)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Plugin Init    │
│  (load.php)     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  App Bootstrap  │
│  (Autoloader)   │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐ ┌──────────┐
│Models │ │Features/ │
│       │ │Integrations│
└───┬───┘ └────┬─────┘
    │          │
    │    ┌─────┴─────┐
    │    │           │
    ▼    ▼           ▼
┌──────────┐   ┌─────────┐
│ WP Hooks │   │  Views  │
│(Actions/ │   │(Render) │
│ Filters) │   │         │
└──────────┘   └─────────┘
```

---

## Bootstrap Process

1. **Constants defined** in `load.php`
2. **Core utilities** loaded (Call, List_Utility)
3. **Patterns** loaded (Singleton, Factory)
4. **Traits** loaded (hooks, meta, models)
5. **Core objects** loaded (Model, Service, App, View)
6. **WordPress models** loaded (Post, User, Term)
7. **Views & components** loaded
8. **WooCommerce** loaded (on `plugins_loaded` hook)
9. **Features** loaded and initialized

---

## Real-World Implementation: digitalis-co Plugin

The `digitalis-co` plugin demonstrates how to extend the framework for a client project. Below are the actual patterns in use.

### Plugin Structure

```
digitalis-co/
├── include/
│   ├── models/              # Domain models
│   │   ├── Account.class.php
│   │   ├── Project.class.php
│   │   ├── Gateway.class.php
│   │   └── Portal.class.php
│   │
│   ├── post-types/          # Custom post type registration
│   │   ├── Project.singleton.php
│   │   ├── Service.singleton.php
│   │   ├── Portfolio.singleton.php
│   │   └── Gateway.singleton.php
│   │
│   ├── woocommerce/         # WooCommerce customizations
│   │   ├── account-pages/   # Custom account area pages
│   │   ├── emails/          # Custom email classes
│   │   └── order-statuses/  # Custom order statuses
│   │
│   ├── features/            # Hook-based functionality
│   │   ├── Bidirectional_Relationships.feature.php
│   │   └── Custom_Permalinks.feature.php
│   │
│   ├── rest/                # REST API routes
│   │   ├── Invoice_Route.class.php
│   │   └── Estimate_Route.class.php
│   │
│   └── views/               # UI components
│       ├── widgets/         # Dashboard widgets
│       └── components/      # Reusable components
│
├── templates/               # PHP template files
└── assets/                  # CSS, JS, images
```

### Custom Post Types (Real Example)

```php
namespace Digitalis;

class Project extends Singleton {
    protected static $slug     = 'project';
    protected static $singular = 'Project';
    protected static $plural   = 'Projects';
    protected static $icon     = 'dashicons-portfolio';
    protected static $archive  = false;
    protected static $position = 20;

    // Supports
    protected static $supports = ['title', 'editor', 'thumbnail'];

    // Taxonomies to register
    protected static $taxonomies = ['project_category'];
}
```

### Domain Models (Real Example)

```php
namespace Digitalis;

class Account extends Post {
    protected static $post_type = 'account';

    // Get associated projects via ACF relationship
    public function get_projects(): array {
        $project_ids = get_field('account_projects', $this->get_id());
        return Project::get_instances($project_ids ?: []);
    }

    // Get users linked to this account
    public function get_users(): array {
        $user_ids = get_field('account_users', $this->get_id());
        return User::get_instances($user_ids ?: []);
    }

    // Get all orders for this account
    public function get_orders(array $args = []): array {
        return wc_get_orders(array_merge([
            'meta_key'   => '_account_id',
            'meta_value' => $this->get_id(),
        ], $args));
    }
}
```

### Custom Order Statuses

```php
namespace Digitalis;

class Order_Status_Estimate extends Singleton {
    protected static $slug  = 'wc-estimate';
    protected static $label = 'Estimate';
    protected static $color = '#f0ad4e';

    // Statuses that can transition TO this status
    protected static $valid_from = ['pending', 'on-hold'];

    // Statuses that can transition FROM this status
    protected static $valid_to = ['approved', 'cancelled'];
}
```

### WooCommerce Account Pages

```php
namespace Digitalis;

class Projects_Account_Page extends Woo_Account_Page {
    protected static $slug     = 'projects';
    protected static $title    = 'Projects';
    protected static $icon     = 'folder';
    protected static $position = 15;

    // Permission check
    public function can_access(): bool {
        $user = User::current();
        return $user && $user->can('view_projects');
    }

    // Render page content
    public function render(): void {
        $user = User::current();
        $account = $user->get_account();
        $projects = $account->get_projects();

        View::render('account/projects', [
            'projects' => $projects,
            'account'  => $account,
        ]);
    }
}
```

### Permission System

```php
namespace Digitalis;

class User extends \Digitalis\User {

    // Custom capability checks
    public function can(string $capability, $object_id = null): bool {
        switch ($capability) {
            case 'view_projects':
                return $this->has_account();

            case 'approve_estimate':
                $order = wc_get_order($object_id);
                return $order && $this->owns_order($order);

            case 'manage_account':
                return $this->is_account_admin();

            default:
                return parent::can($capability, $object_id);
        }
    }

    public function get_account(): ?Account {
        $account_id = get_field('user_account', 'user_' . $this->get_id());
        return $account_id ? Account::get_instance($account_id) : null;
    }
}
```

### REST API Routes

```php
namespace Digitalis;

class Invoice_Route extends Route {
    protected static $route  = 'invoice/(?P<id>\d+)';
    protected static $method = 'GET';

    public function permission_callback(): bool {
        $user = User::current();
        $order_id = $this->get_param('id');
        return $user && $user->can('view_invoice', $order_id);
    }

    public function callback(): \WP_REST_Response {
        $order = Order::get_instance($this->get_param('id'));

        return new \WP_REST_Response([
            'pdf_url' => $order->get_invoice_url(),
            'order'   => $order->to_array(),
        ]);
    }
}
```

### ACF Bidirectional Relationships

```php
namespace Digitalis;

// Automatically syncs Account <-> Project relationships
Bidirectional_Relationship::create([
    'field_a' => 'account_projects',  // Field on Account
    'field_b' => 'project_account',   // Field on Project
]);

// Automatically syncs Account <-> User relationships
Bidirectional_Relationship::create([
    'field_a' => 'account_users',     // Field on Account
    'field_b' => 'user_account',      // Field on User (user_*)
]);
```

### Dashboard Widgets

```php
namespace Digitalis;

class Box_Link_Widget extends View {
    protected static $defaults = [
        'title'    => '',
        'icon'     => 'arrow-right',
        'href'     => '#',
        'progress' => null,  // Optional progress indicator
        'columns'  => 4,     // Grid columns (out of 12)
    ];

    protected static $required = ['title', 'href'];

    protected static $template = 'widgets/box-link';
}

// Usage in account dashboard
Box_Link_Widget::render([
    'title'    => 'Active Projects',
    'icon'     => 'folder',
    'href'     => wc_get_account_endpoint_url('projects'),
    'progress' => ['current' => 3, 'total' => 5],
    'columns'  => 6,
]);
```

---

## Key Principles

1. **Convention over Configuration** - Follow naming conventions for automatic behavior
2. **Lazy Loading** - Instances created only when needed
3. **Caching by Default** - Models and instances cached automatically
4. **WordPress Native** - Builds on WordPress APIs, doesn't replace them
5. **Composition over Inheritance** - Use traits for shared functionality
6. **Static Configuration** - Use static properties for class behavior
