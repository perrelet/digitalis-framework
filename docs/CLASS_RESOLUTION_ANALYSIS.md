# Model Class Resolution: Comprehensive Analysis

A detailed technical analysis of the Digitalis Framework's automatic model class resolution system.

---

## 1. Short Summary

The Digitalis class resolution system is a **specificity-based polymorphic factory pattern** that automatically determines the most appropriate subclass when instantiating models. Instead of returning generic `Post`, `User`, or `Term` instances, the system evaluates all registered subclasses and returns the most specific one that validates for a given ID.

Key characteristics:
- **Automatic** - No explicit type checking or manual class selection
- **Specificity-driven** - Weighted scoring determines which class wins
- **Validation-based** - Each class defines its own validation logic
- **Hierarchical** - Respects and leverages class inheritance
- **WordPress-native** - Built around WordPress entity patterns

The system transforms this:
```php
$post = get_post(123);
if ($post->post_type === 'project') {
    $project = new Project($post->ID);
} elseif ($post->post_type === 'document') {
    $document = new Document($post->ID);
}
// ...handle each type manually
```

Into this:
```php
$model = Post::get_instance(123);  // Returns Project, Document, or Post automatically
```

---

## 2. Pros

### Eliminates Type-Checking Boilerplate
Every method that retrieves a model no longer requires manual type detection and class instantiation. The factory method handles this automatically.

```php
// Before: Manual resolution
function process_post($post_id) {
    $post_type = get_post_type($post_id);
    if ($post_type === 'project') {
        return new Project($post_id);
    } elseif ($post_type === 'document') {
        return new Document($post_id);
    }
    return new Post($post_id);
}

// After: Automatic resolution
function process_post($post_id) {
    return Post::get_instance($post_id);  // Correct type automatically
}
```

### Enables True Polymorphism
Code can work with base classes while receiving specialized instances:

```php
// Works with any post type
function display_title(Post $post) {
    echo $post->get_title();

    // If it's a Project, we can still access Project methods
    if (method_exists($post, 'get_account')) {
        echo " - " . $post->get_account()->get_name();
    }
}
```

### Self-Registering Architecture
New model classes automatically participate in resolution without modifying existing code. Just extend the appropriate parent and define the specificity properties.

```php
// Adding a new model is simple
class Invoice extends Post {
    protected static $post_type = 'invoice';
    // Automatically registered during autoload
    // Post::get_instance() now resolves invoices correctly
}
```

### Consistent Instance Caching
Resolution happens once per ID, then the instance is cached. Subsequent calls return the cached instance regardless of which class was used to request it.

```php
$a = Post::get_instance(123);      // Resolves to Project, cached
$b = Project::get_instance(123);   // Cache hit, same instance
$c = Post::get_instance(123);      // Cache hit, same instance
assert($a === $b && $b === $c);    // true
```

### Supports Complex Specificity Hierarchies
The weighted specificity system allows for sophisticated class hierarchies:

```php
class Project extends Post { }                    // Specificity: 1
class Draft_Project extends Project { }           // Specificity: 11
class Featured_Project extends Project { }        // Specificity: 101
class Featured_Draft_Project extends Project { }  // Specificity: 111
```

### Integration with Dependency Injection
Works seamlessly with the framework's DI system - injected models are automatically resolved to their most specific type.

### Clean API Design
The public API remains simple and intuitive while hiding complex resolution logic internally.

---

## 3. Cons

### Hidden Control Flow
Resolution happens invisibly, which can confuse developers unfamiliar with the system:

```php
$model = Post::get_instance(123);
// What type is $model? Can't tell from this line alone
```

### Performance Overhead
Resolution requires:
- Iterating over all registered subclasses
- Calling `validate_id()` on each candidate
- Multiple WordPress database queries (get_post_type, has_term, etc.)

### Validation Query Cost
Each `validate_id()` call may trigger WordPress queries:

```php
// Post validation can call:
get_post_type($id);           // Database query
has_term($term, $taxonomy);   // Database query
get_post_status($id);         // Database query
```

### No Compile-Time Type Safety
PHP's type system can't guarantee the resolved type:

```php
function process(Project $project) { }

$model = Post::get_instance(123);  // Might not be Project
process($model);  // Runtime error if not Project
```

### Class Map Memory Usage
All registered classes are stored in memory:

```php
Model::$class_map = [
    'Post' => [/* all Post subclasses */],
    'User' => [/* all User subclasses */],
    // etc.
];
```

### Debugging Complexity
Stack traces through resolution logic are harder to follow. When things go wrong, tracking why a particular class was chosen requires understanding the entire resolution algorithm.

---

## 4. Potential Pitfalls

### Expensive validate_id() Implementations

```php
// SLOW - Multiple queries per validation
public static function validate_id($id) {
    $post = get_post($id);
    $account = get_field('project_account', $id);
    $terms = wp_get_post_terms($id, 'project_type');
    // ... more queries
}

// Resolution iterates ALL subclasses
// 10 subclasses = 10+ validate_id() calls = 30+ queries
```

**Solution:** Keep `validate_id()` minimal, using only essential checks.

### Ambiguous Specificity

```php
class Project extends Post {
    protected static $post_type = 'project';  // Specificity: 1
}

class Document extends Post {
    protected static $post_type = 'document';  // Specificity: 1
}

// If a post somehow matches both (shouldn't happen, but...),
// which one wins depends on iteration order
```

### Circular Validation Dependencies

```php
// DANGEROUS - causes infinite recursion
public static function validate_id($id) {
    // This calls get_instance() which calls validate_id()...
    $account = Account::get_instance($id);
    return $account->is_valid();
}
```

**Solution:** Use direct WordPress functions in `validate_id()`.

### Forgetting to Set Properties

```php
class My_Post extends Post {
    // Forgot to set $post_type
    // Specificity: 0
    // Will never be resolved (same as base class)
}
```

### Multiple Statuses Gotcha

```php
class Draft_Or_Pending extends Post {
    protected static $post_status = ['draft', 'pending'];
    // This works, but...
}

class Just_Draft extends Post {
    protected static $post_status = 'draft';
    // Same specificity (11) as Draft_Or_Pending
    // Resolution order determines winner
}
```

### Inherited Property Collision

```php
class Project extends Post {
    protected static $post_type = 'project';
}

class Special_Project extends Project {
    // Inherits $post_type = 'project'
    // But forgot to add additional specificity
    // Same specificity as Project - won't resolve correctly
}
```

### Query Builder Resolution Overhead

```php
// Each result gets resolved individually
$posts = Post::query()
    ->where_in('post_type', ['project', 'document', 'invoice'])
    ->limit(100)
    ->get();
// 100 posts × resolution overhead = slow
```

---

## 5. Recommended Tweaks or Adjustments

### Add Validation Caching

```php
protected static $validation_cache = [];

public static function validate_id($id) {
    $key = static::class . ':' . $id;

    if (!isset(self::$validation_cache[$key])) {
        self::$validation_cache[$key] = static::perform_validation($id);
    }

    return self::$validation_cache[$key];
}
```

### Add Resolution Caching

```php
protected static $resolution_cache = [];

public static function get_class_name($id, $auto_resolve = null) {
    $key = static::class . ':' . $id;

    if (isset(self::$resolution_cache[$key])) {
        return self::$resolution_cache[$key];
    }

    // ... existing resolution logic ...

    self::$resolution_cache[$key] = $class_name;
    return $class_name;
}
```

### Add Debug Logging

```php
public static function get_class_name($id, $auto_resolve = null) {
    if (defined('DIGITALIS_DEBUG_RESOLUTION') && DIGITALIS_DEBUG_RESOLUTION) {
        error_log(sprintf(
            "Resolution: %s::get_class_name(%d) - checking %d subclasses",
            static::class,
            $id,
            count(static::$class_map[static::class] ?? [])
        ));
    }
    // ... existing logic ...
}
```

### Add Specificity Tie-Breaker

```php
// When specificities are equal, use class name as tie-breaker
// for consistent, predictable ordering
if ($class_specificity === $specificity && $sub_class > $class_name) {
    continue;  // Keep current winner
}
```

### Add Resolution Failure Callback

```php
protected static function on_resolution_complete($id, $resolved_class, $candidates) {
    // Hook for debugging or metrics
    do_action('digitalis/model/resolved', $id, $resolved_class, $candidates);
}
```

### Optimize Class Map Structure

```php
// Current: flat array
static::$class_map[$parent][static::class] = $specificity;

// Optimized: grouped by specificity for early exit
static::$class_map[$parent][$specificity][static::class] = true;
```

---

## 6. Candidate Feature Improvements

### 1. Lazy Resolution

Defer resolution until first method call that requires the specific type:

```php
class LazyModel {
    private $id;
    private $resolved_instance = null;

    public function __call($method, $args) {
        if (!$this->resolved_instance) {
            $class = Model::get_class_name($this->id);
            $this->resolved_instance = $class::create(['id' => $this->id]);
        }
        return $this->resolved_instance->$method(...$args);
    }
}
```

### 2. Resolution Hints

Allow callers to provide hints to speed up resolution:

```php
// Hint that it's likely a Project
$model = Post::get_instance($id, true, ['hint' => Project::class]);

// Resolution checks hint first, skips if valid
```

### 3. Batch Resolution

Optimize resolution for multiple IDs:

```php
public static function resolve_batch(array $ids) {
    // Single query to get all post types
    $types = self::get_post_types_batch($ids);

    return array_map(function($id) use ($types) {
        // Use cached type data
        return self::get_class_name($id, true, ['type' => $types[$id]]);
    }, $ids);
}
```

### 4. Resolution Events

WordPress hooks for resolution lifecycle:

```php
do_action('digitalis/model/before_resolve', $id, static::class);
$class_name = static::resolve($id);
do_action('digitalis/model/after_resolve', $id, $class_name, static::class);
```

### 5. Custom Resolution Strategies

Allow plugins to provide custom resolution logic:

```php
add_filter('digitalis/model/resolve', function($class_name, $id, $context) {
    // Custom resolution logic
    if ($context['caller'] === 'admin') {
        return Admin_Post::class;
    }
    return $class_name;
}, 10, 3);
```

### 6. Specificity Calculator UI

Admin tool to visualize and debug specificity calculations:

```php
// Admin page showing:
// - All registered classes
// - Their specificities
// - Which IDs they match
// - Resolution simulation
```

### 7. Precomputed Resolution Map

For production, precompute resolution for known entities:

```php
// Generated file
return [
    'post:123' => 'Digitalis_Co\Project',
    'post:456' => 'Digitalis_Co\Document',
    // ...
];
```

### 8. Type-Safe Resolution

PHP 8 attribute for IDE support:

```php
/**
 * @return Project
 * @psalm-return Project
 */
#[ResolvedType(Project::class)]
$project = Post::get_instance($known_project_id);
```

---

## 7. Overall Reflection

The Digitalis class resolution system represents a **sophisticated solution to a common WordPress problem**: the mismatch between WordPress's flat entity storage and object-oriented domain modeling.

### What It Does Well

1. **Solves Real Pain Point** - WordPress's post type system stores everything in one table; this adds proper polymorphism on top

2. **Elegant API** - Complex resolution logic hidden behind simple `get_instance()` call

3. **Extensible** - New models automatically participate without changes to existing code

4. **Integrates Naturally** - Works with existing Factory and DI patterns

5. **Hierarchical** - Supports arbitrary depth inheritance chains

### Where It Falls Short

1. **Performance Cost** - Validation queries add overhead, especially for bulk operations

2. **Implicit Behavior** - "Magic" resolution can surprise developers

3. **Limited Type Safety** - PHP can't statically verify resolved types

4. **Debug Difficulty** - Tracking resolution decisions requires framework knowledge

### Design Philosophy

The system prioritizes **developer convenience and domain modeling purity** over raw performance. It assumes that:
- Models are retrieved individually or in small batches
- The convenience of automatic resolution outweighs query overhead
- Developers will learn and appreciate the convention

This is appropriate for WordPress applications where:
- Request volumes are moderate
- Business logic requires rich domain models
- Developer productivity matters

---

## 8. Professional Opinion

### Assessment: Well-Designed for Its Domain

The class resolution system is **appropriately sophisticated** for WordPress plugin development. It brings patterns common in enterprise frameworks (like Doctrine's discriminator maps) to WordPress in a lightweight, convention-based form.

### When This Approach Excels

- **Domain-driven WordPress applications** with multiple custom post types
- **WooCommerce-heavy projects** with custom order types and user roles
- **Multi-tenant systems** where different user types need different models
- **Projects with deep inheritance** requiring type-specific behavior

### When to Consider Alternatives

- **High-throughput APIs** where every query matters
- **Simple CRUD applications** without type-specific behavior
- **Microservice architectures** where WordPress is just data storage
- **Teams unfamiliar** with the framework's conventions

### Recommendations

1. **Keep using it** for model instantiation - it's solving the right problem

2. **Add caching** for hot paths with many resolutions

3. **Document specificity** - create a cheat sheet for common patterns

4. **Profile resolution** - measure actual overhead in your use case

5. **Consider batch operations** - add helpers for bulk resolution

### Comparison to Alternatives

| Approach | Complexity | Performance | Type Safety |
|----------|------------|-------------|-------------|
| Digitalis Resolution | Medium | Medium | Runtime |
| Manual Type Checking | Low | High | Manual |
| Discriminator Maps | High | High | Compile-time |
| Service Locator | Medium | High | Configuration |

---

## 9. Conclusion

The Digitalis Model class resolution system is a **well-crafted, domain-specific solution** for polymorphic model handling in WordPress. It trades some performance and explicitness for significant gains in developer ergonomics and domain modeling capability.

### Key Strengths
- Eliminates repetitive type-checking code
- Enables proper object-oriented design over WordPress's flat storage
- Self-registering architecture minimizes configuration
- Integrates seamlessly with Factory and DI patterns

### Key Limitations
- Validation queries add performance overhead
- Implicit behavior requires framework knowledge
- No compile-time type safety
- Debug complexity for resolution decisions

### Verdict

**Recommended for WordPress applications** where domain modeling and developer productivity outweigh raw performance concerns. The system enables expressing business logic naturally while hiding WordPress's storage implementation details.

For bulk operations or performance-critical paths, use direct class instantiation or batch resolution helpers.

---

## 10. Next Steps

### Immediate Actions

1. **Add documentation link** from ARCHITECTURE.md to CLASS_RESOLUTION.md ✓
2. **Add inline comments** to Model.abstract.php explaining resolution
3. **Create specificity cheat sheet** for common patterns

### Short-Term Improvements

1. **Implement validation caching** to reduce query overhead
2. **Add resolution profiling** in debug mode
3. **Create batch resolution helper** for bulk operations

### Medium-Term Considerations

1. **Evaluate lazy resolution** for deferred type determination
2. **Add resolution events** for debugging and extension
3. **Consider precomputed maps** for production optimization

### Documentation Tasks

1. **Add video walkthrough** demonstrating resolution in action
2. **Create debugging guide** for resolution issues
3. **Document performance characteristics** and optimization tips

### Research

1. **Benchmark resolution overhead** in production scenarios
2. **Compare with Doctrine discriminators** for lessons learned
3. **Survey usage patterns** to identify optimization opportunities

---

## Appendix: Technical Implementation Details

### Resolution Algorithm Pseudocode

```
function get_class_name(id, auto_resolve):
    if auto_resolve is null:
        auto_resolve = get_auto_resolve()

    if not auto_resolve:
        return static::class

    best_class = static::class
    best_specificity = get_specificity()

    for each (subclass, specificity) in class_map[static::class]:
        if specificity >= best_specificity:
            if subclass::validate_id(id):
                best_class = subclass
                best_specificity = specificity

    return best_class
```

### Specificity Calculation Summary

```
Post:
    specificity = (bool)post_type * 1
               + (bool)post_status * 10
               + (bool)term * 100

User:
    specificity = (bool)role * 1

Term:
    specificity = (bool)taxonomy * 1
```

### Class Map Population

```
On autoload of each Model subclass:
    1. Calculate specificity
    2. Walk inheritance chain via get_parent_class()
    3. Register with each parent that has $class_map property
    4. Store: parent_class_map[this_class] = specificity
```

### Integration Points

```
Model::create()
    └── Model::get_class_name()
        ├── Model::get_auto_resolve()
        ├── Model::get_specificity()
        ├── iterate Model::$class_map
        │   └── SubClass::validate_id()
        └── Call::get_class_name()

Model::get_instance()
    └── Model::create()
        └── [resolution flow above]
```
