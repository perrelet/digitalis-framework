# Query System Analysis: Query_Vars, Query_Manager & Query_Profile

Deep technical analysis of the framework's query building and dispatch system.

---

## 1. Short Summary

The query system now consists of three collaborating classes:

- **`Query_Vars`** — A fluent builder for `WP_Query` arguments. Implements `ArrayAccess`, `IteratorAggregate`, `JsonSerializable`, and `Countable`. Supports property overloading, smart merging with WordPress quirk handling, path-based finding and upsert for nested meta/tax queries, and produces a bare `WP_Query` via `make_query()`.

- **`Query_Manager`** — A singleton dispatcher that hooks into `pre_get_posts`, `posts_clauses`, and `posts_results`. Maintains a registry of `Query_Profile` instances, applies matching profiles to every query that passes through it, and provides an `execute()` method for programmatic queries. Attaches a `digitalis` stamp to each query it processes.

- **`Query_Profile`** — A `Factory`-based class that self-registers with `Query_Manager` on construction. Each profile declares when it applies (via `$mode`, `$post_type`, `$post_status`, `$role`, `$context`, and a `condition()` method) and what it does (via `apply()` for query var changes, and SQL-level `$mods` closures for `posts_clauses`).

**`Digitalis_Query`** (the old `WP_Query` subclass) is now marked for deprecation. Use `Query_Vars::make_query()` to produce a plain `WP_Query` and `Query_Manager::get_instance()->execute()` to run it with profiles applied.

The core design shift is from a build-and-execute model tied to a single object to a **separate concerns model**: vars are built in `Query_Vars`, execution is dispatched through `Query_Manager`, and cross-cutting concerns (ordering, filtering, SQL joins) live in dedicated `Query_Profile` classes.

---

## 2. Query_Vars — API Reference

### Construction

```php
// From array
$qv = new Query_Vars(['post_type' => 'project', 'posts_per_page' => 10]);

// From an existing WP_Query (extracts query_vars)
$qv = new Query_Vars($wp_query);

// Always initialises meta_query and tax_query to [] if not provided
```

### Interfaces

`Query_Vars` implements four interfaces:

| Interface | Behaviour |
|---|---|
| `ArrayAccess` | `$qv['post_type']`, `$qv['post_type'] = 'x'`, `isset($qv['post_type'])`, `unset($qv['post_type'])` |
| `IteratorAggregate` | `foreach ($qv as $key => $value)` |
| `JsonSerializable` | `json_encode($qv)` returns the full vars array |
| `Countable` | `count($qv)` returns number of vars set |

### Property overloading

```php
$qv->post_type   = 'project';     // same as $qv->set('post_type', 'project')
$post_type = $qv->post_type;      // same as $qv->get('post_type')
isset($qv->post_type);            // same as $qv->has('post_type')
unset($qv->post_type);            // same as $qv->remove('post_type')
```

`__get` returns by reference, which means `$qv->meta_query[] = [...]` works correctly.

### Core get/set

| Method | Description |
|---|---|
| `get($key, $default = null)` | Get a var. |
| `set($key, $value)` | Set a var. Returns `$this`. |
| `has($key)` | `array_key_exists` check (catches `false`, `0`, `null`). |
| `remove($key)` | Unset a var. Returns `$this`. |
| `get_var / set_var / has_var / unset_var` | Aliases for the above. |

### Meta & tax query helpers

```php
$qv->add_meta_query(['key' => 'status', 'value' => 'active']);
$qv->add_tax_query(['taxonomy' => 'category', 'terms' => [5]]);
$qv->clear_meta_query(); // reset to []
$qv->clear_tax_query();
$qv->get_meta_query();   // returns current array
$qv->get_tax_query();
```

### Merge vs. overwrite

```php
// merge(): combines intelligently, skips empty values by default
$qv->merge(['post_type' => 'page', 'post_status' => 'draft']);

// merge() with $allow_empty = true: also writes null, '', []
$qv->merge(['posts_per_page' => 0], true);

// overwrite(): unconditional set for every key
$qv->overwrite(['post_status' => 'publish']);
```

**`should_merge()` rules** — what counts as "empty" (skipped unless `$allow_empty = true`):

| Value | Skipped? |
|---|---|
| `null` | yes |
| `''` | yes |
| `[]` | yes |
| `false`, `0`, `'0'` | **no** — always merged |
| non-empty array | no |

**`merge_var()` smart rules** for specific keys:

| Key | Behaviour when key already exists |
|---|---|
| `post_type`, `post_status` | If either side is `'any'`, result is `'any'`. Otherwise both coerced to arrays and array-unique merged. |
| `meta_query`, `tax_query` | Arrays are unique-merged. |
| other arrays | `array_values(array_unique(array_merge(...), SORT_REGULAR))` |
| scalar | incoming value wins |

### Path-based finding and upsert

The old by-reference `find_*` methods are replaced with a path-based approach that cleanly separates search from mutation.

```php
// Returns a path array (e.g. [0], [1, 'nested', 2]) or null if not found
$path = $qv->find_meta_query_path('status');           // default key = 'key'
$path = $qv->find_tax_query_path('category');          // default key = 'taxonomy'
$path = $qv->find_meta_query_path('active', 'value', '!=');

// Get a reference to a block by its path for in-place modification
$block = &$qv->get_meta_block($path);
$block['value'] = 'new_value';

// Upsert: update block if found, append if not
$qv->upsert_meta_query('status', ['key' => 'status', 'value' => 'active']);
$qv->upsert_tax_query('category', ['taxonomy' => 'category', 'terms' => [3]]);
```

### Producing a WP_Query

```php
// make_query() sets query_vars directly — no DB call yet
$wp_query = $qv->make_query();
$wp_query = $qv->make_query(['posts_per_page' => 5]); // with overrides

// Execute via Query_Manager (applies profiles)
$posts = Query_Manager::get_instance()->execute($wp_query);

// Or execute directly (bypasses profiles)
$posts = $wp_query->query($wp_query->query_vars);
```

### The stamp

`get_stamp()` returns the `digitalis` query var as an array. `Query_Manager` writes into this to track query identity, role, context, and which profiles were applied.

```php
$stamp = $qv->get_stamp(); // ['id' => '1', 'role' => 'programmatic', 'context' => 'front', ...]
```

### Static utilities

```php
// True if the wp_query is for the given post type (handles taxonomy archives, 'any', arrays)
Query_Vars::compare_post_type($wp_query, 'project');

// True if the query is a "listing" context (archive, search, posts page, or digitalis ajax)
Query_Vars::is_multiple($wp_query);
Query_Vars::is_multiple(); // null falls back to global $wp_query
```

---

## 3. Query_Manager — Architecture

`Query_Manager` is a singleton. Call `Query_Manager::get_instance()` to access it. `Query_Profile` subclasses register themselves automatically on construction.

### Lifecycle hooks

| Hook | Role |
|---|---|
| `pre_get_posts` | Intercepts the main query (front archive or `edit.php`), stamps it, and applies profiles. Registered mods are stored keyed by query ID. |
| `posts_clauses` | Applies SQL-level closures (`$mods`) registered by profiles during `apply()`. Only fires if mods were registered for this query ID. |
| `posts_results` | Cleans up stored mods after the SQL executes. |

### Context detection

Detected once at construction and stored in `$context`:

```
WP_CLI → 'cli'
cron   → 'cron'
REST   → 'rest'
ajax   → 'ajax'
admin  → 'admin'
front  → 'front'
```

### The stamp

Every query that passes through `Query_Manager` gets a `digitalis` query var stamped onto it:

```php
[
    'id'                  => '1',           // auto-incrementing per-request ID
    'role'                => 'front_main',  // front_main | admin_main | programmatic
    'context'             => 'front',       // cli | cron | rest | ajax | admin | front
    'multiple'            => true,          // is_archive || is_search || is_posts_page || ajax flag
    'selection_mode'      => 'implicit',    // implicit | explicit
    'allow_profile_select'=> true,          // whether _profiles / _suppress are honoured
    'applied'             => ['My_Profile::class', ...], // profiles that ran
]
```

### Programmatic execution

```php
// Basic — implicit profile selection
$posts = Query_Manager::get_instance()->execute($wp_query);

// With explicit role stamp (profiles can filter on this)
$posts = Query_Manager::get_instance()->execute($wp_query, ['role' => 'programmatic']);
```

`execute()` calls `apply()` internally, then runs `$wp_query->query($wp_query->query_vars)`.

### Explicit profile selection / suppression

Set query vars before calling `execute()` to control which profiles run:

```php
$wp_query->set('_profiles', [Featured_Posts_Profile::class]); // only run this profile
$wp_query->set('_suppress', [Sort_By_Priority_Profile::class]); // skip this profile
```

These vars are only honoured when `allow_profile_select` is `true` in the stamp (set automatically by `execute()`).

---

## 4. Query_Profile — Writing Profiles

`Query_Profile` extends `Factory`. Instantiating one registers it with `Query_Manager` automatically.

```php
class Featured_Posts_Profile extends Query_Profile {

    protected $mode      = 'ambient';   // baseline | ambient | selectable
    protected $priority  = 20;
    protected $post_type = 'project';   // only apply to this post type

    public function condition ($wp_query) {
        return $this->is_multiple($wp_query); // only on listings
    }

    public function apply ($query_vars, $wp_query, &$mods) {

        // Modify query vars
        $query_vars->merge([
            'meta_query' => [
                ['key' => 'featured', 'value' => '1'],
            ],
        ], true);

        // Optionally push a SQL-level closure (runs in posts_clauses)
        $mods[] = function ($clauses, $wp_query) {
            global $wpdb;
            $clauses['orderby'] = "FIELD({$wpdb->posts}.post_status, 'publish') DESC, " . $clauses['orderby'];
            return $clauses;
        };

    }

}

// Instantiate to register
Featured_Posts_Profile::get_instance();
```

### Profile modes

| Mode | When it runs |
|---|---|
| `baseline` | Always runs unless listed in `_suppress`. |
| `ambient` | Runs on implicit queries (main query, programmatic without `_profiles` set). Skipped when `_profiles` is explicitly provided. |
| `selectable` | Only runs when explicitly listed in `_profiles`. Never runs on main/ambient queries. |

### Matching conditions

Profiles pre-filter by structural properties before `condition()` is called:

| Property | Behaviour |
|---|---|
| `$post_type` | Profile only applies if the query matches one of these post types (via `compare_post_type`). Empty = any. |
| `$post_status` | Profile only applies if query post_status intersects this list. Empty = any. |
| `$role` | Match against stamp `role` (e.g. `'front_main'`, `'programmatic'`). Empty = any. |
| `$context` | Match against stamp `context` (e.g. `'front'`, `'admin'`). Empty = any. |

### Priority

Profiles are sorted descending by `$priority` before application. Higher number runs first.

---

## 5. Pros

### Clean separation of concerns
- Query vars are built in `Query_Vars` (reusable, testable, serialisable).
- Cross-cutting concerns (ordering, scoping, SQL joins) live in dedicated `Query_Profile` classes.
- Execution is owned by `Query_Manager`.

### Profile modes give precise control
- `baseline` profiles always apply (good for security scoping, active-only constraints).
- `ambient` profiles apply to all normal queries but back off when explicit control is needed.
- `selectable` profiles are opt-in per query (good for feature flags, conditional enrichments).

### Smart merging
- `merge_var()` handles WP quirks automatically: `post_type` / `post_status` array coercion, `'any'` precedence, array-unique for nested queries.
- `$allow_empty` semantics are consistent and well-reasoned: `false`/`0`/`'0'` always merge; `null`/`''`/`[]` are skipped unless explicitly allowed.

### Richer Query_Vars interface
- Property overloading (`$qv->post_type = 'x'`) reads naturally.
- `IteratorAggregate` and `JsonSerializable` mean `Query_Vars` objects work in `foreach`, `json_encode`, and spread contexts.
- `make_query()` produces a plain `WP_Query` with no DB side effect — clean handoff to `execute()` or direct `->query()`.

### Upsert replaces fragile by-reference finding
- `upsert_meta_query()` and `upsert_tax_query()` atomically add-or-update a clause, eliminating the class of bugs where a caller adds a duplicate clause instead of modifying the existing one.

### SQL-level hooks without global filters
- `$mods` closures registered in `apply()` are scoped to a specific query ID via `posts_clauses`.
- No need for global `add_filter('posts_clauses', ...)` that affect all queries.

### Query identity and observability
- The stamp gives every query a unique ID, role, context, and list of applied profiles.
- Useful for debugging: `$qv->get_stamp()` or `$wp_query->get('digitalis')`.

---

## 6. Cons and Known Limitations

### Profile registration via construction
- `Query_Profile` registers itself on `new`. The profile instance must be created before the query it should affect runs. If a profile class is autoloaded but never instantiated (no `get_instance()`), it silently doesn't apply.

### Stamp visibility leaks into query args
- The `digitalis` query var is visible in `WP_Query::query_vars`, which means it appears in `$_GET`-reflected URLs if a query is used as a redirect source. Rare but possible.

### `posts_clauses` mods are fire-once
- Mods are cleaned up in `posts_results`. If the same query object is re-run (e.g. `rewind_posts()` + a second `query()`), mods won't re-apply.

### `allow_empty` / merge semantics still require care
- `merge(['posts_per_page' => 0])` is still merged (0 is not "empty"), but `merge(['post__in' => []])` is skipped. Understanding the `should_merge()` table is necessary for predictable behaviour.

### `is_multiple()` depends on stamp for ajax detection
- `Query_Vars::is_multiple()` checks `Post_Type::AJAX_Flag` rather than the old action-prefix heuristic. If a query reaches `is_multiple()` before being stamped, the ajax check may not fire correctly.

### No down migrations
- Inherited from the DB system. Profiles themselves have no rollback. If a profile is removed, queries that relied on it silently revert to unmodified behaviour.

---

## 7. Potential Pitfalls

### Profile not instantiated
```php
// Profile exists but was never constructed — Query_Manager never sees it
class My_Profile extends Query_Profile { ... }

// Fix: ensure it's created during boot
My_Profile::get_instance();
```

### Using execute() on a query that was already applied
`Query_Manager::apply()` checks `is_applied()` and returns early if the stamp contains `applied`. Calling `execute()` twice on the same `WP_Query` object does not re-apply profiles.

### Forgetting make_query()
```php
// WRONG: setting query_vars on a new WP_Query directly then calling query()
// misses the Query_Manager apply() step
$wp_query = new WP_Query();
$wp_query->query_vars['post_type'] = 'project';
$posts = $wp_query->query($wp_query->query_vars); // profiles not applied

// CORRECT
$qv    = new Query_Vars(['post_type' => 'project']);
$posts = Query_Manager::get_instance()->execute($qv->make_query());
```

### Merge vs. overwrite confusion
```php
$qv->set('post_status', 'publish');
$qv->merge(['post_status' => 'draft']);     // → ['publish', 'draft']
$qv->overwrite(['post_status' => 'draft']); // → 'draft'
```

### Path-based reference after structure changes
```php
$path  = $qv->find_meta_query_path('status');
$qv->add_meta_query(['key' => 'other']); // appended a new block

$block = &$qv->get_meta_block($path);    // path is still valid — blocks don't shift
$block['value'] = 'updated';             // safe
```
Paths are integer-indexed into the flat `meta_query` array. They remain valid as long as earlier blocks aren't removed.

### Selectable profiles in main query
`allow_profile_select` is only set to `true` by `execute()`. The `pre_get_posts` handler for the main query does **not** set it, so `_profiles` / `_suppress` are ignored on the main query. Use `execute()` for programmatic queries where explicit selection is needed.

---

## 8. Recommended Usage Patterns

### Register profiles during boot
```php
// In App::boot_shared() or similar
Featured_Posts_Profile::get_instance();
Sort_By_Priority_Profile::get_instance();
```

### Model::query() pattern
`Post::query()` now uses `Query_Vars::make_query()` + `Query_Manager::execute()` internally. Custom model query methods should follow the same pattern:

```php
class Project extends Post {
    public static function query ($args = [], &$query = null) {
        $qv = new Query_Vars(['post_type' => 'project']);
        $qv->merge(static::get_query_vars($args), true);
        $query = $qv->make_query();
        return Query_Manager::get_instance()->execute($query, ['role' => 'programmatic']);
    }
}
```

### Upsert for idempotent query composition
```php
// Called multiple times safely — won't duplicate the clause
$qv->upsert_meta_query('status', [
    'key'     => 'status',
    'value'   => 'active',
    'compare' => '=',
]);
```

### SQL joins via profile mods
```php
public function apply ($query_vars, $wp_query, &$mods) {
    $mods[] = function ($clauses, $wp_query) {
        global $wpdb;
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = 'priority'";
        $clauses['orderby'] = "CAST(pm.meta_value AS UNSIGNED) DESC, " . $clauses['orderby'];
        return $clauses;
    };
}
```

---

## 9. Overall Reflection

The query system has matured significantly. The original `Digitalis_Query` approach — extending `WP_Query` and deferring execution — was a pragmatic fix for one problem (eager execution). The new system addresses a broader set of concerns:

- **Who applies cross-cutting query logic?** Previously: scattered `add_action('pre_get_posts', ...)` calls. Now: `Query_Profile` classes with explicit modes.
- **How does programmatic code know if it's the main query?** Previously: fragile checks scattered across models. Now: the stamp on every query communicates role and context.
- **How do SQL-level changes stay scoped?** Previously: global `posts_clauses` filters that affected all queries. Now: `$mods` closures registered per query ID.

The profile mode system (`baseline` / `ambient` / `selectable`) is the standout design decision. It cleanly handles the three most common cases without requiring the caller to know what profiles exist. A security profile that enforces active-only posts can be `baseline`. A default ordering profile can be `ambient` (backs off when the caller takes explicit control). A heavy enrichment profile can be `selectable` (only when opted in).

The tradeoff is configuration complexity. Developers need to understand modes, the stamp structure, and when `allow_profile_select` is set. The payoff is that query behaviour becomes composable and inspectable rather than emergent.

---

## 10. Next Steps / Open Items

1. **`Digitalis_Query` removal** — Now marked for deprecation. Callers should migrate to `Query_Vars::make_query()` + `Query_Manager::execute()`.

2. **Add relation methods to `Query_Vars`** — `set_meta_relation('OR')` / `set_tax_relation('OR')` still not present. Currently requires `$qv->merge(['meta_query' => ['relation' => 'OR']], true)`.

3. **Profile observability** — `Query_Manager::get_instance_map()` (from Factory) gives a list of registered profiles. A `get_profiles()` method would be a friendlier interface.

4. **Test coverage for stamp edge cases** — ajax + REST double-boot path and `allow_profile_select` on main query are subtle enough to warrant explicit tests.

5. **`_profiles` / `_suppress` on main query** — Currently `allow_profile_select` is not set for main queries. If there's a use case for selecting profiles on the main query (e.g. from a query string param), this would require an explicit opt-in.
