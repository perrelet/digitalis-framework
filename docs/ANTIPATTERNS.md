# Digitalis Framework: Antipatterns

Patterns that look correct but are wrong in this framework. Each entry explains why, not just what.

---

## Bootstrap

Bootstrap-time traps — how the App class is wired to WordPress, and what breaks when that wiring is wrong.

### Bootstrap the App via `get_instance()` — not `new`

```php
// ❌ Bypasses the Factory cache — App::get_apps() returns empty, App::render() outputs nothing
new My_Plugin();

// ✅
My_Plugin::get_instance();
```

`new` creates an instance but never registers it in the Factory cache. `App::get_apps()` queries that cache, so the layout system never finds the plugin and nothing renders.

### Register hooks in `boot_*()` override points — not directly in `__construct()`

```php
// ❌ Fires at require time, before plugins_loaded — WP and other plugins aren't ready yet
public function __construct () {
    parent::__construct();
    add_action('init', [$this, 'on_init']);
}

// ✅
public function boot_shared (): void {
    add_action('init', [$this, 'on_init']);
}
```

`App::__construct()` hooks `boot()` on `plugins_loaded`. Adding WP hooks in `__construct()` fires them at require time before the WP stack is available. Use `boot_shared()`, `boot_front()`, `boot_admin()`, etc. for hook registration.

### Always call `parent::__construct()` when overriding the App constructor

```php
// ❌ $this->path / $this->url never set; boot() never hooked to plugins_loaded
public function __construct () {
    $this->custom_setup();
}

// ✅
public function __construct () {
    parent::__construct();
    $this->custom_setup();
}
```

`App::__construct()` sets `$this->reflection`, `$this->path`, and `$this->url` (required by `autoload()`) and hooks `boot()` on `plugins_loaded`. Skipping the parent call silently breaks autoloading and prevents boot from ever firing.

---

## Route

### Properties must be non-static instance properties

`Factory::get_cache_key()` reads `$this->$property` (non-static). Static properties create separate slots; `$this->route` resolves to the parent's default.

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
// ❌
protected $method  = 'POST';
protected $methods = 'POST';

// ✅
protected $definition = ['methods' => 'POST'];
```

### Override `permission()` and `callback()`, not `permission_callback()`

Framework wraps `permission_callback` — override `permission()` and `callback()` instead.

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
// ❌
protected $namespace = 'my-plugin';
protected $version   = 'v1';

// ✅
protected $namespace = 'my-plugin/v1';
```
---

## Post / User / Term — `query()`

### `query()` returns an array, not a fluent builder

```php
// ❌
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
// ❌
$count = Project::query(['posts_per_page' => -1])->found_posts;
$total = Order::query(['posts_per_page' => -1])->total;

// ✅
$count = count(Project::query(['posts_per_page' => -1]));
$posts = Project::query(['posts_per_page' => 10], $wp_query);
$found = $wp_query->found_posts;
```
---

## ACF_Block

### Properties must be non-static instance properties

Framework accesses these as instance properties.

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

Called on every registered subclass — expensive implementations multiply fast.

```php
// ❌
public static function validate_id($id) {
    $account = Account::get_instance($id);
    return $account->is_valid();
}

// ✅
public static function validate_id($id) {
    return get_post_type($id) === 'project';
}
```

### Set at least one specificity property in every subclass

Without specificity properties, subclasses tie with their parent and may never resolve.

```php
// ❌
class My_Post extends Post {}

// ✅
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

// ✅
$path = $qv->find_meta_query_path('status');
if ($path !== null) {
    $qv->get_meta_block($path)['value'] = 'active';
}
```

### Upsert is safer than find + modify for add-or-update

```php
// ❌
$qv->add_meta_query(['key' => 'status', 'value' => 'active']);

// ✅
$qv->upsert_meta_query('status', ['key' => 'status', 'value' => 'active']);
```

### `merge()` combines arrays — use `overwrite()` for unconditional replacement

```php
// ❌
$qv->set('post_status', 'publish');
$qv->merge(['post_status' => 'draft']); // Result: ['publish', 'draft']

// ✅
$qv->overwrite(['post_status' => 'draft']); // Result: 'draft'
```

### Paths from `find_*_path()` are invalidated by structural changes

```php
// ❌
$path = $qv->find_meta_query_path('status');
array_splice($qv['meta_query'], 0, 1);
$block =& $qv->get_meta_block($path);

// ✅
$qv->get_meta_block($qv->find_meta_query_path('status'))['value'] = 'updated';
```
---

## Query_Profile

### `Query_Profile` subclasses must be instantiated at boot to register

```php
// ❌
class My_Profile extends Query_Profile {}

// ✅
My_Profile::get_instance();
```

### Don't call `execute()` twice on the same `WP_Query` object

`execute()` stamps the query on first run and skips profiles on subsequent calls.

```php
// ❌
$posts = Query_Manager::get_instance()->execute($wp_query);
$posts = Query_Manager::get_instance()->execute($wp_query); // no-op

// ✅
$posts = Query_Manager::get_instance()->execute((new Query_Vars([...]))->make_query());
```

### `_profiles` / `_suppress` are ignored on the main WordPress query

`allow_profile_select` is `false` on the main query.

```php
// ❌
add_action('pre_get_posts', function ($q) {
    $q->set('_profiles', [Featured_Profile::class]);
});

// ✅
$qv = new Query_Vars(['post_type' => 'project']);
$qv->set('_profiles', [Featured_Profile::class]);
$posts = Query_Manager::get_instance()->execute($qv->make_query());
```
---

## Has_WP_Post (Post model)

### `get_type()` was removed — use `get_post_type()`

```php
// ❌
$type = $post->get_type();

// ✅
$type = $post->get_post_type();
```
---

## View

### Always call `parent::params($p)` when overriding `params()`

```php
// ❌
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
// ❌
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
// ❌
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

String values mapping to classes with `get_instance()` are resolved as DI.

```php
// ❌
protected static $defaults = [
    'model_class' => Order::class,
];

// ✅
protected static $defaults    = ['model_class' => Order::class];
protected static $skip_inject = ['model_class'];
```

### Don't render views via a dynamically resolved class name stored in a static property

```php
// ❌
static::$view::render([...]);

// ✅
My_View::render([...]);
```

### Only render markup from the render phase

Render in `view()` or lifecycle hooks, not `params()` or validate methods.

```php
// ❌
public function params (&$p) {
    ob_start();
    ?><div class="x"><?= $p['title'] ?></div><?php
    $p['content'] = ob_get_clean();
}

// ✅
public function view () {
    ?><div class="x"><?= esc_html($this['title']) ?></div><?php
}
```
---

## Query_Manager / Digitalis_Query

### `Digitalis_Query` is removed — use `Query_Vars` + `Query_Manager`

```php
// ❌
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

Generic accessors take raw string keys. Keep keys inside the model; call sites use named methods.

```php
// ❌
if ($user->get_meta('mycelium_onboarding_source') === 'self_registered') { ... }
$user->update_meta('mycelium_onboarding_source', 'invite');
$phone = $user->get_field('phone');

// ✅
class User extends \Digitalis\User {
    public function get_onboarding_source(): ?string {
        return $this->get_meta('mycelium_onboarding_source');
    }
    public function set_onboarding_source(string $source): void {
        $this->update_meta('mycelium_onboarding_source', $source);
    }
}
if ($user->get_onboarding_source() === 'self_registered') { ... }
```

**Exception:** keys internal to a single class may remain as raw calls.

---

## Has_WP_Post (Post model) — `save()` inside `wp_after_insert_post`

### Don't call `save()` with full post data inside a `wp_after_insert_post` hook

Hook fires inside `wp_update_post`; cached instances may be stale. Full `save()` can revert recent changes.

```php
// ❌
public function update_keywords () {
    $this->set_excerpt($this->generate_keywords());
    $this->save([], false);
}

// ✅
public function update_keywords () {
    $keywords = $this->generate_keywords();
    $this->wp_post->post_excerpt = $keywords;
    wp_update_post(['ID' => $this->get_id(), 'post_excerpt' => $keywords], false, false);
}
```
---

## Post / User / Term — Saving

### Use `$model->save()` — not `wp_update_post()`, `wp_update_user()`, or `wp_update_term()`

Model's `save()` wraps WP functions. Direct WP calls bypass the model layer.

```php
// ❌
wp_update_post(['ID' => $org_id, 'post_status' => 'publish']);

// ✅
$org->save(['post_status' => 'publish']);
```
---

## Post / User / Term — Querying

### Use framework `query()` methods, not bare WordPress query functions

WP functions return raw objects. Framework methods return typed model instances.

```php
// ❌
$posts = get_posts(['post_type' => 'project']);

// ✅
$posts = Project::query();
```
---

## Layout System

### Don't put shell logic in Page_View

Page_View renders body content. Shell structure belongs in Layout.

```php
// ❌
class My_Page extends Page_View {
    public function view (): void {
        echo new Header(); // wrong
    }
}

// ✅
class My_Page extends Page_View {
    public function view (): void {
        // body content only
    }
}
```

### Don't set `$context` or `$post_type` on regular Views

`Resolvable` properties only work on `Layout` and `Page_View` subclasses.

### Don't set `$priority` when auto-specificity is sufficient

Auto-specificity calculates from context weight + properties. Only set `$priority` to break ties.

```php
// ❌
class Product_Page extends Page_View {
    protected static $context   = 'single';
    protected static $post_type = 'product';
    protected static $priority  = 30;
}

// ✅
class Product_Page extends Page_View {
    protected static $context   = 'single';
    protected static $post_type = 'product';
}
```
---

## General PHP / Framework

### Prefix vendor model variables — reserve short names for framework models

Short names for framework models; vendor prefixes for WP/WC objects.

### `self::` vs `static::` for inherited static calls

```php
// ❌
$defaults = self::get_defaults();
$class    = self::class;

// ✅
$defaults = static::get_defaults();
$class    = static::class;
```
