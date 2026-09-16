# Digitalis Framework: Antipatterns

Patterns that look correct but are wrong in this framework. Each entry explains why, not just what.

---

## Route

### Route reads a fixed contract and strict mode enforces it

`Route` reads `$namespace` (with the version: `'my-plugin/v1'`), `$route`, `$definition` (the arguments to `register_rest_route`, `['methods' => 'POST']` for a non-GET route), `$args` (its `'args'` map; override public `get_args()` when computed), `$view`, `$format`, `$handler` and `$require_nonce`. It calls `permission(WP_REST_Request $request)` and the handler, `callback()` by default; params come from `$request->get_param()`. Strict mode throws at boot for a `$method`, `$methods` or `$version` property, a `permission_callback()` method and the old `Deprecated_Route` API (`$rest_args`, `$html_prefix`, `get_params()`, `get_rest_args()`, `register_api_routes()`), and at the call for `$this->get_param()`. Redeclaring a config property as static is a PHP compile error, not a silent slot; the same holds for `ACF_Block`.

```php
// ❌ Nothing here reaches register_rest_route
protected $method = 'POST';
protected function get_params () { return ['id' => ['required' => true]]; }
public function permission_callback () { return current_user_can('edit_posts'); }

// ✅
protected $definition = ['methods' => 'POST'];
protected $args       = ['id' => ['required' => true]];
public function permission (\WP_REST_Request $request) { return current_user_can('edit_posts'); }
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

## Class Resolution

### Keep `validate_id()` cheap — no nested queries or model instantiation

Called on every registered subclass — expensive implementations multiply fast. A `validate_id()` that calls `get_instance()` on its own family recurses without end; strict throws at the re-entry.

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

### Unrelated subclasses must not validate the same id at equal specificity

At equal specificity a subclass replaces its parent for every id it validates, and a consumer model replaces the framework's default (`Lattice\Page`, `Lattice\Attachment`), so those pairs resolve deterministically. Two unrelated classes that both validate an id at the same specificity are a tie: the last registered wins, which is walk order, and strict throws naming both. Narrow one `validate_id()` or add a distinguishing static (`$post_status`, `$term`, `$post_slug` on posts).

```php
// ❌ Both validate every 'ticket' post; which one you get depends on file order
class Ticket extends Post { protected static $post_type = 'ticket'; }
class Room   extends Post { protected static $post_type = 'ticket'; }

// ✅
class Ticket extends Post {
    protected static $post_type = 'ticket';
    public static function validate_id ($id) { return parent::validate_id($id) && get_post_meta($id, 'kind', true) !== 'room'; }
}
```

---

## Query_Vars

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

### Query dispatch has three strict checks

A concrete `Query_Profile` subclass registers itself when the autoloader walks it (Factory subclasses are instantiated through `get_instance()`); at `wp_loaded` strict throws for any declared subclass that never registered (an `.abstract.`-named or `_dir/` file, a manual `require`, a `lattice.class` redirect, or a profile constructed after `wp_loaded`, the one false positive). `Query_Manager::execute()` throws when the query's stamp already carries `applied`: a second `execute()` on the same object, or `query_vars` copied from an executed query; drop the stamp after copying (`$qv->remove('digitalis')`) or build a fresh query with `make_query()`. `_profiles` / `_suppress` on the main query throw, because profile selection is disabled there: the main query takes ambient and baseline profiles only, so gate them in `condition()` and select or suppress on programmatic queries through `execute()`. `find_meta_query()` and `find_tax_query()` do not exist; the two-step path pattern is `find_meta_query_path()` then `get_meta_block($path)`, and strict says so at the call.

```php
// ❌ The copied stamp says "applied", so no profile runs and strict throws
$qv = new Query_Vars($wp_query->query_vars);
$posts = Query_Manager::get_instance()->execute($qv->make_query());

// ✅
$qv = (new Query_Vars($wp_query->query_vars))->remove('digitalis');
$posts = Query_Manager::get_instance()->execute($qv->make_query());
```
---

## Has_WP_Post (Post model)

### `get_type()` was removed — use `get_post_type()`

Strict throws at the call. Models with their own `__call` (courses `Product`, somm `Teacher` and `Subscription_Post`) shadow that and forward `get_type()` to the WooCommerce product, which is the one legitimate use.

---

## View

### Call the parent in `params()`, `__construct()` and `static_init()` overrides

Each parent does work the child depends on: `View::__construct()` sets the defaults, `Component::params()` builds the `Element` objects, `View::static_init()` registers the view in `$loaded_views` (skip it on a `Page_View` and the resolver silently stops selecting it). Strict mode throws for all three: a `params()` override with no `parent::params($p)` call and a `static_init()` skip at boot, a `__construct()` skip at first print. A `parent::params($p)` call that is present but skipped by an early return is the one case strict cannot see. Order matters for `params()`: call the parent last when it reads params you set, first when you use what it builds, and extend its parent instead if its work is unwanted.

```php
// ❌ Component::params() never runs, so the template has no element
public function params (&$p) {
    $p['total'] = $p['order']->get_total();
}

// ✅
public function params (&$p) {
    $p['total'] = $p['order']->get_total();
    parent::params($p);
}
```

### Don't guard a DI-backed required param with `instanceof`

`$required` keys whose default is a class string are gated by `pre_validate()`, which runs *before* `params()`. If the model didn't resolve, `params()` never runs. The guard is dead code, and worse, it erases the signal a guard should carry: a reader can no longer tell "this may legitimately be absent" from "I'm defending against the framework".

```php
protected static $defaults = ['product' => Product::class];
protected static $required = ['product'];

// ❌ pre_validate() already guaranteed this
public function params (&$p) {
    if ($p['product'] instanceof Product) {
        $p['title'] = $p['product']->get_title();
    }
    parent::params($p);
}

// ✅
public function params (&$p) {
    $p['title'] = $p['product']->get_title();
    parent::params($p);
}
```

Keep the guard only when it narrows to a **subtype** of the declared default (`default => Product::class`, guard `instanceof Ion_Source`) — that is a real type test, not a framework defence.

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

Strict mode throws when the prepare phase (`pre_validate()`, `params()`, `validate()`) emits output or leaves a buffer open. The balanced `ob_start()` … `ob_get_clean()` above is invisible to it, which is why this entry stays.
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

Page_View renders body content. Shell structure belongs in Layout. Strict throws when `Header`, `Footer`, `Modals` or a subclass renders while a `Page_View` is on the render stack; a page that needs its own shell part declares `protected static $layout = ['header' => My_Header::class]`. A project shell part that extends `Component` instead is outside the check until it sets `protected static $shell = true`.

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
