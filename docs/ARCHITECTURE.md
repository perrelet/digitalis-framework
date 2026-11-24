# Lattice Architecture

## Overview

Lattice is a modern, object-oriented PHP framework built on top of WordPress. It implements MVC-like patterns with a focus on:

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
│   │   ├── App.abstract.php
│   │   └── Request_Resolver.singleton.php
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
│   │   ├── Dependency_Injection.trait.php
│   │   └── Resolvable.trait.php
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
│   │   └── Query_Vars.class.php
│   │
│   ├── integrations/
│   │   ├── acf/           # ACF: the field provider, rows, blocks, option pages, the AJAX form, the bidirectional relationship feature
│   │   └── _woocommerce/  # Declared only after woocommerce_loaded (outside the sweep): Customer, Order, Order_Item, Order_Status, Product_Type, Woo_Account_Page, the two themes, Is_Woo_Customer, Has_Order_Version, Has_WC_Data
│   │
│   ├── views/             # View rendering system
│   │   ├── View.abstract.php
│   │   ├── Route.abstract.php
│   │   ├── Layout.view.php
│   │   ├── Page_View.view.php
│   │   ├── Header.component.php
│   │   ├── Footer.component.php
│   │   ├── Modals.component.php
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
│   └── features/          # Optional features
│
├── templates/             # PHP template files
├── assets/                # Static assets (CSS, JS, images)
└── load.php               # Bootstrap entry point
```

---

## Core Design Patterns

Every instance the framework keeps lives in one registry, `Lattice\Registry`, under a kind, a group and a key. There are four kinds: a **registrant** hooks into WordPress or registers with it (features, routes, admin pages, post types, iterators, `Query_Manager`); a **service** never hooks (`Log`, `Credentials`); an **app** is a registrant, one per class; an **entity** is stored data seen through a model, kept as a record with its views.

A registrant's life has two steps. Its constructor only configures. Once the instance is kept, the framework switches it on: `get_hooks()` is registered, then `run()` is called. Every hook added through `Has_WP_Hooks` is recorded on the instance, and `switch_off()` removes them. `Registrant` (on `Factory`) and `Singleton` share this through the `Is_Registrant` trait; a family base adds its own hooks in `switch_on()` and calls the parent, while a site's class uses `get_hooks()` and `run()`.

Switch-on is batched. `Lattice\Batch` keeps a frame per `App::boot()`: everything the app's `load*()` methods build is kept, then switched on together once they return, before `ensure_schema()`, `boot_shared()` and the `boot_*()` branches. A `declare()` made inside a `run()` joins the same drain. Only a build outside any batch switches on at once. `load.php` builds `Lattice\Framework`, the framework's own app, which boots on `plugins_loaded` at priority 5 (ahead of the consumers' 10, or at once when the framework is first loaded later, from a theme) and loads `Query_Manager`, `Task_Handler` and the deleted-entity listeners.

### 1. Singleton Pattern

One instance per class, found bare. Used for registration families (`Post_Type`, `Taxonomy`, `Post_Status`, `User_Role`, `Iterator`, `Cron_Scheduler`) and managers.

```php
namespace Lattice;

abstract class Singleton extends Creational {
    use Auto_Instantiate, Is_Registrant;

    const INSTANCE_KIND = 'registrant';

    protected static $cache_group = self::class; // a family base sets its own, so its members are audited for the slugs they claim

    public function __construct () {}            // public: `new` builds an inert instance

    public static function get_instance () {
        // the held instance, else may_build(), keep, then switch on through the batch
    }

    public function run () {}
}
```

A singleton takes no config and no name. `new` builds an inert instance that is never kept or switched on. Two members of one family claiming the same `$slug` is a strict violation. Cloning and unserializing throw.

### 2. Factory Pattern

`Factory` builds and keeps registrants and services under `$cache_group` and a key: the name, which is the value of `$cache_property` (`id` for features, `slug` for admin pages and shortcodes), else the class. A family is one group: every feature shares one, so a base class finds a subclass's instance by name and two classes claiming one name are refused. `Registrant extends Factory` adds the switch-on; `Service extends Factory` never hooks.

```php
namespace Lattice;

abstract class Factory extends Creational {
    protected static $cache_group    = '__global__';
    protected static $cache_property = null;    // the property that holds the name
    protected static $named          = false;   // true: built only under names given to declare()

    public static function get_instance(mixed $data = null) { /* finds; builds a single class bare */ }
    public static function declare(string|array $name = [], array $config = []) { /* builds live under a name */ }
    public static function create(mixed $data = []) { /* builds inert */ }
    public static function get_group_instances(?string $group = null) { /* every instance in a group (App::get_apps()) */ }
    public static function get_instance_map() { /* ['group' => ['key' => ClassName]] */ }
}
```

Three verbs cover every case:

| Call | Answers |
|---|---|
| `Thing::get_instance()` | The class's own instance, under its declared default name, else its class. A single class is built on first find; a named class (`$named = true`) answers `null` |
| `Thing::get_instance('name')` | The registrant holding `name`, or `null`. A find by name never builds a registrant; a service (`Log::get_instance($path)`) is built on any find |
| `Thing::declare($config)` | The class built live under its default name with that config |
| `Thing::declare('name', $config)` | A named class built live under `name`; a single class is refused any name but its default |
| `Thing::create($config)` | A new inert instance: never kept, never switched on, found by nothing. A scalar is refused |

A second build of a live name is refused aloud, same class included: strict throws, and outside strict one log line names the app that built the holder. An owner whose `declare($config)` comes after a bare find already built the defaults is refused the same way, so the owner declares and everyone else finds. `get_instance([...])` with a config is refused: declare it, or `create()` one nothing finds.

A config is on the instance before its constructor runs, so a constructor reads what it was configured with. In a family named by a property the declared name fills that property, and a config giving another value is refused. A family base declares no default name (its placeholder is `null`), so only a concrete class's own declaration names it. A route's name is only a name: its path comes in the config, since plugins share paths and a GET and a POST route share one. `Model` refuses `declare()`: a model is found by its id. An app is single, found and declared by its class.

Every build, by a find, `declare()` or the autoloader, passes one gate, `Creational::may_build()`, asked on a registry miss: the `lattice.instantiate` filters (seeded `true`; only `false` refuses), then the class's `instance_condition($data)`, then `prepare_build()` (a feature loads its `$requires`). A class the site opted out is refused on every path.

### 3. Model Pattern

Entity abstraction with automatic class resolution based on specificity, and one record per stored entity.

```php
namespace Lattice;

abstract class Model extends Factory {
    const INSTANCE_KIND = 'entity';

    // Resolves to the most specific subclass, then answers the held view for that class, else a view of the held record, else reads it
    public static function get_instance(mixed $data = null, ?bool $auto_resolve = null) {
        [$id, $class_name] = static::resolve_data($data, $auto_resolve);
        if ($class_name != static::class) return $class_name::get_instance($data, false);
        // held view (null if deleted), else validate and `new $class_name($id)`
    }
}
```

A model is a view of a `Lattice\Model_Record`, which holds what an edit or a save must agree on: the id, the new, dirty and deleted marks, the WordPress or WooCommerce object, staged edits and a change counter. Records are kept under their storage and site (`post`, `user`, `term`, `comment`, `wc_order`, `wc_order_item`, `acf_row`), so `User`, `Customer` and `Account` views of user 1 share one record; views are kept by class and id. A view binds the properties its class lists in `get_record_properties()` to the record by reference, so `$this->wp_post` reads the shared object. `remember()` memoises derived values on the record, `Model::release()` frees records between units of a long run, a save reloads every other record over the rows it wrote and a clone is a fresh read. See [MODELS.md: Records and views](./MODELS.md#records-and-views).

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
- Query building via `Query_Vars` + `Query_Manager`

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
├── Layout             # Page shell (header/body/footer/modals) — uses Resolvable
├── Page_View          # Request body — uses Resolvable
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
echo new Invoice_View(['order' => 721, 'user' => 1]);
```

**Rendering:**
```php
// Echo
echo new My_View(['param' => 'value']);

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
├── Feature (Registrant)  # Optional functionality with hooks
└── Integration (Singleton)  # Plugin/service integrations
```

---

## Hook System

The framework uses a trait-based hook system for WordPress integration.

### Has_WP_Hooks Trait

```php
trait Has_WP_Hooks {
    public function add_hook(string|array $hook_name, mixed $callback, ?int $priority = null, string $type = 'filter') {
        // Registers a WordPress hook callback; a string names a method of $this, null priority is get_default_priority()
    }
}

// At switch-on, after the instance is kept, a registrant passes get_hooks() to add_hooks(), then calls run()
abstract class Feature extends Registrant {
    public function get_hooks () {
        return [
            'hook_name' => 'method_name',
            'hook_name' => ['method_name', $priority],
        ];
    }
}
```

Actions and filters share WordPress's hook registry. Prefer omitting hook type
in hook maps; include `'action'` or `'filter'` only when the extra explicitness
is useful documentation.

### Hook Naming Convention

A string is WordPress's own hook name and is used verbatim (`'acf/init'`, `'load-tools_page_x'`). An array is a framework name: its parts are joined with `.` and sanitized.

```php
$this->add_hook('acf/get_field_label', 'filter_label');            // registers acf/get_field_label
$this->add_hook(['lattice', 'my_feature', 'event'], 'on_event');   // registers lattice.my_feature.event
```

---

## Layout System

The Layout System makes Lattice the primary rendering layer for WordPress. WordPress provides content and request context; Lattice owns the application and rendering; the theme is a thin adapter.

### Rendering Flow

```
WordPress Request
    │
Theme index.php → App::render()
    │
    ├── foreach App: render_app()     # context setup
    │
    ├── Request_Resolver
    │   ├── resolve_layout() → Layout subclass (or default)
    │   └── resolve_page()   → Page_View instance
    │
    └── Layout (View)
        ├── Header
        ├── Page_View (body)
        ├── Footer
        └── Modals
```

### App Coordination

`App` extends `Factory` (not `Singleton`). All Apps share a single cache group (`protected static $cache_group = self::class`) so the base `Lattice\App` can discover all active plugin Apps.

`App::render()` is static — called from the theme. It iterates all registered Apps via `render_app()`, then resolves and renders the page:

```php
public static function render () {
    $apps = static::get_apps();
    if (!$apps) return;

    foreach ($apps as $app) {
        $app->render_app();
    }

    $resolver     = Request_Resolver::get_instance();
    $layout_class = $resolver->resolve_layout() ?? Layout::class;
    $page         = $resolver->resolve_page();
    $overrides    = $page ? $page::get_layout_overrides() : [];

    echo new $layout_class(['body' => $page] + $overrides);
}
```

### Resolvable Trait

Used by both `Layout` and `Page_View`. Declares static routing properties (`$context`, `$post_type`, `$taxonomy`, `$term`, `$priority`) and provides auto-specificity calculation.

Auto-specificity sums: context weight + 10 per set `$post_type`/`$taxonomy`/`$term`. Context weights from `$context_weights`:

| Context | Weight | Tier |
|---------|--------|------|
| `archive` | 10 | Broadest |
| `single`, `home` | 20 | Broad |
| `author`, `taxonomy`, `page`, `search` | 30 | Narrow |
| `front_page`, `404` | 40 | Most specific |

Specificity uses the best *matched* context weight — a view declaring `['home', 'archive']` that matches via `archive` gets weight 10, not 20. `$priority` defaults to `null` (auto-specificity). Setting it to any integer overrides auto-specificity entirely.

All properties accept strings or arrays: `$context = ['home', 'archive']`, `$post_type = ['post', 'page']`.

### Request_Resolver

A `Singleton`. Discovers candidates via `View::get_loaded_views()`, pre-filters by context/post_type/taxonomy/term, sorts by specificity descending, instantiates each and calls `condition()` — first truthy match wins.

- `resolve_layout()` — returns Layout subclass name or `null` (falls back to base `Layout`)
- `resolve_page()` — returns Page_View instance or `null`

`get_current_context()` returns an array of **all** matching WordPress contexts — a request can match multiple (e.g. `['front_page', 'home', 'archive']`). The `taxonomy` context covers `is_tax() || is_category() || is_tag()`. On taxonomy archives, post_type is resolved from the taxonomy's registered `object_type`.

### Layout

A `View` using `Resolvable`. Owns the page shell. Shell parts are class strings in `$defaults` — `params()` instantiates any class string param before render. Page_Views can override shell parts via `get_layout_overrides()`.

### Page_View

An abstract `View` using `Resolvable`. Owns the body content. Declares request context via static properties. File suffix: `.page-view.php`.

---

## WordPress Integration Layer

### Post Type Registration

```php
namespace Lattice;

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

Queries are built with `Query_Vars` and executed via `Query_Manager`.

```php
$qv = new Query_Vars(['post_type' => 'project', 'posts_per_page' => 10]);
$qv->post_status = 'publish';          // property overloading
$qv->add_meta_query(['key' => 'featured', 'value' => '1']);

$wp_query = $qv->make_query();         // produce a WP_Query
$posts    = Query_Manager::get_instance()->execute($wp_query);
```

> **Full documentation:** See [QUERY_SYSTEM_ANALYSIS.md](./QUERY_SYSTEM_ANALYSIS.md).

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

### Lazy Parent Resolution

The autoloader reads each file's declared class and registers a prepended `spl_autoload_register` closure over the result. Files are then walked in collection order; when a child is included before its parent, PHP asks the closure and the parent is included on demand. No inheritance graph is built and no order is computed, so the `.parent.` suffix carries no mechanism, only meaning for the reader.

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
| `true` | `new ClassName()`: an inert instance, never kept or switched on |
| `'method_name'` | `ClassName::method_name()` |
| `array` | `ClassName::declare($array)` |

### Autoload Usage

`load()` runs unconditionally on every request. Context-specific directories are autoloaded by the default `load_*` implementations (e.g. `load_admin()` autoloads `_admin/`). Override `boot_*` methods for code that should run after loading in a specific context.

```php
class My_Plugin extends App {

    // Runs on every request after load() and ensure_schema()
    public function boot_shared () {
        My_Service::get_instance();
    }

    // Runs only in wp-admin (non-ajax)
    public function boot_admin () {
        My_Admin_Feature::get_instance();
    }

    // Runs only on REST requests
    public function boot_rest () {
        My_Route::get_instance();
    }

    // Runs only on WP-CLI
    public function boot_cli () {
        My_CLI_Command::register();
    }

    // Hook database migrations
    public function ensure_schema () {
        (new \Lattice\DB\Migration_Runner(
            new \Lattice\DB\Table_Registry(),
            new \Lattice\DB\Option_Migration_Logger()
        ))->migrate_module(My_Schema::class);
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
    protected $args = [
        'order' => [
            'required' => true,
            'class'    => Order::class,  // Enable injection
        ],
    ];

    // $order is automatically injected from request param
    public function permission(WP_REST_Request $request, ?Order $order = null) {
        return User::get_instance()->can('view_order', $order->get_id());
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
| **Post** | `$post_type`, `$post_status`, `$term`, `$post_slug`, `$post_context` | `(bool)type + (bool)status*10 + (bool)term*100 + (bool)slug*1000 + (bool)context*10000` | 11111 |
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

Classes register themselves during autoload via `static_init()` (abstract classes skip it, so they are never resolution candidates):

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
         │  plugins_loaded
         ▼
┌─────────────────────────────────────┐
│  App::boot()                        │
│  ├── load()          (autoload)     │
│  ├── ensure_schema() (migrations)   │
│  └── boot_shared()                  │
└────────┬────────────────────────────┘
         │
    ┌────┴──────────────────────────────┐
    │  context branch                   │
    ▼          ▼        ▼       ▼       ▼
 [CLI]      [Cron]   [Ajax]  [REST] [Admin] [Front]
    │          │        │       │      │       │
    ▼          ▼        ▼       ▼      ▼       ▼
 boot_cli  boot_cron boot_ajax boot_rest boot_admin boot_front
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐ ┌──────────────┐
│Models │ │Features/     │
│       │ │Integrations  │
└───┬───┘ └──────┬───────┘
    │             │
    ▼             ▼
┌──────────┐  ┌─────────┐
│ WP Hooks │  │  Views  │
│(Actions/ │  │(Render) │
│ Filters) │  │         │
└──────────┘  └─────────┘

Front-end rendering (theme calls App::render()):

┌──────────────────────────────────────────┐
│ App::render()                            │
│ ├── foreach App: render_app()            │
│ ├── Request_Resolver                     │
│ │   ├── resolve_layout() → Layout class  │
│ │   └── resolve_page()   → Page_View     │
│ └── echo Layout(body: Page_View)         │
└──────────────────────────────────────────┘
```

---

## Bootstrap Process

### Framework load order (synchronous, before `plugins_loaded`)

`load.php` is short and does not list classes:

1. **Lock**: when a 0.x framework copy loaded first (it defines `DIGITALIS_FRAMEWORK_PATH`), this one stands down with an `error_log` line and an admin notice
2. **Constants** defined (`LATTICE_VERSION`, `LATTICE_PATH`, `LATTICE_LIBRARY_PATH`, `LATTICE_URI`), then `DIGITALIS_FRAMEWORK_VERSION`: a 0.x copy loaded later returns on `LATTICE_VERSION` (since the `Lattice\` rename during 0.4.0) or on `DIGITALIS_FRAMEWORK_VERSION` (before it)
3. **Bootstrap** required explicitly: `Utility`, `Hook`, the `Autoloader` trait and `Classmap`, the four things `autoload()` reaches for before a sweep can run
4. **One sweep** of `include/` with instantiation off: every declaration is read (or taken from `.classmap.php` when present and current), a lazy loader is registered, and each file is included once. The framework is a library of base classes, so nothing is constructed; singletons come up on first `get_instance()`
5. **Gated**: the nine WooCommerce files in `integrations/_woocommerce/` are required when `woocommerce_loaded` fires (WooCommerce raises it at `plugins_loaded` priority -1), or at once if it already has. Without WooCommerce they are never declared. The directory carries the `_` prefix so the sweep never reaches it: `Model::static_init()` would otherwise register `Order` and `Customer` for class resolution, and `Order::validate_id()` calls `wc_get_order()`.

### App boot lifecycle (`plugins_loaded` hook)

When your plugin calls `App::get_instance()` it schedules `boot()` on `plugins_loaded` (or boots from that `get_instance()` when the hook has already run). The boot sequence is:

```
boot()
 ├── load()              # autoload() — always runs
 ├── ensure_schema()     # DB migrations — always runs
 ├── boot_shared()       # always runs
 │
 ├── [WP_CLI]  → load_cli()  + boot_cli()   → return
 ├── [cron]    → load_cron() + boot_cron()  → return
 ├── [ajax]    → load_ajax() + boot_ajax()  (no return — may also hit REST below)
 ├── [REST]    → load_rest() + boot_rest()  → return
 ├── [admin]   → load_admin()+ boot_admin() → return
 └── [front]   →               boot_front()
```

**Context detection order matters:**
- Ajax requests do **not** return early — they fall through to the REST check, because ajax handlers may also need REST-registered functionality.
- All other contexts return after their `boot_*` call.

### Default directory conventions per context

| Method | Autoloads |
|---|---|
| `load()` | Plugin root (respects `_` and `~` prefix rules) |
| `load_admin()` | `_admin/` |
| `load_cli()` | `_cli/` |
| `load_cron()` | `_cron/` |
| `load_ajax()` | `_ajax/` |
| `load_rest()` | `_rest/` |

### Public App accessors

```php
$app = My_Plugin::get_instance();

$app->get_path(); // Absolute path to the plugin directory
$app->get_url();  // URL to the plugin directory
```

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
namespace Lattice;

class Project extends Singleton {
    protected static $slug     = 'project';
    protected static $singular = 'Project';
    protected static $plural   = 'Projects';
    protected static $icon     = 'dashicons-portfolio';
    protected static $archive  = false;
    protected static $position = 20;

    // Supports are not a property: override get_supports() (false or [] registers none)
    protected function get_supports ($supports) { return ['title', 'editor', 'thumbnail']; }

    // Taxonomies to register
    protected static $taxonomies = ['project_category'];
}
```

### Domain Models (Real Example)

```php
namespace Lattice;

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
namespace Lattice;

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
namespace Lattice;

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

        echo new Account_Projects_View([
            'projects' => $projects,
            'account'  => $account,
        ]);
    }
}
```

### Permission System

```php
namespace Lattice;

class User extends \Lattice\User {

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
namespace Lattice;

class Invoice_Route extends Route {
    protected $route = 'invoice/(?P<id>\d+)';

    public function permission(\WP_REST_Request $request): bool {
        $user = User::current();
        $order_id = $request->get_param('id');
        return $user && $user->can('view_invoice', $order_id);
    }

    public function callback(\WP_REST_Request $request): \WP_REST_Response {
        $order = Order::get_instance($request->get_param('id'));

        return new \WP_REST_Response([
            'pdf_url' => $order->get_invoice_url(),
            'order'   => $order->to_array(),
        ]);
    }
}
```

### ACF Bidirectional Relationships

```php
namespace Lattice;

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
namespace Lattice;

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
echo new Box_Link_Widget([
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
