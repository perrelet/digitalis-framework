# Digitalis Framework: Utilities Reference

Reference for utility classes, traits, and helper patterns.

---

## Table of Contents

- [Call Utility](#call-utility)
- [List_Utility](#list_utility)
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

## Query_Vars

Fluent builder for WordPress query arguments with smart merging and WP quirk handling.

**Location:** `framework/include/objects/query-vars.class.php`

### Core Purpose

Query_Vars solves several WP_Query pain points:
- **Smart merging** of query args from multiple sources
- **Array handling** for `post_type`, `post_status`, `meta_query`, `tax_query`
- **Reference-based finding** to modify nested meta/tax queries in place
- **ArrayAccess** for convenient property-style access

### Basic Usage

```php
use Digitalis\Query_Vars;

$qv = new Query_Vars([
    'post_type'      => 'project',
    'posts_per_page' => 10,
]);

// Get/set variables
$qv->set('orderby', 'date');
$qv->set('order', 'DESC');
$type = $qv->get('post_type');  // 'project'

// ArrayAccess
$qv['meta_key'] = 'priority';
echo $qv['post_type'];

// Check existence
if ($qv->has_var('author')) { ... }

// Remove variable
$qv->unset('author');

// Convert to array for WP_Query
$args = $qv->to_array();
$query = new WP_Query($args);
```

### Constructor Defaults

Query_Vars initializes with empty arrays for meta_query and tax_query:

```php
// Internally:
$this->query = wp_parse_args($query_vars, [
    'meta_query' => [],
    'tax_query'  => [],
]);
```

This ensures `add_meta_query()` and `add_tax_query()` always work without null checks.

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

**Falsy value handling:**
```php
// By default, falsy values are skipped
$qv->merge(['posts_per_page' => 0]);  // Ignored!

// Use $merge_falsy = true to include falsy values
$qv->merge(['posts_per_page' => 0], true);  // Applied
```

### Finding & Modifying Nested Queries (By Reference)

Find meta/tax query clauses and modify them in place:

```php
$qv = new Query_Vars([
    'meta_query' => [
        ['key' => 'account', 'value' => 5],
        ['key' => 'status', 'value' => 'active'],
        [
            'relation' => 'OR',
            ['key' => 'priority', 'value' => 'high'],
            ['key' => 'priority', 'value' => 'critical'],
        ],
    ],
]);

// Find meta query by key (returns reference)
$meta =& $qv->find_meta_query('account');
$meta['value'] = 10;  // Modifies in place!

// Find with comparison operators
$meta =& $qv->find_meta_query('priority', 'IN');  // Find where key IN array
$meta =& $qv->find_meta_query('project_%', 'LIKE');  // Pattern matching

// Find tax query by taxonomy
$tax =& $qv->find_tax_query('category');
$tax['terms'][] = 'new-term';

// Recursive search - finds nested queries too
$nested =& $qv->find_meta_query('priority');  // Finds in nested OR group
```

**Available comparison operators:**
- `=`, `!=`, `==`, `!==` - Equality
- `<`, `<=`, `>`, `>=` - Comparison
- `IN`, `!IN` - Array membership (loose)
- `IN=`, `!IN=` - Array membership (strict)

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

---

## Digitalis_Query

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

#### `compare_post_type($wp_query, $post_type)`

Determines whether a WP_Query refers to a specific post type. Handles edge cases:

```php
use Digitalis\Digitalis_Query;

// Basic usage
if (Digitalis_Query::compare_post_type($wp_query, 'project')) {
    // Query is for projects
}

// Instance method shortcut
$query = new Digitalis_Query(['post_type' => 'project']);
$query->is_post_type('project');  // true
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

#### `is_multiple($wp_query)`

Determines whether a WP_Query is for plural results (archive, search, etc.):

```php
// Check if archive/search/multiple posts page
if (Digitalis_Query::is_multiple($wp_query)) {
    // Display archive template
}

// Uses global $wp_query if null passed
if (Digitalis_Query::is_multiple()) {
    // Currently on an archive page
}
```

**Returns true for:**
- `$wp_query->is_archive()`
- `$wp_query->is_search()`
- `$wp_query->is_posts_page`
- AJAX requests where action starts with 'query'

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
| `Query_Vars` | Fluent WP_Query args builder |
| `Digitalis_Query` | Extended WP_Query with Query_Vars |

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

### Query_Vars

```php
$qv = new Query_Vars($args);
$qv->set($key, $value);                  // Set variable
$qv->get($key, $default);                // Get variable
$qv->has_var($key);                      // Check existence
$qv->unset($key);                        // Remove variable
$qv->add_meta_query($clause);            // Append meta query
$qv->add_tax_query($clause);             // Append tax query
$qv->get_meta_query();                   // Get meta query array
$qv->get_tax_query();                    // Get tax query array
$meta =& $qv->find_meta_query($key);     // Find by reference
$tax =& $qv->find_tax_query($taxonomy);  // Find by reference
$qv->merge($args, $merge_falsy);         // Smart merge
$qv->merge_var($key, $value);            // Merge single var
$qv->overwrite($args);                   // Replace vars
$qv->to_array();                         // Export to array
```

### Digitalis_Query

```php
$query = new Digitalis_Query($args);           // No execution yet!
$query->set_var($key, $value);                 // Set variable
$query->get_var($key, $default);               // Get variable
$query->unset_var($key);                       // Remove variable
$query->add_meta_query($clause);               // Append meta query
$query->add_tax_query($clause);                // Append tax query
$query->merge($args, $merge_falsy);            // Smart merge
$query->overwrite($args);                      // Replace vars
$query->get_query_vars();                      // Get args array
$query->get_query_vars_obj();                  // Get Query_Vars instance
$query->query($args, $merge_falsy);            // Execute (returns posts)
$query->is_post_type($slug);                   // Check post type

// Static utilities
Digitalis_Query::compare_post_type($wp_query, $slug);  // Check query post type
Digitalis_Query::is_multiple($wp_query);               // Check if archive/search
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
