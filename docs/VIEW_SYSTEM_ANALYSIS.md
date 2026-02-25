# View System: Comprehensive Analysis

A detailed technical analysis of the Digitalis Framework's View rendering system.

---

## 1. Short Summary

The Digitalis View system is a **declarative, inheritance-aware rendering framework** that combines parameter management, dependency injection, and lifecycle hooks into a cohesive templating solution. Views define their data requirements through static properties, which are automatically inherited and merged up the class hierarchy.

Key characteristics:
- **Declarative** - Define requirements via `$defaults`, `$required`, `$merge`, `$skip_inject`
- **Inheritable** - Parent defaults automatically merge with child definitions
- **DI-Integrated** - Class names in defaults resolve to model instances
- **Lifecycle-aware** - `before_first()`, `before()`, `view()`, `after_first()`, `after()` hooks
- **Dual rendering** - Use inline `view()` method or external template files
- **ArrayAccess** - Parameters accessible via `$view['key']` or `$view->key`

The system transforms this:
```php
function render_invoice($order_id, $user_id) {
    $order = Order::get_instance($order_id);
    $user = User::get_instance($user_id);
    if (!$order || !$user) return;
    if (!$user->can('view_invoice', $order)) return;
    extract(['order' => $order, 'user' => $user]);
    include 'invoice.php';
}
```

Into this:
```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,
        'user'  => User::class,
    ];

    public function condition() {
        return $this['user']->can('view_invoice', $this['order']);
    }
}

Invoice_View::render(['order' => 721, 'user' => 1]);
```

---

## 2. Pros

### Self-Documenting Views
The `$defaults` property immediately reveals what data a view expects:

```php
protected static $defaults = [
    'order'      => Order::class,
    'user'       => User::class,
    'show_total' => true,
    'columns'    => 3,
];
```

### Automatic Property Inheritance
Child views automatically receive parent defaults without manual merging:

```php
class Base_Card extends View {
    protected static $defaults = ['wrapper' => true, 'classes' => ['card']];
}

class Product_Card extends Base_Card {
    protected static $defaults = ['product' => Product::class];
    // Gets both: wrapper, classes, and product
}
```

### Integrated Dependency Injection
Model resolution happens automatically, eliminating boilerplate:

```php
// Pass IDs
new Invoice_View(['order' => 721, 'user' => 1]);

// Receive instances
$this['order'];  // Order instance
$this['user'];   // User instance
```

### Flexible Rendering Options
Choose between inline markup or templates based on complexity:

```php
// Simple views - inline
public function view() {
    echo "<div>{$this['message']}</div>";
}

// Complex views - template
protected static $template = 'invoice';
```

### Clean Lifecycle Hooks
Separation of concerns via well-defined hooks:

```php
before_first()  // Assets, initialization (once)
before()        // Wrapper open
view()          // Content
after_first()   // Post-init (once)
after()         // Wrapper close
```

### Robust Validation System
Three-tier validation ensures views render only when appropriate:

```php
required()    // Check required parameters exist
permission()  // Access control
condition()   // Business logic conditions
```

### ArrayAccess and Property Overloading
Multiple intuitive ways to access parameters:

```php
$this['title'];        // ArrayAccess
$this->title;          // Property
$this->get_param('title');  // Method
```

### Merge Strategy for Collections
CSS classes, styles, and attributes merge intelligently:

```php
protected static $merge = ['classes', 'styles'];
// Parent: ['classes' => ['base']]
// Child:  ['classes' => ['specific']]
// Result: ['classes' => ['base', 'specific']]
```

### View Index Tracking
Automatic tracking enables first-render-only logic:

```php
$this->is_first();     // First render of this view class?
$this->get_index();    // 0, 1, 2, ... render count
```

---

## 3. Cons

### Implicit Inheritance Behavior
Merged defaults can surprise developers unfamiliar with the system:

```php
class Child extends Parent {
    protected static $defaults = ['title' => 'New'];
    // Also inherits parent's 'wrapper', 'classes', etc.
}
```

### Static Property Limitations
PHP's late static binding requires careful handling:

```php
// Must use static:: not self::
$defaults = static::get_defaults();  // Correct
$defaults = self::$defaults;         // Wrong - gets only this class
```

### Memory Overhead
Inheritance resolution stores computed values per class:

```php
protected static $prop_storage = [];  // Cached for each class
```

### Template Path Coupling
Template paths are typically hardcoded:

```php
protected static $template_path = PLUGIN_PATH . 'templates/';
// Harder to override for theme developers
```

### No Built-in Escaping
Templates must manually escape output:

```php
// In templates, must remember:
<?= esc_html($title) ?>
<?= esc_attr($class) ?>
```

### Validation Fails Silently
Failed validation returns empty string without feedback:

```php
if (!$this->validate()) return '';  // No error indication
```

### Performance in Loops
Creating many view instances has overhead:

```php
foreach ($items as $item) {
    echo new Item_View(['item' => $item]);  // Reflection, inheritance resolution each time
}
```

---

## 4. Potential Pitfalls

### Forgetting to Call parent::params()

```php
// WRONG - breaks parent's param transformations
public function params(&$p) {
    $p['total'] = $p['order']->get_total();
    // Missing parent::params($p)!
}

// CORRECT
public function params(&$p) {
    $p['total'] = $p['order']->get_total();
    parent::params($p);
}
```

### Mismatched Default Types

```php
// WRONG - expects array, gets null
protected static $defaults = [
    'items' => null,  // Should be []
];

public function view() {
    foreach ($this['items'] as $item) { }  // Error if null
}
```

### Circular Dependency in params()

```php
// DANGEROUS - infinite recursion
public function params(&$p) {
    // Calls another view that calls back to this one
    $p['preview'] = My_View::render(['item' => $p['item']], false);
}
```

### Template Variable Collisions

```php
// Template receives extracted params
extract($this->params, EXTR_OVERWRITE);

// If you have $this as a param key, it overwrites $this!
protected static $defaults = ['this' => '...'];  // Bad!
```

### Relying on Undefined Parameters

```php
// WRONG - assumes 'optional' exists
public function view() {
    if ($this['optional']) { }  // Returns null, not false
}

// CORRECT - explicit check
public function view() {
    if ($this->has_param('optional') && $this['optional']) { }
}
```

### Merge Key Forgetting

```php
class Parent extends View {
    protected static $merge = ['classes'];
}

class Child extends Parent {
    protected static $merge = ['styles'];  // Loses 'classes' merge!
}

// CORRECT - include parent merges
class Child extends Parent {
    protected static $merge = ['classes', 'styles'];
}
```

### Skip Inject Inconsistency

```php
protected static $defaults = [
    'model_class' => Order::class,  // Meant as string
];
// Forgot to add to skip_inject - will attempt injection!

protected static $skip_inject = ['model_class'];  // Required!
```

### Constructor Override Without Calling Parent

```php
// WRONG - loses default initialization
public function __construct($params = []) {
    $this->custom_setup();
    // Missing parent::__construct($params)!
}
```

---

## 5. Recommended Tweaks or Adjustments

### Add Escaping Helpers

```php
public function esc($key, $type = 'html') {
    $value = $this[$key] ?? '';
    switch ($type) {
        case 'attr': return esc_attr($value);
        case 'url':  return esc_url($value);
        default:     return esc_html($value);
    }
}

// Usage in view()
echo $this->esc('title');
echo $this->esc('url', 'url');
```

### Add Validation Feedback

```php
protected $validation_errors = [];

public function validate() {
    $this->validation_errors = [];

    if (!$this->required()) {
        $this->validation_errors[] = 'Missing required parameters';
    }
    // ... etc

    return empty($this->validation_errors);
}

public function get_validation_errors() {
    return $this->validation_errors;
}
```

### Add Debug Mode

```php
public function print($return = false) {
    if (defined('DIGITALIS_VIEW_DEBUG') && DIGITALIS_VIEW_DEBUG) {
        error_log(sprintf(
            "View: %s rendering with params: %s",
            static::class,
            json_encode(array_keys($this->params))
        ));
    }
    // ... existing logic
}
```

### Cached Inheritance Resolution

```php
protected static function get_defaults() {
    static $cache = [];
    $key = static::class;

    if (!isset($cache[$key])) {
        $cache[$key] = static::get_inherited_prop('defaults', static::get_merge_keys());
    }

    return $cache[$key];
}
```

### Theme Override Support

```php
public function get_template_path() {
    // Check theme first
    $theme_path = get_stylesheet_directory() . '/digitalis/' . static::$template . '.php';
    if (file_exists($theme_path)) {
        return get_stylesheet_directory() . '/digitalis/';
    }

    return static::$template_path;
}
```

### Batch Rendering Helper

```php
public static function render_many(array $items, $param_key = 'item') {
    $output = '';
    foreach ($items as $item) {
        $output .= static::render([$param_key => $item], false);
    }
    return $output;
}
```

---

## 6. Candidate Feature Improvements

### 1. Slot System

Allow parent views to define content slots:

```php
class Card extends View {
    protected static $slots = ['header', 'body', 'footer'];

    public function view() {
        echo '<div class="card">';
        $this->slot('header');
        $this->slot('body');
        $this->slot('footer');
        echo '</div>';
    }
}

// Usage
Card::render([
    'slots' => [
        'header' => '<h2>Title</h2>',
        'body'   => fn() => echo "Dynamic content",
    ],
]);
```

### 2. View Composition

Compose views from other views:

```php
protected static $components = [
    'header' => Header_View::class,
    'footer' => Footer_View::class,
];

public function view() {
    echo $this->component('header', ['title' => $this['title']]);
    echo $this['content'];
    echo $this->component('footer');
}
```

### 3. Caching Support

Built-in fragment caching:

```php
protected static $cache_ttl = 3600;
protected static $cache_key = ['order_id', 'user_id'];

// Automatically caches render output
```

### 4. Async Rendering

Support for streaming/async output:

```php
public static function render_async($params = []) {
    return new ViewPromise(static::class, $params);
}
```

### 5. Event Hooks

WordPress actions for view lifecycle:

```php
do_action('digitalis/view/before_render', static::class, $this->params);
do_action('digitalis/view/after_render', static::class, $html);
```

### 6. Schema Validation

Define parameter schemas for stricter validation:

```php
protected static $schema = [
    'order' => ['type' => Order::class, 'required' => true],
    'columns' => ['type' => 'int', 'min' => 1, 'max' => 12],
    'show_total' => ['type' => 'bool'],
];
```

### 7. Lazy Parameters

Defer expensive parameter calculations:

```php
protected static $lazy = ['heavy_data'];

public function get_heavy_data() {
    return expensive_calculation();  // Only called if accessed
}
```

### 8. View States

Support for conditional state-based rendering:

```php
protected static $states = ['loading', 'empty', 'error', 'success'];

public function get_state() {
    if ($this['loading']) return 'loading';
    if (empty($this['items'])) return 'empty';
    return 'success';
}

// Renders loading.php, empty.php, error.php, or success.php template
```

---

## 7. Overall Reflection

The Digitalis View system represents a **thoughtful evolution of PHP templating** that addresses common pain points in WordPress development while maintaining simplicity.

### What It Does Well

1. **Solves Real Problems** - Eliminates repetitive parameter validation, model resolution, and template setup

2. **Leverages PHP's Strengths** - Uses static properties, inheritance, and traits effectively

3. **Integrates with Framework** - DI, autoloader, and model systems work seamlessly together

4. **Provides Flexibility** - Choose inline or template rendering based on needs

5. **Enables Composition** - Views can compose other views naturally

### Where It Falls Short

1. **Learning Curve** - Inheritance merging and DI require understanding the system

2. **Debug Difficulty** - Implicit behavior makes tracing issues harder

3. **Performance Overhead** - Reflection and inheritance resolution on every render

4. **Limited Escaping** - Templates must handle output escaping manually

### Design Philosophy

The system prioritizes **developer experience and maintainability** over raw performance. It assumes:
- Views are rendered at reasonable scale (not thousands per request)
- Developer productivity matters more than microsecond optimization
- Type safety and validation prevent bugs

This is appropriate for WordPress applications where:
- UI complexity requires organized templating
- Multiple developers work on views
- Business logic benefits from validation hooks

---

## 8. Professional Opinion

### Assessment: Excellent for Component-Based Development

The View system is **well-suited for modern WordPress development** where views are treated as components with clear data contracts.

### When This Approach Excels

- **Component libraries** with reusable UI elements
- **Complex admin interfaces** with many form fields
- **Customer portals** with permission-based rendering
- **Theme development** requiring customizable components
- **Teams** where view contracts improve collaboration

### When to Consider Alternatives

- **Simple templates** without validation needs
- **Performance-critical** rendering paths
- **External API responses** (use JSON serialization instead)
- **Purely static content** without dynamic data

### Recommendations

1. **Embrace the pattern** - Use it consistently for all views
2. **Document inheritance** - Create a visual hierarchy of view classes
3. **Add escaping helpers** - Build safe-by-default output methods
4. **Consider caching** - Add fragment caching for expensive views
5. **Profile hot paths** - Optimize views that render frequently

### Comparison to Alternatives

| Approach | Type Safety | Inheritance | DI | Complexity |
|----------|-------------|-------------|-----|------------|
| Digitalis View | High | Excellent | Built-in | Medium |
| Blade (Laravel) | Low | Via includes | Manual | Low |
| Twig | Medium | Template inheritance | Manual | Medium |
| Raw PHP | None | None | Manual | Low |
| React/Vue | High | Component-based | Props | High |

---

## 9. Conclusion

The Digitalis View system is a **well-crafted, PHP-native templating solution** that brings component-based thinking to WordPress development. It trades some simplicity for significant gains in maintainability, type safety, and code organization.

### Key Strengths
- Self-documenting parameter contracts via `$defaults`
- Automatic inheritance merging eliminates boilerplate
- Integrated dependency injection for models
- Clean separation of validation and rendering
- Flexible template or inline rendering

### Key Limitations
- Learning curve for inheritance behavior
- Manual output escaping required
- Performance overhead for many instances
- Silent validation failures

### Verdict

**Highly recommended for WordPress applications** with complex UI requirements, component libraries, or team-based development. The investment in learning the system pays dividends in code organization and maintainability.

For simple one-off templates, raw PHP may be more appropriate.

---

## 10. Next Steps

### Immediate Actions

1. **Add VIEW_SYSTEM.md link** to ARCHITECTURE.md ✓
2. **Create escaping helpers** for common output patterns
3. **Document the view hierarchy** in existing projects

### Short-Term Improvements

1. **Add debug logging** option for tracing renders
2. **Implement validation feedback** for development mode
3. **Create batch rendering** helper for loops

### Medium-Term Considerations

1. **Evaluate caching layer** for expensive views
2. **Add theme override** support for template paths
3. **Consider slot system** for flexible composition

### Documentation Tasks

1. **Add visual inheritance diagram** showing property flow
2. **Create field reference** with all field types
3. **Document common patterns** and anti-patterns

### Research

1. **Benchmark render performance** at scale
2. **Survey usage patterns** in existing projects
3. **Compare with Blade/Twig** for feature gaps

---

## Appendix: Technical Implementation Details

### Print Flow

```
View::render($params)
    │
    └── new View($params)
        │
        ├── set_params(get_defaults())
        └── merge_params($params)

$view->print()
    │
    ├── set_param('view_index', n)
    ├── inject_dependencies()
    ├── params($this->params)
    │
    ├── validate()
    │   ├── required()
    │   ├── permission()
    │   └── condition()
    │
    ├── [if first] before_first()
    ├── before()
    │
    ├── [if template] extract() + require
    │   [else]        view()
    │
    ├── [if first] after_first()
    └── after()
```

### Inheritance Resolution

```php
static::get_inherited_prop('defaults', $merge_keys)
    │
    └── inherit_merge_array('defaults', $merge_keys)
        │
        ├── $value = static::$defaults
        │
        └── while ($parent = get_parent_class())
            │
            ├── for each $merge_key
            │   └── array_merge(parent[key], child[key])
            │
            └── array_merge(parent, child)
```

### Dependency Injection Flow

```php
inject_dependencies(&$params, $defaults)
    │
    ├── Remove skip_inject keys from defaults
    │
    └── array_inject($params, $defaults)
        │
        └── for each default where value is class name
            │
            ├── Check class_exists
            ├── Check method_exists('get_instance')
            │
            └── $params[key] = Class::get_instance($params[key])
```

### Parameter Merge Strategy

```php
deep_parse_args($args, $defaults, $merge)
    │
    ├── wp_parse_args($args, $defaults)  // Standard merge
    │
    └── for each $merge key
        │
        └── wp_parse_args($args[key], $defaults[key])  // Deep merge
```
