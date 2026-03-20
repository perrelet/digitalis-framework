# Digitalis Framework: Antipatterns

Patterns that look correct but are wrong in this framework. Each entry explains why, not just what.

---

## Route

### Properties must be non-static instance properties

`Factory::get_cache_key()` reads `$this->$property` (non-static). A `protected static` override in a subclass creates a separate static slot — `$this->route` still resolves to the parent's default `'route'`.

```php
// ❌
protected static $route     = 'projects';
protected static $namespace = 'my-plugin/v1';

// ✅
protected $route     = 'projects';
protected $namespace = 'my-plugin/v1';
```

### No `$method` or `$methods` property

```php
// ❌ These properties don't exist on Route
protected $method  = 'POST';
protected $methods = 'POST';

// ✅ Pass WP REST API route args via $definition. GET is the default — omit for GET-only routes.
protected $definition = ['methods' => 'POST'];
```

### Override `permission()` and `callback()`, not `permission_callback()`

`permission_callback` is a WP REST API internal concept. The framework wraps it — the override points are `permission()` and `callback()`, both receiving `WP_REST_Request`.

```php
// ❌
public function permission_callback(): bool { ... }
public function callback(): \WP_REST_Response { ... }

// ✅
public function permission(\WP_REST_Request $request): bool { ... }
public function callback(\WP_REST_Request $request): \WP_REST_Response { ... }
```

### Access params from `$request`, not `$this`

```php
// ❌
public function permission(\WP_REST_Request $request): bool {
    return current_user_can('edit_post', $this->get_param('id'));
}

// ✅
public function permission(\WP_REST_Request $request): bool {
    return current_user_can('edit_post', $request->get_param('id'));
}
```

### `$namespace` includes the version — no separate `$version` property

```php
// ❌ $version does not exist on Route
protected $namespace = 'my-plugin';
protected $version   = 'v1';

// ✅
protected $namespace = 'my-plugin/v1';
```

---

## Post / User / Term — `query()`

### `query()` returns an array, not a fluent builder

```php
// ❌ None of these methods exist
Post::query()->where_meta('status', 'active')->get();
Post::query()->where_tax('category', 5)->limit(10)->get();
User::query()->where_role('customer')->get();

// ✅
Post::query(['meta_query' => [['key' => 'status', 'value' => 'active']], 'posts_per_page' => 10]);
Post::query(['tax_query' => [['taxonomy' => 'category', 'terms' => [5]]]]);
User::query(['role' => 'customer']);
```

### The return value is an array — WP_Query properties don't apply to it

```php
// ❌ query() returns Post[], not a WP_Query object
$count = Project::query(['posts_per_page' => -1])->found_posts;
$total = Order::query(['posts_per_page' => -1])->total;

// ✅ Use count() for total of returned results
$count = count(Project::query(['posts_per_page' => -1]));

// ✅ Or pass &$query to get the underlying WP_Query (e.g. for found_posts with LIMIT)
$posts = Project::query(['posts_per_page' => 10], $wp_query);
$found = $wp_query->found_posts;
```

---

## ACF_Block

### Properties must be non-static instance properties

Same reason as Route — the framework accesses these as instance properties.

```php
// ❌
protected static $slug     = 'testimonial';
protected static $view     = Testimonial_View::class;
protected static $block    = ['title' => 'Testimonial', 'icon' => 'format-quote'];
protected static $defaults = ['quote' => '', 'author' => ''];

// ✅
protected $slug     = 'testimonial';
protected $view     = Testimonial_View::class;
protected $block    = ['title' => 'Testimonial', 'icon' => 'format-quote'];
protected $defaults = ['quote' => '', 'author' => ''];
```

---

## Class Resolution

### Keep `validate_id()` cheap — no nested queries or model instantiation

Resolution calls `validate_id()` on every registered subclass. Expensive implementations multiply fast.

```php
// ❌ Multiple queries per call, run for every subclass
public static function validate_id($id) {
    $account = Account::get_instance($id); // ← also triggers resolution chain
    return $account->is_valid();
}

// ✅ One direct WP function only
public static function validate_id($id) {
    return get_post_type($id) === 'project';
}
```

### Set at least one specificity property in every subclass

Without a specificity property, the subclass has the same score as its parent and may never be resolved.

```php
// ❌ Same specificity as Post — resolution order determines winner
class My_Post extends Post {}

// ✅ At least one property raises specificity above the parent
class My_Post extends Post {
    protected static $post_type = 'my_post';
}
```

---

## Query_Vars

### `find_meta_query()` does not exist — use the two-step path pattern

```php
// ❌
$meta =& $qv->find_meta_query('status');
$meta['value'] = 'active';

// ✅ find_meta_query_path() returns a path (or null); get_meta_block() returns a reference
$path = $qv->find_meta_query_path('status');
if ($path !== null) {
    $meta          =& $qv->get_meta_block($path);
    $meta['value'] = 'active';
}
```

### Upsert is safer than find + modify for add-or-update

```php
// ❌ Adds a duplicate clause if 'status' already exists
$qv->add_meta_query(['key' => 'status', 'value' => 'active']);

// ✅ Updates existing clause if found, appends if not
$qv->upsert_meta_query('status', ['key' => 'status', 'value' => 'active']);
```

### `merge()` combines arrays — use `overwrite()` for unconditional replacement

```php
// ❌ merge() on a scalar-keyed value appends, not replaces
$qv->set('post_status', 'publish');
$qv->merge(['post_status' => 'draft']); // Result: ['publish', 'draft']

// ✅
$qv->overwrite(['post_status' => 'draft']); // Result: 'draft'
```

### Paths from `find_*_path()` are invalidated by structural changes

```php
// ❌ Removing an earlier block shifts all indexes — path now points to wrong block
$path = $qv->find_meta_query_path('status');
array_splice($qv['meta_query'], 0, 1);
$block =& $qv->get_meta_block($path); // Wrong block

// ✅ Get a fresh path after any structural change
$path  = $qv->find_meta_query_path('status');
$block =& $qv->get_meta_block($path);
$block['value'] = 'updated';
```

---

## Query_Profile

### `Query_Profile` subclasses must be instantiated at boot to register

```php
// ❌ Class defined but never instantiated — profile silently never runs
class My_Profile extends Query_Profile {}

// ✅ Call get_instance() during plugin boot
My_Profile::get_instance();
```

### Don't call `execute()` twice on the same `WP_Query` object

`execute()` stamps the query on first run and skips profiles on subsequent calls.

```php
// ❌ Second call is a no-op
$posts = Query_Manager::get_instance()->execute($wp_query);
$posts = Query_Manager::get_instance()->execute($wp_query); // profiles not re-applied

// ✅ Build a fresh Query_Vars for each execution
$posts = Query_Manager::get_instance()->execute((new Query_Vars([...]))->make_query());
```

### `_profiles` / `_suppress` are ignored on the main WordPress query

`allow_profile_select` is `false` on the main query — use `execute()` for programmatic queries instead.

```php
// ❌ Silently ignored
add_action('pre_get_posts', function ($q) {
    $q->set('_profiles', [Featured_Profile::class]);
});

// ✅ Use execute() where profile selection is needed
$qv = new Query_Vars(['post_type' => 'project']);
$qv->set('_profiles', [Featured_Profile::class]);
$posts = Query_Manager::get_instance()->execute($qv->make_query());
```

---

## Has_WP_Post (Post model)

### `get_type()` was removed — use `get_post_type()`

```php
// ❌ Method no longer exists (removed in commit daaddfe)
$type = $post->get_type();

// ✅
$type = $post->get_post_type();
```

---

## View

### Always call `parent::params($p)` when overriding `params()`

```php
// ❌ Breaks any param transformations defined in parent classes
public function params(&$p) {
    $p['total'] = $p['order']->get_total();
}

// ✅
public function params(&$p) {
    $p['total'] = $p['order']->get_total();
    parent::params($p);
}
```

### Always call `parent::__construct()` when overriding the constructor

```php
// ❌ Default parameter initialization never runs
public function __construct($params = []) {
    $this->custom_setup();
}

// ✅
public function __construct($params = []) {
    parent::__construct($params);
    $this->custom_setup();
}
```

### Child views must re-list parent merge keys — they don't accumulate

```php
// ❌ Child's $merge replaces parent's — 'classes' is lost
class Parent_View extends View {
    protected static $merge = ['classes'];
}
class Child_View extends Parent_View {
    protected static $merge = ['styles'];
}

// ✅
class Child_View extends Parent_View {
    protected static $merge = ['classes', 'styles'];
}
```

### Class-name defaults are injected — add to `$skip_inject` to prevent it

Any string value in `$defaults` that maps to a class with `get_instance()` will be resolved as DI.

```php
// ❌ Framework calls Order::get_instance($value) on this param
protected static $defaults = [
    'model_class' => Order::class,
];

// ✅
protected static $defaults    = ['model_class' => Order::class];
protected static $skip_inject = ['model_class'];
```

### Don't render views via a dynamically resolved class name stored in a static property

```php
// ❌ static::$view resolves to the property value (a string), but chaining ::render()
//    off it is fragile and doesn't work through the static resolution chain as expected
static::$view::render([...]);

// ✅ Use the concrete class name directly
My_View::render([...]);

// ✅ Or if you need dynamic dispatch, use a local variable
$view_class = $this->view;
$view_class::render([...]);
```

---

## Query_Manager / Digitalis_Query

### `Digitalis_Query` is removed — use `Query_Vars` + `Query_Manager`

```php
// ❌ Digitalis_Query is gone
$query = new Digitalis_Query(['post_type' => 'project']);
$query->merge($args)->query();

// ✅
$qv = new \Digitalis\Query_Vars(['post_type' => 'project']);
$qv->merge($args);
$posts = \Digitalis\Query_Manager::get_instance()->execute($qv->make_query());
```

---

## Post / User / Term — Model Methods

### Wrap named data access in dedicated model methods

The framework provides generic low-level accessors — `get_meta()`, `update_meta()`, `get_field()`, `update_field()` — that take raw string keys. These are implementation details and should stay inside the model. Call sites should work with named methods, not key strings.

This applies to **any** raw-key accessor: WP meta, ACF fields, options, transients, etc. — including bare WordPress functions (`get_user_meta()`, `get_post_meta()`, `update_user_meta()`, etc.), which are subject to the same rule.

The threshold is: if you'd grep for the key string tomorrow, it belongs in a method.

```php
// ❌ Raw key strings scattered across features, routes, and post-types
if ($user->get_meta('mycelium_onboarding_source') === 'self_registered') { ... }
$user->update_meta('mycelium_onboarding_source', 'invite');

$phone = $user->get_field('phone');
$org->update_field('location', $value);

// ✅ Key strings live once, in the model — call sites are readable and key-string-free
class User extends \Digitalis\User {

    public function get_onboarding_source(): ?string {
        return $this->get_meta('mycelium_onboarding_source');
    }

    public function set_onboarding_source(string $source): void {
        $this->update_meta('mycelium_onboarding_source', $source);
    }

    public function get_phone(): ?string {
        return $this->get_field('phone');
    }

}

// Call sites
if ($user->get_onboarding_source() === 'self_registered') { ... }
$user->set_onboarding_source('invite');
$phone = $user->get_phone();
```

**Exception:** keys that are internal implementation details of a single class — written and consumed entirely within that class as part of one flow (e.g. a short-lived token stored and verified inside `Email_Confirmation`) — may remain as raw calls. The test is: does the key represent a named concept on the model that other classes care about? If yes, wrap it. If it's private plumbing that never leaves the class, raw calls are fine.

---

## Post / User / Term — Querying

### Use framework `query()` methods, not bare WordPress query functions

`get_posts()`, `get_users()`, `get_terms()`, `WP_Query`, `WP_User_Query`, and `WP_Term_Query` return raw WordPress objects. Framework query methods return typed model instances, respect class resolution, and keep query logic consistent.

```php
// ❌ Returns WP_Post[] — bypasses model resolution and class hierarchy
$posts = get_posts(['post_type' => 'project', 'posts_per_page' => 10]);
$users = get_users(['role' => 'subscriber']);
$terms = get_terms(['taxonomy' => 'category']);

// ✅ Returns typed model instances
$posts = Project::query(['posts_per_page' => 10]);
$users = User::query(['role' => 'subscriber']);
$terms = Category::query();
```

Valid exceptions: low-level utility code where raw IDs or WP objects are explicitly needed, or framework internals where model instantiation would be circular.

---

## General PHP / Framework

### Prefix vendor model variables — reserve short names for framework models

Short variable names (`$user`, `$product`, `$order`) are reserved for framework model instances (`Mycelium\User`, `Digitalis\Order`, etc.). Variables holding vendor/WordPress/WooCommerce objects must carry a vendor prefix so the type is unambiguous at a glance.

| Variable | Type |
|----------|------|
| `$wp_user` | `WP_User` |
| `$wp_post` | `WP_Post` |
| `$wp_term` | `WP_Term` |
| `$wc_product` | `WC_Product` |
| `$wc_order` | `WC_Order` |
| `$user` | `Mycelium\User` / `Digitalis\User` |
| `$post` | framework `Post` subclass |

```php
// ❌ $user suggests a framework model; $mycelium_user is noise in the other direction
$user          = get_userdata($id);   // WP_User
$mycelium_user = User::get_instance($id);

// ✅
$wp_user = get_userdata($id);         // WP_User — vendor prefix makes type clear
$user    = User::get_instance($id);   // framework model gets the short name
```

---

### `self::` vs `static::` for inherited static calls

```php
// ❌ self:: binds at definition time — breaks in subclasses
$defaults = self::get_defaults();
$class    = self::class;

// ✅ static:: uses late static binding
$defaults = static::get_defaults();
$class    = static::class;
```
