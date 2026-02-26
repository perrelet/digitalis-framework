# Digitalis Framework: Utilities Reference

Reference for utility classes, traits, and helper patterns.

---

## Table of Contents

- [Call Utility](#call-utility)
- [List_Utility](#list_utility)
- [Log Service](#log-service)
- [Query_Vars](#query_vars)
- [Digitalis_Query](#digitalis_query)
- [Dependency Injection](#dependency-injection)
- [Is_Stashable Trait](#is_stashable-trait)
- [Autoloader Trait](#autoloader-trait)
- [Quick Reference](#quick-reference)

---

## Call Utility

Static utility for calling methods with class name filtering.

### Class Name Resolution

```php
use Digitalis\Call;

// Get class name (passes through filter)
$class = Call::get_class_name('Digitalis\Project');

// Filter allows runtime class swapping
add_filter('Digitalis/Class/Digitalis/Project', function($class, $data) {
    if ($data['context'] === 'admin') {
        return 'Digitalis\Admin_Project';
    }
    return $class;
}, 10, 2);
```

### Static Method Calls

```php
// Call static method with class name filtering
Call::static('Digitalis\Project', 'query', ['posts_per_page' => 10]);

// Equivalent to:
// Digitalis\Project::query(['posts_per_page' => 10])
// But allows the class name to be filtered first

// With array of args (for pass-by-reference)
Call::static_array('Digitalis\View', 'render', [&$params]);
```

### Use Cases

```php
// In Views - call View classes dynamically
class Dashboard extends View {
    protected static $defaults = [
        'widget_class' => Widget::class,
    ];

    public function view() {
        // Class can be overridden via params or filter
        Call::static($this['widget_class'], 'render', ['data' => $this['data']]);
    }
}

// In Meta Boxes - render View dynamically
class My_Box extends Meta_Box {
    protected $view = 'Digitalis\Stats_View';

    public function render_wrap($object, $args) {
        Call::static($this->view, 'render', $object, $args);
    }
}
```

---

## List_Utility

Base class for creating static lookup lists/enums.

### Creating a List

```php
namespace Digitalis;

class Project_Status extends List_Utility {

    public static $list = [
        'draft'     => 'Draft',
        'pending'   => 'Pending Review',
        'active'    => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public static $null_option = 'Select Status';
    public static $null_key = '';
}
```

### Using the List

```php
// Get full list
$statuses = Project_Status::get_list();
// ['draft' => 'Draft', 'pending' => 'Pending Review', ...]

// Get list with null option prepended
$statuses = Project_Status::get_list(true);
// ['' => 'Select Status', 'draft' => 'Draft', ...]

// Get list with custom null label
$statuses = Project_Status::get_list('Choose...');
// ['' => 'Choose...', 'draft' => 'Draft', ...]

// Lookup single value
$label = Project_Status::lookup('active');  // 'Active'

// Lookup multiple
$labels = Project_Status::lookup(['draft', 'active']);
// ['draft' => 'Draft', 'active' => 'Active']

// Reverse lookup (find key by label)
$key = Project_Status::reverse_lookup('Active');  // 'active'
```

### With Primary Keys

Highlight certain options at the top of the list.

```php
class Priority extends List_Utility {

    public static $list = [
        'low'      => 'Low',
        'medium'   => 'Medium',
        'high'     => 'High',
        'critical' => 'Critical',
    ];

    public static function get_primary_keys() {
        return ['high', 'critical'];  // Show these first
    }
}

// Result of get_list():
// ['high' => 'High', 'critical' => 'Critical', 'low' => 'Low', 'medium' => 'Medium']
```

### In Select Fields

```php
use Digitalis\Field\Select;

echo new Select([
    'name'    => 'status',
    'label'   => 'Status',
    'options' => Project_Status::get_list(true),
    'value'   => $current_status,
]);
```

---

## Log Service

File-based logger. Extends `Service` so instances are resolved via `get_instance($file)`.

**Location:** `framework/include/objects/log.service.php`

### Class Properties

```php
class My_Log extends Log {
    protected $file        = 'my-plugin.log';  // File name (default: 'log.log')
    protected $directory   = null;              // Directory (default: PHP error_log dir)
    protected $name        = null;              // Logger name shown in lines (default: class name)
    protected $date_format = null;              // Date format (auto per export_vars mode)
    protected $export_vars = false;             // Use var_export() format
}
```

When `$directory` is `null` the log is written to the same directory as PHP's configured `error_log`. When `$file` is empty it falls back to the same filename as PHP's `error_log`.

### Writing

```php
use Digitalis\Log;

// Via instance
$log = Log::get_instance('my-plugin.log');
$log->log('Something happened');
$log->log(['key' => 'value']);  // Non-scalars are print_r'd or var_export'd

// Raw text write (no formatting)
$log->write('raw line');

// Callable shorthand (__invoke)
$log('Quick message');

// Static shorthand
Log::w('message');
Log::w('message', 'my-plugin.log');  // Specific file
```

### Reading

```php
// Read entire file
$contents = $log->read();
$contents = Log::r('my-plugin.log');  // Static

// Read a page (tail-like, from end of file)
$page = $log->get_page(1);        // Most recent ~200 KB
$page = $log->get_page(2);        // Previous page
$page = $log->get_page(-1);       // Last page (oldest content)

// Get page and byte count
$page = $log->get_page(1, [], $bytes);
echo "Returned {$bytes} bytes";

// Custom page size
$page = $log->get_page(1, ['bpp' => 50000, 'overflow' => 200]);
```

`get_page()` reads backwards from the end of the file:

| Arg | Default | Description |
|-----|---------|-------------|
| `bpp` | `200000` | Bytes per page |
| `overflow` | `500` | Extra bytes read on each edge to find clean line boundaries |

### Export Vars Mode

```php
$log->set_export_vars(true);
$log->log(['key' => 'value']);
// Writes: $log['2026-01-01 12:00:00.000000'] = array ( 'key' => 'value', );
```

When `export_vars` is `true`:
- Messages are formatted with `var_export()`
- Lines are written as PHP variable assignments
- Date format defaults to `Y-m-d H:i:s.u` (microseconds)

### Path Resolution

| Method | Returns | Description |
|--------|---------|-------------|
| `get_file()` | `string` | File name; falls back to `basename(ini_get('error_log'))` |
| `get_directory()` | `string` | Directory; falls back to `dirname(ini_get('error_log'))` |
| `get_path()` | `string` | Full path (`directory/file`) |

The `write()` method creates the directory automatically via `wp_mkdir_p()` if it does not exist.

---

## Query_Vars

Fluent builder for WordPress query arguments with smart merging and WP quirk handling.

**Location:** `framework/include/objects/query-vars.class.php`

Implements `ArrayAccess`, `IteratorAggregate`, `JsonSerializable`, `Countable`.

### Core Purpose

Query_Vars solves several WP_Query pain points:
- **Smart merging** of query args from multiple sources
- **Array handling** for `post_type`, `post_status`, `meta_query`, `tax_query`
- **Path-based finding** to locate and modify nested meta/tax query clauses
- **ArrayAccess + property overloading** for convenient access
- **`make_query()`** to produce a ready-to-execute `WP_Query` without running it

### Basic Usage

```php
use Digitalis\Query_Vars;

$qv = new Query_Vars([
    'post_type'      => 'project',
    'posts_per_page' => 10,
]);

// Accepts a WP_Query directly
$qv = new Query_Vars($wp_query);

// Get/set/check/remove
$qv->set('orderby', 'date');
$type = $qv->get('post_type');   // 'project'
$qv->has('author');              // bool
$qv->remove('author');

// Property overloading (same as get/set/has/remove)
$qv->meta_key = 'priority';
echo $qv->post_type;
isset($qv->author);
unset($qv->author);

// ArrayAccess (same as get/set/has/remove)
$qv['meta_key'] = 'priority';
echo $qv['post_type'];

// Count and iterate
count($qv);
foreach ($qv as $key => $value) { ... }
json_encode($qv);   // JsonSerializable

// Convert to array (for manual WP_Query use)
$args = $qv->to_array();
```

The old `get_var()`, `set_var()`, `has_var()`, `unset_var()` are retained as aliases. The primary API is `get/set/has/remove`.

`meta_query` and `tax_query` are initialised as empty arrays so `add_meta_query()` / `add_tax_query()` always work without null checks.

### Meta Queries

```php
$qv = new Query_Vars(['post_type' => 'project']);

// Add meta queries
$qv->add_meta_query([
    'key'     => 'project_account',
    'value'   => $account_id,
    'compare' => '=',
]);

$qv->add_meta_query([
    'key'     => 'priority',
    'value'   => 'high',
    'compare' => '=',
]);

// Get current meta query
$current = $qv->get_meta_query();

// Result: meta_query with AND relation (WP default)
```

### Tax Queries

```php
$qv = new Query_Vars(['post_type' => 'project']);

$qv->add_tax_query([
    'taxonomy' => 'project_category',
    'field'    => 'term_id',
    'terms'    => [1, 2, 3],
]);

$qv->add_tax_query([
    'taxonomy' => 'project_tag',
    'field'    => 'slug',
    'terms'    => 'featured',
]);

// Get current tax query
$current = $qv->get_tax_query();
```

### Smart Merging (WP Quirk Handling)

`merge_var()` handles WordPress quirks for specific keys:

```php
$qv = new Query_Vars([
    'post_type'   => 'project',
    'post_status' => 'publish',
]);

// Merge additional vars
$qv->merge([
    'post_type'   => 'document',  // Becomes ['project', 'document']
    'post_status' => 'draft',     // Becomes ['publish', 'draft']
    'meta_query'  => [['key' => 'featured', 'value' => '1']],  // Appended
]);
```

**Special handling for `post_type` and `post_status`:**
```php
// 'any' takes precedence
$qv->set('post_type', 'project');
$qv->merge_var('post_type', 'any');  // Results in 'any'

// Strings converted to arrays for merging
$qv->set('post_status', 'publish');
$qv->merge_var('post_status', 'draft');  // Results in ['publish', 'draft']
```

**Overwrite vs Merge:**
```php
// Merge: combines values intelligently
$qv->merge(['post_status' => 'draft']);  // ['publish', 'draft']

// Overwrite: replaces entirely
$qv->overwrite(['post_status' => 'private']);  // 'private'
```

**Empty value handling (`$allow_empty`):**

By default `merge()` skips values that are "empty" in the framework sense — `null`, `''`, or `[]`. All other values (including `0`, `false`, `'0'`) are merged normally.

```php
// null / '' / [] are skipped by default
$qv->merge(['author' => null]);     // Skipped
$qv->merge(['author' => '']);       // Skipped
$qv->merge(['tax_query' => []]);    // Skipped

// 0, false, '0' ARE applied (they are meaningful query values)
$qv->merge(['posts_per_page' => 0]);   // Applied
$qv->merge(['ignore_sticky_posts' => false]); // Applied

// Force empty values through with $allow_empty = true
$qv->merge(['author' => null], true);  // Applied (sets author to null)
```

### Finding & Modifying Nested Queries (Path-Based)

Locate a meta or tax query clause by value, get a reference to it, and modify it in place.

```php
$qv = new Query_Vars([
    'meta_query' => [
        ['key' => 'account', 'value' => 5],
        ['key' => 'status',  'value' => 'active'],
        [
            'relation' => 'OR',
            ['key' => 'priority', 'value' => 'high'],
            ['key' => 'priority', 'value' => 'critical'],
        ],
    ],
]);

// Step 1: find the path (array of array indices into meta_query)
$path = $qv->find_meta_query_path('account');   // e.g. [0]
$path = $qv->find_meta_query_path('priority');  // finds first match, even in nested OR

// Step 2: get a reference to the clause
if ($path !== null) {
    $block =& $qv->get_meta_block($path);
    $block['value'] = 10;   // Modifies in place
}

// Tax query: same pattern
$path  = $qv->find_tax_query_path('project_category');
if ($path !== null) {
    $tax =& $qv->get_tax_block($path);
    $tax['terms'][] = 'new-slug';
}
```

`find_meta_query_path($match_value, $key = 'key', $compare = '=')` — returns an array path (e.g. `[2, 0]`) into `meta_query`, or `null` if not found.

`find_tax_query_path($match_value, $key = 'taxonomy', $compare = '=')` — same for `tax_query`.

**Available comparison operators** (for `$compare`):
- `=`, `!=`, `==`, `!==` — equality
- `<`, `<=`, `>`, `>=` — comparison
- `IN`, `!IN` — array membership (loose `==`)
- `IN=`, `!IN=` — array membership (strict `===`)

### Upsert (Add or Update)

`upsert_meta_query()` finds an existing clause and merges new values into it, or appends it if not found:

```php
// If a meta_query clause with key='account' exists, merge $new_block into it.
// Otherwise, add $new_block as a new clause.
$qv->upsert_meta_query('account', [
    'key'     => 'account',
    'value'   => $new_id,
    'compare' => '=',
]);

// Same for tax queries
$qv->upsert_tax_query('project_category', [
    'taxonomy' => 'project_category',
    'field'    => 'term_id',
    'terms'    => [5, 6],
]);
```

### Creating Queries

`make_query()` returns a `WP_Query` with the vars loaded but **not executed**. This is how `Post::query()` hands off to `Query_Manager::execute()`.

```php
$wp_query = $qv->make_query();
// $wp_query->query_vars is set, but no DB query has run yet

// Pass additional overrides
$wp_query = $qv->make_query(['posts_per_page' => 5]);
```

### Stamp

The `digitalis` query var is reserved for framework metadata. Read it as an array:

```php
$stamp = $qv->get_stamp();  // (array) $qv->get('digitalis')
```

### Real-World Usage Examples

**Admin Table Column Sorting:**
```php
// In Posts_Table
public function sort_column_account($qv) {
    $qv->set('meta_key', 'project_account');
    $qv->set('orderby', 'meta_value_num');
}

// Framework wraps WP_Query with Query_Vars
public function pre_get_posts($query) {
    $qv = new Query_Vars();
    $qv->merge($query->query_vars);
    call_user_func([$this, "sort_column_{$orderby}"], $qv);
    $query->query_vars = $qv->to_array();
}
```

**WooCommerce Order Filters:**
```php
// In WC_Orders_Table
public function query_vars($query_vars) {
    // $query_vars is already a Query_Vars instance
    $query_vars->add_meta_query([
        'key'     => '_customer_user',
        'value'   => get_current_user_id(),
        'compare' => '=',
    ]);
    return $query_vars;
}
```

**Update an existing meta query clause:**
```php
$path = $qv->find_meta_query_path('project_account');
if ($path !== null) {
    $block          =& $qv->get_meta_block($path);
    $block['value'] = $new_account_id;
}
```

---

## Digitalis_Query

> ⚠️ **Deprecated.** Use `Query_Vars::make_query()` to build the query object and `Query_Manager::execute()` to run it. The static utilities `compare_post_type()` and `is_multiple()` have moved to `Query_Vars`.

Extended WP_Query with deferred execution and fluent Query_Vars integration.

**Location:** `framework/include/objects/query.wp-query.php`

### Core Motivation

**Key difference from WP_Query:** Does NOT execute the query in the constructor.

```php
// WP_Query: Constructor immediately runs $this->query($query)
$wp_query = new WP_Query(['post_type' => 'post']);  // ← DB query happens here!

// Digitalis_Query: Constructor only stores args, no DB query
$query = new Digitalis_Query(['post_type' => 'post']);  // ← No query yet
$query->add_meta_query([...]);                          // ← Still no query
$query->merge($other_args);                             // ← Still no query
$query->query();                                        // ← NOW it runs
```

This allows:
1. Building up query args from multiple sources before execution
2. Merging queries from different contexts (e.g., main query + filters)
3. Modifying the query based on runtime conditions
4. Separating query construction from query execution

### Internal Structure

```php
class Digitalis_Query extends WP_Query {
    protected $query_vars_obj;  // Query_Vars instance

    public function __construct($query = []) {
        // Only creates Query_Vars, does NOT call parent::query()
        $this->query_vars_obj = new Query_Vars($query);
    }
}
```

### Basic Usage

```php
use Digitalis\Digitalis_Query;

$query = new Digitalis_Query([
    'post_type'      => 'project',
    'posts_per_page' => 10,
]);

// Fluent modifications before executing
$query->set_var('orderby', 'title')
      ->set_var('order', 'ASC')
      ->add_meta_query(['key' => 'featured', 'value' => '1']);

// Execute query
$query->query();

// Access results (standard WP_Query properties)
$posts = $query->posts;
$total = $query->found_posts;
$max_pages = $query->max_num_pages;
```

### Fluent Chaining

All modification methods return `$this` for chaining:

```php
$query = new Digitalis_Query(['post_type' => 'project']);

$results = $query
    ->set_var('posts_per_page', 20)
    ->set_var('post_status', 'publish')
    ->add_meta_query([
        'key'     => 'account',
        'value'   => $account_id,
        'compare' => '=',
    ])
    ->add_tax_query([
        'taxonomy' => 'project_type',
        'field'    => 'slug',
        'terms'    => 'featured',
    ])
    ->unset_var('author')
    ->query();  // Returns WP_Post[] (same as parent::query())
```

### Query Execution

```php
$query = new Digitalis_Query(['post_type' => 'project']);

// Execute with additional args (merged)
$query->query(['posts_per_page' => 5]);

// Execute with falsy merge
$query->query(['posts_per_page' => 0], true);  // 0 is applied

// Get vars without executing
$args = $query->get_query_vars();  // Returns array
$args = $query->to_array();        // Alias
```

### Accessing Query_Vars Object

```php
$query = new Digitalis_Query(['post_type' => 'project']);

// Access underlying Query_Vars for advanced operations
$qv = $query->get_query_vars_obj();

// Use Query_Vars methods directly
$meta =& $qv->find_meta_query('status');
$meta['value'] = 'active';
```

### Static Utilities

These static helpers are defined on `Query_Vars` (they were previously on `Digitalis_Query`, which is now deprecated).

#### `Query_Vars::compare_post_type($wp_query, $post_type)`

Determines whether a WP_Query refers to a specific post type. Handles edge cases:

```php
use Digitalis\Query_Vars;

if (Query_Vars::compare_post_type($wp_query, 'project')) {
    // Query is for projects
}
```

**Handles these scenarios:**

1. **Direct post_type query:**
   ```php
   $query = new WP_Query(['post_type' => 'project']);
   Digitalis_Query::compare_post_type($query, 'project');  // true
   ```

2. **Array of post types:**
   ```php
   $query = new WP_Query(['post_type' => ['project', 'document']]);
   Digitalis_Query::compare_post_type($query, 'project');  // true
   ```

3. **'any' post type:**
   ```php
   $query = new WP_Query(['post_type' => 'any']);
   Digitalis_Query::compare_post_type($query, 'project');  // true (matches any)
   ```

4. **Taxonomy archives (checks taxonomy's object_type):**
   ```php
   // Taxonomy 'project_category' is registered for 'project' post type
   // User visits /project-category/web-design/
   Digitalis_Query::compare_post_type($wp_query, 'project');  // true
   ```

5. **Default 'post' behavior:**
   ```php
   // No post_type set, but is_posts_page or is_author
   Digitalis_Query::compare_post_type($wp_query, 'post');  // true
   ```

#### `Query_Vars::is_multiple($wp_query = null)`

Determines whether a WP_Query is for plural results (archive, search, etc.):

```php
if (Query_Vars::is_multiple($wp_query)) {
    // Display archive template
}

// Uses global $wp_query if null passed
if (Query_Vars::is_multiple()) {
    // Currently on an archive page
}
```

**Returns true for:**
- `$wp_query->is_archive()`
- `$wp_query->is_search()`
- `$wp_query->is_posts_page`
- Digitalis AJAX queries (presence of `Post_Type::AJAX_Flag` query var)

### Real-World Usage Examples

**In Post Model - Building Fresh Query:**
```php
class Project extends Post {
    public static function query($args = [], $skip_main = false) {
        global $wp_query;

        // Build fresh Digitalis_Query
        $query = new Digitalis_Query();

        // Merge main query vars if on archive page
        if (!$skip_main && $wp_query && $wp_query->is_main_query()
            && Digitalis_Query::is_multiple($query)) {
            $query->merge($wp_query->query_vars);
        }

        // Set post type and status
        $query->set_var('post_type', static::$post_type ?: 'any');
        if (static::$post_status) {
            $query->set_var('post_status', static::$post_status);
        }

        // Merge user args and execute
        return $query->merge($args)->query();
    }
}
```

**In Post_Type - Main Query Filtering:**
```php
class Project_Post_Type extends Post_Type {
    protected function is_main_query($query) {
        return (
            (!is_admin() || wp_doing_ajax()) &&
            $query->is_main_query() &&
            Digitalis_Query::compare_post_type($query, $this->slug)
        );
    }

    public function main_query_wrap($query) {
        if ($this->is_main_query($query) && Digitalis_Query::is_multiple($query)) {
            $this->main_query($query);  // Apply archive modifications
        }
    }
}
```

---

## Dependency Injection

Trait for automatic model injection based on type hints.

### In Method Calls

```php
use Digitalis\Dependency_Injection;

class My_Handler {
    use Dependency_Injection;

    public function process(Project $project, User $user) {
        // $project and $user are automatically resolved
        echo $project->get_title();
        echo $user->get_name();
    }
}

$handler = new My_Handler();

// Call with IDs - models injected automatically
static::inject([$handler, 'process'], [123, 456]);

// Call with raw data
static::inject([$handler, 'process'], [$post_id, $user_id]);
```

### In Admin Tables

```php
class Projects_Table extends Posts_Table {

    // DI automatically resolves $project from post_id
    public function column_account(Project $project) {
        $account = $project->get_account();
        return $account ? $account->get_name() : '—';
    }
}
```

### Array Injection

```php
use Digitalis\Dependency_Injection;

class My_Class {
    use Dependency_Injection;

    protected static $defaults = [
        'project' => Project::class,
        'user'    => User::class,
        'title'   => '',
    ];

    public function process($params) {
        // Inject models for keys matching $defaults
        static::array_inject($params, static::$defaults);

        // $params['project'] is now a Project instance
        // $params['user'] is now a User instance
        // $params['title'] remains a string
    }
}
```

### Value Injection

```php
// Single value injection
$value = 123;
static::value_inject(Project::class, $value);
// $value is now Project::get_instance(123)
```

### Constructor Injection

```php
class Service {
    public function __construct(Project $project, User $user) {
        $this->project = $project;
        $this->user = $user;
    }
}

// Create with DI
$service = static::constructor_inject(Service::class, [$project_id, $user_id]);
```

---

## Is_Stashable Trait

Cache instances in WordPress object cache with TTL.

### Using Stashable

```php
namespace Digitalis;

class Expensive_Calculation extends Factory {

    use Is_Stashable;

    protected $result;
    protected $key;

    public function __construct($key) {
        $this->key = $key;
        $this->result = $this->calculate();
    }

    protected function get_cache_key() {
        return $this->key;
    }

    private function calculate() {
        // Expensive operation
        return heavy_calculation();
    }

    public function get_result() {
        return $this->result;
    }
}
```

### Stash Operations

```php
// Create and stash for 5 minutes (300 seconds)
$calc = new Expensive_Calculation('report_2024');
$calc->stash(300);

// Later, retrieve from stash
$calc = Expensive_Calculation::get_stash('report_2024');
if ($calc) {
    $result = $calc->get_result();
}

// Pop (get and remove from cache)
$calc = Expensive_Calculation::pop_stash('report_2024');

// Remove from stash
$calc->unstash();

// Flush all stashed instances of this class
Expensive_Calculation::flush_stash();
```

### With Models

```php
class Project extends Post {

    use Is_Stashable;

    protected function get_cache_key() {
        return $this->get_id();
    }

    public function get_expensive_data() {
        // Check stash first
        if ($stashed = static::get_stash($this->get_id())) {
            return $stashed->cached_data;
        }

        // Calculate and stash
        $this->cached_data = $this->calculate_data();
        $this->stash(600);  // 10 minutes

        return $this->cached_data;
    }
}
```

---

## Autoloader Trait

Automatic class loading and instantiation from directories.

### Basic Autoloading

```php
namespace Digitalis;

class My_Plugin extends App {

    use Autoloader;

    public function __construct() {
        $this->path = plugin_dir_path(__FILE__);
        $this->autoload();  // Load from $this->path/include
    }
}
```

### Directory Structure

```
my-plugin/
├── include/
│   ├── models/
│   │   ├── project.post.php       # Loaded, extends Post
│   │   └── account.user.php       # Loaded, extends User
│   ├── features/
│   │   └── emails.feature.php     # Loaded, instantiated
│   ├── _internal/                 # Skipped (underscore prefix)
│   │   └── helper.php
│   └── ~woocommerce/              # Conditional (tilde prefix)
│       └── orders.feature.php     # Only if WooCommerce active
```

### File Naming Conventions

| Pattern | Behavior |
|---------|----------|
| `name.php` | Loaded, no auto-instantiation |
| `name.post.php` | Loaded, extends Post |
| `name.feature.php` | Loaded, extends Feature, auto-instantiated |
| `name.abstract.php` | Loaded, not instantiated |
| `name.trait.php` | Loaded first (before classes) |
| `_folder/` | Skipped entirely |
| `~plugin-name/` | Only loaded if plugin active |

### Load Order

Files are sorted by inheritance:

1. Traits (`.trait.php`)
2. Interfaces (`.interface.php`)
3. Base classes (no parent in filename)
4. Child classes (sorted by inheritance depth)

```
# Files in directory:
special-project.project.php   # Extends Project
project.post.php              # Extends Post
base.trait.php                # Trait

# Load order:
1. base.trait.php
2. project.post.php
3. special-project.project.php
```

### Auto-Instantiation

Classes control their own instantiation:

```php
class My_Feature extends Feature {

    // Return method name to call for instantiation
    public static function get_auto_instantiation() {
        return 'get_instance';  // Calls My_Feature::get_instance()
    }
}

class My_Singleton extends Singleton {

    public static function get_auto_instantiation() {
        // Conditional instantiation
        return is_admin() ? 'get_instance' : false;
    }
}

class My_Model extends Post {

    public static function get_auto_instantiation() {
        return false;  // Never auto-instantiate
    }
}
```

### Filtering Instantiation

```php
// Prevent specific class from instantiating
add_filter('Digitalis/Instantiate/Digitalis/My_Feature', '__return_false');

// Conditional instantiation
add_filter('Digitalis/Instantiate/', function($instantiation, $class, $path) {
    // Skip all classes in a specific directory
    if (strpos($path, '/deprecated/') !== false) {
        return false;
    }
    return $instantiation;
}, 10, 3);
```

### Manual Loading

```php
class My_Plugin extends App {

    public function load() {
        // Standard autoload
        $this->autoload();

        // Load specific directory
        $this->autoload($this->path . 'custom-features');

        // Load single class
        $this->load_class($this->path . 'special/handler.php');

        // Load with specific instantiation
        $this->load_class($this->path . 'special/service.php', 'init');
    }
}
```

---

## Quick Reference

### Utility Classes

| Class | Purpose |
|-------|---------|
| `Call` | Static method calls with class filtering |
| `List_Utility` | Static lookup lists / enums |
| `Log` | File-based logger with paginated reading |
| `Query_Vars` | Fluent WP_Query args builder |
| `Digitalis_Query` | ⚠️ Deprecated — use Query_Vars + Query_Manager |

### Traits

| Trait | Purpose |
|-------|---------|
| `Dependency_Injection` | Auto-resolve model instances from type hints |
| `Is_Stashable` | Object cache with TTL |
| `Autoloader` | Directory-based class loading |
| `Has_WP_Hooks` | WordPress hooks integration |

### Call Utility

```php
Call::get_class_name($class);              // Get filtered class name
Call::static($class, $method, ...$args);   // Call static method
Call::static_array($class, $method, $args); // Call with array args
```

### List_Utility

```php
MyList::get_list();              // Full list
MyList::get_list(true);          // With null option
MyList::lookup($key);            // Get label by key
MyList::reverse_lookup($label);  // Get key by label
```

### Log

```php
Log::w($msg, $file);           // Static write
Log::r($file);                 // Static read
$log = Log::get_instance($file);
$log->log($msg);               // Write (formats line)
$log->write($text);            // Write raw text
$log->read();                  // Read entire file
$log->get_page($page, $args, $bytes); // Paginated read
$log->set_export_vars(true);   // Switch to var_export mode
```

### Query_Vars

```php
$qv = new Query_Vars($args);           // array or WP_Query
$qv->set($key, $value);                // Set variable
$qv->get($key, $default);              // Get variable
$qv->has($key);                        // Check existence
$qv->remove($key);                     // Remove variable
$qv->add_meta_query($clause);          // Append meta query
$qv->add_tax_query($clause);           // Append tax query
$qv->clear_meta_query();               // Clear all meta queries
$qv->clear_tax_query();                // Clear all tax queries
$qv->get_meta_query();                 // Get meta query array
$qv->get_tax_query();                  // Get tax query array
$path = $qv->find_meta_query_path($v); // Find clause path (or null)
$path = $qv->find_tax_query_path($v);  // Find clause path (or null)
$block =& $qv->get_meta_block($path);  // Get reference to clause
$block =& $qv->get_tax_block($path);   // Get reference to clause
$qv->upsert_meta_query($v, $block);    // Add or update meta clause
$qv->upsert_tax_query($v, $block);     // Add or update tax clause
$qv->merge($args, $allow_empty);       // Smart merge (skips null/''/[])
$qv->merge_var($key, $value);          // Merge single var
$qv->overwrite($args);                 // Replace vars
$qv->make_query($overrides);           // Build WP_Query (not executed)
$qv->get_stamp();                      // Get 'digitalis' query var
$qv->to_array();                       // Export to array

// Static utilities
Query_Vars::compare_post_type($wp_query, $slug);
Query_Vars::is_multiple($wp_query);
```

### Digitalis_Query (⚠️ Deprecated)

Migrate to `Query_Vars::make_query()` + `Query_Manager::execute()`.

```php
// Old
$query = new Digitalis_Query($args);
$query->merge($more_args)->query();

// New
$qv = new Query_Vars($args);
$qv->merge($more_args);
Query_Manager::get_instance()->execute($qv->make_query());
```

### Dependency Injection

```php
static::inject($callable, $args);           // Call with DI
static::array_inject($array, $defaults);    // Inject into array
static::value_inject($class, $value);       // Inject single value
static::constructor_inject($class, $args);  // Create with DI
```

### Is_Stashable

```php
$obj->stash($ttl);                 // Cache instance
$obj->unstash();                   // Remove from cache
MyClass::get_stash($key);          // Retrieve cached
MyClass::pop_stash($key);          // Get and remove
MyClass::flush_stash();            // Clear all cached
```

### Autoloader

```php
$this->autoload();                           // Load from $this->path
$this->autoload($path);                      // Load specific directory
$this->load_class($path);                    // Load single file
$this->load_class($path, 'method');          // Load with instantiation
```
