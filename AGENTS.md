# Digitalis Framework — Agent Primer

OOP WordPress plugin framework. Auto-loads PHP files by name suffix, routes instances through a Factory/Singleton system, wraps WP entities (Post, User, Term) and WC entities (Order, Customer), and provides a View system for rendering.

**Namespace:** `Digitalis\` throughout. Framework files live in `include/`. Plugin files mirror this structure.

---

## Class Hierarchy

"Auto-instantiated" means the autoloader creates a singleton instance on load — no manual bootstrapping needed.

### Plugin Bootstrap

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `App` | `Singleton` | Base plugin class; provides autoloader entry point | yes |

### WordPress Models

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Post` | `WP_Model` | `WP_Post` wrapper | |
| `Page` | `Post` | WordPress page | |
| `Attachment` | `Post` | Media attachment | |
| `User` | `WP_Model` | `WP_User` wrapper | |
| `Term` | `WP_Model` | `WP_Term` wrapper | |
| `Comment` | `WP_Model` | `WP_Comment` wrapper | |
| `Order` | `Model` | `WC_Order` wrapper | |
| `Customer` | `User` | WooCommerce customer | |
| `Order_Item` | `Model` | WC order item | |

### Registration

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Post_Type` | `Singleton` | CPT registration | yes |
| `Taxonomy` | `Singleton` | Taxonomy registration | yes |
| `User_Taxonomy` | `Taxonomy` | Taxonomy applied to users | yes |
| `Post_Status` | `Singleton` | Custom post status | yes |
| `Order_Status` | `Post_Status` | Custom WC order status | yes |
| `User_Role` | `Singleton` | Custom user role + capabilities | yes |
| `Woo_Account_Page` | `Factory` | WC `/my-account/` endpoint | yes |

### Theme & Misc Objects

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Theme` | — | Front-end hook registrar; auto-wires `style()` → `wp_enqueue_scripts`, `theme_supports()` → `after_setup_theme`, `init()` on construct | yes |
| `Service` | `Factory` | Instantiable service/value object; auto-instantiation off by default | |
| `Options` | `Factory` | WordPress options proxy with ACF support; `Options::get()`, `add()`, `update()`; `$prefix`/`$acf_prefix` for namespacing | yes |

### Utilities

| Class | Extends | Purpose |
|-------|---------|---------|
| `Utility` | — | Abstract pure-static base; cannot be instantiated or cloned |
| `List_Utility` | `Utility` | Static key→label list with null option; `$list`, `$null_option`, `$null_key`, `get_primary_keys()` |

### Views & Rendering

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `View` | — | Renderable component; template or inline `view()` | |
| `Component` | `View` | View with typed sub-elements and attributes | |
| `Field` | `Component` | Form field base | |
| `Field_Group` | `Component` | Group of fields | |
| `Archive` | `Component` | Post/term archive with pagination | |
| `Post_Archive` | `Archive` | `WP_Query`-based archive | |
| `Term_Archive` | `Archive` | `WP_Term_Query`-based archive | |
| `Query_Filters` | `Field_Group` | Filter form for archives | |
| `Iterator_UI` | `View` | Batch processor progress/controls UI | |
| `Component\HTMX` | `Component` | HTMX element factory; maps `url`, `method`, `trigger`, `target`, `swap`, `confirm`, `push_url` etc. to `hx-*` attributes | |
| `ACF_AJAX_Form` | `View` | ACF `acf_form()` wrapper with AJAX submission support | |

### Admin

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Admin_Page` | `Factory` | WordPress admin menu page | yes |
| `Admin_Sub_Page` | `Admin_Page` | Submenu page under an admin parent | yes |
| `Meta_Box` | `Feature` | Post meta box | yes |
| `Posts_Table` | `Screen_Table` | Custom columns on posts list table | yes |
| `Users_Table` | `Screen_Table` | Custom columns on users list table | yes |
| `Terms_Table` | `Screen_Table` | Custom columns on terms list table | yes |
| `WC_Orders_Table` | `Screen_Table` | Custom columns on WC orders table | yes |

### Features & Integration

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Feature` | `Factory` | Hook registrar via `get_hooks()` | yes |
| `Integration` | `Singleton` | Conditional feature (checks plugin availability) | yes |
| `Plugin_Integration` | `Integration` | Integration requiring a specific plugin | yes |
| `ACF\Bidirectional_Relationship` | `Feature` | Keeps two ACF post-object fields in sync across post types; configure via `$key_1`, `$key_2`, `$post_type_1/2`, `$limit_1/2`, `$allow_self`, `$force_add` | yes |

### REST, Blocks & Shortcodes

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Route` | `Factory` | REST API endpoint | yes |
| `ACF_Block` | `Factory` | Gutenberg block (ACF) | yes |
| `Shortcode` | `Factory` | WordPress shortcode | yes |

### Iterators (Batch Processing)

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Iterator` | `Singleton` | Batch processor base with admin UI | yes |
| `Post_Iterator` | `Iterator` | Batch process WP posts | yes |
| `User_Iterator` | `Iterator` | Batch process WP users | yes |
| `CSV_Iterator` | `Iterator` | Batch process CSV rows | yes |
| `Order_Iterator` | `Iterator` | Batch process WC orders | yes |

### Query

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Query_Vars` | — | Fluent builder for `WP_Query` args | |
| `Query_Profile` | `Factory` | Modify queries at dispatch time | yes |
| `Query_Manager` | `Singleton` | Execute queries and apply profiles | yes |

### Database

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Schema` | — | Define custom DB tables and run migrations | yes |
| `Migration` | — | Single migration step | |
| `Table` | — | Custom DB table definition | |

### Services & Scheduling

| Class | Extends | Purpose | Auto |
|-------|---------|---------|:----:|
| `Singleton` | — | Single-instance service/manager base | yes |
| `Cron_Scheduler` | `Singleton` | WP cron job management | yes |
| `Task_Handler` | `Singleton` | One-time background task queue | yes |

---

## File Naming

`kebab-case-name.parent-identifier.php` — the parent identifier encodes the inheritance relationship for the autoloader's topological sort.

| Identifier | Framework class |
|------------|----------------|
| `post` | `Digitalis\Post` |
| `user` | `Digitalis\User` |
| `term` | `Digitalis\Term` |
| `order` | `Digitalis\Order` |
| `view` | `Digitalis\View` |
| `route` | `Digitalis\Route` |
| `feature` | `Digitalis\Feature` |
| `integration` | `Digitalis\Integration` |
| `singleton` | `Digitalis\Singleton` |
| `post-type` | `Digitalis\Post_Type` |
| `taxonomy` | `Digitalis\Taxonomy` |
| `user-taxonomy` | `Digitalis\User_Taxonomy` |
| `post-status` | `Digitalis\Post_Status` |
| `order-status` | `Digitalis\Order_Status` |
| `user-role` | `Digitalis\User_Role` |
| `woo-account-page` | `Digitalis\Woo_Account_Page` |
| `acf-block` | `Digitalis\ACF_Block` |
| `shortcode` | `Digitalis\Shortcode` |
| `iterator` | `Digitalis\Iterator` |
| `post-iterator` | `Digitalis\Post_Iterator` |
| `user-iterator` | `Digitalis\User_Iterator` |
| `csv-iterator` | `Digitalis\CSV_Iterator` |
| `order-iterator` | `Digitalis\Order_Iterator` |
| `admin-page` | `Digitalis\Admin_Page` |
| `query-profile` | `Digitalis\Query_Profile` |
| `.abstract.php` | Abstract class (no framework parent) |
| `.trait.php` | Trait |
| `.class.php` | Standalone class |

**Special directories:** `_folder/` is skipped by the autoloader. `~folder/` is conditionally loaded (plugin-dependent).

---

## Key Signatures

### Post / User / Term

```php
Post::get_instance(int $id): static
Post::get_instances(array $ids): static[]
Post::create(array $data): static
Post::query(array $args, WP_Query &$wp_query = null): static[]   // returns plain array

$post->get_id(): int
$post->get_post_type(): string
$post->get_meta(string $key): mixed
$post->set_meta(string $key, mixed $value): void
$post->update_meta(string $key, mixed $value): void
$post->get_field(string $key): mixed          // ACF
$post->update_field(string $key, mixed $value): void  // ACF
$post->save(): void
```

### View

```php
My_View::render(array $params, bool $echo = true): string
```

```php
// Inheritable static properties
protected static $defaults    = ['key' => 'default'];  // class strings are DI-resolved automatically
protected static $required    = ['key'];                // validated before render; fails silently if missing
protected static $merge       = ['classes'];            // these keys are array-merged, not overwritten
protected static $skip_inject = ['key'];                // prevent DI resolution for specific keys
protected static $template    = 'path/to/file.php';    // relative to templates/; alternative to view()

// Override points
public function params(array &$p): void  // transform/add params before render; MUST call parent::params($p)
public function condition(): bool        // return false to suppress all output
public function view(): void             // inline markup (used when no $template)
```

### Route

```php
protected $namespace  = 'my-plugin/v1';           // non-static; includes version
protected $route      = 'items/(?P<id>\d+)';      // non-static
protected $definition = ['methods' => 'POST'];    // non-static; omit entirely for GET

public function permission(\WP_REST_Request $request): bool
public function callback(\WP_REST_Request $request): mixed
```

### Feature

```php
public function get_hooks(): array {
    return [
        'init'           => 'on_init',                    // method name
        'save_post'      => ['on_save', 10, 2],           // [method, priority, accepted_args]
        'the_content'    => ['filter_content', 10, 1],
    ];
}

public function run(): void  // called immediately on instantiation (before hooks fire)
```

### Admin_Page / Admin_Sub_Page

```php
protected $slug       = 'my-page';          // URL ?page= key; also cache key
protected $title      = 'Page Title';
protected $menu_title = 'Menu Title';
protected $capability = 'manage_options';
protected $icon       = 'dashicons-marker';  // Admin_Page only
protected $position   = null;
protected $parent     = 'admin-menu-page';   // Admin_Sub_Page only — parent $slug

public function callback(): void  // override to render page content
public function get_url(): string
```

### Iterator (Batch Processing)

```php
protected $title      = 'My Iterator';
protected $key        = 'my_iterator';    // unique; used for AJAX action + option storage
protected $batch_size = 10;
protected $capability = 'administrator';
protected $cron       = false;            // set true to enable WP cron scheduling

// Override points
public function get_items(): array              // return full list of items to process
public function process_item($item): bool       // process one item; return false = failed
public function get_total_items(): int          // optional; used for progress bar
public function get_item_id($item): mixed       // optional; for deduplication tracking
public function on_start(): void                // optional; called before first batch
```

### Shortcode

```php
protected $slug = 'my-shortcode';  // [my-shortcode] tag
protected $view = My_View::class;  // View class; its $defaults become the shortcode atts
// render() is handled automatically — no override needed
```

### Schema / Migration (Namespace: `Digitalis\DB`)

```php
// Schema — one per plugin; define tables and migration sequence
protected static $slug    = 'my-plugin';   // option key prefix
protected static $version = 1;

public static function get_tables(): array      // return Table class names
public static function get_migrations(): array  // return Migration class names, in order
```

### Query_Vars + Query_Manager

```php
$qv = new Query_Vars(array $args)
$qv->set(string $key, mixed $value): static
$qv->merge(array $args): static     // smart array-combining (use for composable additions)
$qv->overwrite(array $args): static // unconditional replacement
$qv->upsert_meta_query(string $key, array $clause): static
$qv->find_meta_query_path(string $key): ?array
$qv->get_meta_block(array $path): array&  // reference — invalidated by structural changes
$qv->make_query(): WP_Query               // must call before execute()

Query_Manager::get_instance()->execute(\WP_Query $q): array
```

---

## Critical Rules

Full context and code examples for all of these are in [ANTIPATTERNS.md](./docs/ANTIPATTERNS.md).

**Route and ACF_Block properties must be non-static instance properties.**
`Factory::get_cache_key()` reads `$this->$property`. A `protected static` override creates a separate static slot that is never read.

**Route: no `$method` property; override `permission()` not `permission_callback()`.**
`$definition = ['methods' => 'POST']` is the correct way to set HTTP method. `$namespace` must include the version: `'my-plugin/v1'`.

**`query()` returns a plain `static[]` array — no fluent builder.**
`Post::query()->where_meta()` does not exist. Pass `&$wp_query` as the second argument to access `found_posts`.

**`View::$merge` does not accumulate across subclasses.**
Each child class must re-list all parent merge keys: `['classes', 'styles']`, not just `['styles']`.

**Always call `parent::params($p)` when overriding `params()`.**
Skipping it silently drops any param transformations defined in parent classes.

**`Query_Vars::merge()` combines arrays; `overwrite()` replaces unconditionally.**
`merge(['post_status' => 'draft'])` on an existing `'publish'` produces `['publish', 'draft']`.

**`Query_Profile` subclasses must be instantiated at boot to register.**
Defining the class is not enough — call `My_Profile::get_instance()` during plugin initialisation.

**Use `static::` not `self::` for inherited static calls.**
`self::` binds at definition time and breaks in subclasses.

**Keep `validate_id()` cheap — it runs on every registered subclass.**
One `get_post_type()` call is fine. Multiple queries or model instantiation are not.

---

## Commit Convention

Framework commits follow this format:

```
type: Short description with backtick `Class::method` references 🎭🎪🎠
```

**Types:** `feat`, `fix`, `docs`, `break`

**The 3 emojis** are required and must be humorous, playful, and clever — they tell a little visual story about the change, not just decorate it.

Examples:
- `fix: \`Route\` default \`$format = null\` 💾👉⚫`
- `feat: \`$skip_inject\` View var 🦘💉🖼️`
- `fix: \`View::set_param\` handle null keys 🖐🕳️🗝️`
- `feat: Store all inherited props in single cache 💾🧬🧺`
- `feat: \`Oxygen\Remove_Woo_Styles\` 🗑️🛒🎨`
- `fix: Element whitespace 🛠️💎⚪`

---

## Doc Index

| Need | File |
|------|------|
| Architecture, directory structure, design patterns | [docs/ARCHITECTURE.md](./docs/ARCHITECTURE.md) |
| File naming, load order, auto-instantiation | [docs/AUTOLOADER.md](./docs/AUTOLOADER.md) |
| Post / User / Term / Order method reference | [docs/MODELS.md](./docs/MODELS.md) |
| View system — full reference | [docs/VIEW_SYSTEM.md](./docs/VIEW_SYSTEM.md) |
| Built-in views, components, fields (30+ classes) | [docs/BUILTIN_VIEWS.md](./docs/BUILTIN_VIEWS.md) |
| Hooks, Feature, Integration | [docs/HOOKS.md](./docs/HOOKS.md) |
| Admin pages, tables, meta boxes, ACF | [docs/ADMIN.md](./docs/ADMIN.md) |
| Query_Vars, Query_Manager, DI, utilities | [docs/UTILITIES.md](./docs/UTILITIES.md) |
| Class resolution (how `get_instance()` picks subclass) | [docs/CLASS_RESOLUTION.md](./docs/CLASS_RESOLUTION.md) |
| Dependency injection internals | [docs/DEPENDENCY_INJECTION.md](./docs/DEPENDENCY_INJECTION.md) |
| Copy-paste patterns | [docs/CHEATSHEET.md](./docs/CHEATSHEET.md) |
| Real-world composite examples | [docs/EXAMPLES.md](./docs/EXAMPLES.md) |
| Things that look right but aren't | [docs/ANTIPATTERNS.md](./docs/ANTIPATTERNS.md) |
