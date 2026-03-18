# Digitalis Dependency Injection: Comprehensive Analysis

A detailed technical analysis of the Digitalis Framework's lightweight dependency injection system.

---

## 1. Short Summary

The Digitalis DI system is a **reflection-based, convention-driven** dependency injection mechanism that automatically converts scalar values (typically IDs) into model instances. It operates on a simple principle: any class with a `get_instance()` method can be injected.

Key characteristics:
- **Lightweight** - Single trait, ~110 lines of code
- **Convention-based** - Uses type hints and `get_instance()` method convention
- **Context-aware** - Different injection strategies for Views, Routes, Tables
- **WordPress-native** - Designed for WordPress entity patterns (posts, users, orders)
- **Non-invasive** - Doesn't require a container or complex configuration

The system transforms this:
```php
$order_id = 721;
$this->process_order($order_id);  // Manual resolution needed

function process_order($order_id) {
    $order = Order::get_instance($order_id);  // Boilerplate
    // ...
}
```

Into this:
```php
static::inject([$this, 'process_order'], [$order_id]);

function process_order(Order $order) {  // Already resolved
    // Direct model access
}
```

---

## 2. Pros

### Eliminates Repetitive Resolution Code
Every method that needs a model no longer requires `$model = Model::get_instance($id)` boilerplate.

```php
// Before DI
public function column_account($post_id) {
    $project = Project::get_instance($post_id);
    $account = $project->get_account();
    // ...
}

// After DI
public function column_account(Project $project) {
    $account = $project->get_account();
    // ...
}
```

### Type Safety
Type hints provide IDE autocompletion, static analysis, and runtime validation.

### Clean View API
Views become declarative about their data requirements:

```php
protected static $defaults = [
    'order' => Order::class,
    'user'  => User::class,
];
```

### Seamless Route Integration
REST route methods receive models directly, simplifying permission and callback logic:

```php
public function permission(WP_REST_Request $request, ?Order $order = null) {
    return $user->can('view_order', $order->get_id());  // No manual lookup
}
```

### Consistent Model Access
All contexts (Views, Routes, Tables) access models the same way - through type-hinted parameters.

### Zero Configuration
No container setup, no binding registration, no service providers. Just type hints.

### WordPress Pattern Alignment
Works with WordPress's ID-centric architecture while providing an object-oriented interface.

### Testability Improvement
Methods with injected dependencies are easier to test - pass mock instances directly.

---

## 3. Cons

### Implicit Behavior
The "magic" of automatic resolution can confuse developers unfamiliar with the system:

```php
// Where does $order come from? Not obvious without knowing the DI system
public function callback(WP_REST_Request $request, ?Order $order = null) {
```

### Performance Overhead
Reflection is used on every injection:
- `ReflectionClass` instantiation
- `ReflectionMethod::getParameters()`
- Type inspection via `getType()->getName()`

### Limited to get_instance() Pattern
Only works with classes implementing `get_instance()`. Standard PHP classes or WordPress objects without this pattern require manual resolution.

### No Interface/Abstract Support
Can't inject based on interfaces - must use concrete classes:

```php
// Won't work - interface has no get_instance()
public function process(OrderInterface $order) {}

// Must use concrete class
public function process(Order $order) {}
```

### Null Handling Complexity
Nullable types and failed lookups require careful handling:

```php
public function callback(Request $request, ?Order $order = null) {
    // Must always check for null
    if (!$order) return new WP_Error('not_found', '...');
}
```

### No Circular Dependency Detection
If Model A's `get_instance()` requires Model B, and B requires A, infinite loops occur.

### Debugging Difficulty
Stack traces through reflection calls are harder to read and debug.

---

## 4. Potential Pitfalls

### Forgetting the Class Key in Routes

```php
// WRONG - no injection
protected function get_params() {
    return [
        'order' => ['required' => true],  // Missing 'class' key
    ];
}

// CORRECT
protected function get_params() {
    return [
        'order' => ['required' => true, 'class' => Order::class],
    ];
}
```

### Mismatched Type Hints and Defaults

```php
// View defaults
protected static $defaults = [
    'order' => Order::class,
];

// WRONG - type hint doesn't match
public function view() {
    $this['order'];  // Works
}

public function process(WC_Order $order) {  // Won't inject - wrong type
```

### Assuming Injection Always Succeeds

```php
// DANGEROUS - assumes order exists
public function callback(Request $request, Order $order) {
    return $order->get_total();  // Crashes if order not found
}

// SAFE - nullable with check
public function callback(Request $request, ?Order $order = null) {
    if (!$order) return new WP_Error('not_found', 'Order not found', 404);
    return $order->get_total();
}
```

### Performance in Loops

```php
// SLOW - reflection on every iteration
foreach ($ids as $id) {
    static::inject([$this, 'process'], [$id]);
}

// BETTER - batch resolution
$models = Model::get_instances($ids);
foreach ($models as $model) {
    $this->process($model);
}
```

### Inheritance and skip_inject

```php
class Parent_View extends View {
    protected static $defaults = [
        'model' => Model::class,
    ];
}

class Child_View extends Parent_View {
    // Forgot that parent injects 'model'
    // Unexpected behavior when passing raw data
}
```

### Union Type First-Type Resolution

```php
// Only Order is used for injection (first type)
public function process(Order|Product $item) {
    // Product IDs won't resolve correctly
}
```

---

## 5. Recommended Tweaks or Adjustments

### Add Reflection Caching

```php
protected static $reflection_cache = [];

protected static function get_cached_parameters($call) {
    $key = is_array($call) ? get_class($call[0]) . '::' . $call[1] : $call;

    if (!isset(self::$reflection_cache[$key])) {
        if (is_array($call)) {
            $class = new ReflectionClass($call[0]);
            $func = $class->getMethod($call[1]);
        } else {
            $func = new ReflectionFunction($call);
        }
        self::$reflection_cache[$key] = $func->getParameters();
    }

    return self::$reflection_cache[$key];
}
```

### Add Injection Failure Callback

```php
protected static function on_inject_failure($class, $value, $context) {
    if (WP_DEBUG) {
        error_log("DI: Failed to resolve {$class} from value: " . print_r($value, true));
    }
    return null;
}
```

### Support Interface Binding

```php
protected static $bindings = [
    OrderInterface::class => Order::class,
];

protected static function resolve_class($type_name) {
    return self::$bindings[$type_name] ?? $type_name;
}
```

### Add Explicit Injection Marker

```php
protected static $defaults = [
    'order' => ['@inject' => Order::class],  // Explicit
    'title' => 'Default',                     // Regular default
];
```

### Batch Resolution Helper

```php
public static function resolve_many($class, array $ids) {
    return array_filter(array_map(
        fn($id) => $class::get_instance($id),
        $ids
    ));
}
```

---

## 6. Candidate Feature Improvements

### 1. Lazy Injection

Defer resolution until first access:

```php
class LazyProxy {
    private $class;
    private $value;
    private $instance = null;

    public function __get($name) {
        if (!$this->instance) {
            $this->instance = $this->class::get_instance($this->value);
        }
        return $this->instance->$name;
    }
}
```

### 2. Injection Scopes

Control injection lifecycle:

```php
protected static $injection_scope = [
    'order' => 'request',   // Cached per request
    'user'  => 'singleton', // Cached globally
    'temp'  => 'none',      // Always fresh
];
```

### 3. Custom Resolver Registration

```php
Dependency_Injection::register_resolver(
    WC_Order::class,
    fn($id) => wc_get_order($id)
);
```

### 4. Automatic Null Coalescing

```php
protected static $defaults = [
    'order' => Order::class ?? null,  // Return null if not found
];
```

### 5. Injection Events

```php
do_action('digitalis/di/before_inject', $class, $value);
$instance = $class::get_instance($value);
do_action('digitalis/di/after_inject', $class, $value, $instance);
```

### 6. Debug Mode

```php
if (defined('DIGITALIS_DI_DEBUG') && DIGITALIS_DI_DEBUG) {
    error_log("DI: Resolving {$class} from " . print_r($value, true));
}
```

### 7. Attribute-Based Injection (PHP 8+)

```php
class My_View extends View {
    #[Inject(Order::class)]
    protected $order;

    #[Inject(User::class, required: false)]
    protected $user;
}
```

### 8. Validation Rules

```php
protected static $defaults = [
    'order' => [
        'class' => Order::class,
        'validate' => fn($order) => $order->get_status() !== 'trash',
    ],
];
```

---

## 7. Overall Reflection

The Digitalis DI system represents a **pragmatic middle ground** between no DI and full container-based DI. It solves the specific problem of converting WordPress's ID-centric APIs into object-oriented interfaces.

### What It Does Well

1. **Solves a real pain point** - WordPress constantly passes IDs; this system converts them elegantly
2. **Stays lightweight** - No container overhead, no complex configuration
3. **Integrates naturally** - Works with existing WordPress and WooCommerce patterns
4. **Provides consistency** - Same injection pattern across Views, Routes, Tables

### Where It Falls Short

1. **Limited scope** - Only works with `get_instance()` pattern
2. **No interface binding** - Can't swap implementations
3. **Implicit behavior** - Magic can confuse newcomers
4. **Performance cost** - Reflection on every call

### Design Philosophy

The system prioritizes **developer convenience over architectural purity**. It doesn't try to be a full DI container - it's a targeted solution for model resolution in WordPress contexts.

---

## 8. Professional Opinion

### Assessment: Well-Suited for Its Purpose

The Digitalis DI system is **appropriately scoped** for WordPress plugin development. It provides meaningful benefits without imposing the complexity of full DI containers like PHP-DI or Symfony's Container.

### When This Approach Works

- **WordPress/WooCommerce plugins** with entity-heavy architectures
- **Teams comfortable with conventions** over explicit configuration
- **Projects where model resolution** is the primary DI need
- **Codebases already using the Factory pattern** with `get_instance()`

### When to Consider Alternatives

- **Complex dependency graphs** requiring interface binding
- **Service-oriented architectures** needing scoped lifecycles
- **Large teams** where explicit configuration aids understanding
- **Testing-heavy projects** requiring extensive mocking

### Recommendations

1. **Keep using it** for model injection - it's the right tool
2. **Add reflection caching** for performance in hot paths
3. **Document the magic** - new developers need clear guidance
4. **Consider a hybrid approach** - use this for models, explicit DI for services

### Comparison to Alternatives

| Approach | Complexity | Flexibility | WordPress Fit |
|----------|------------|-------------|---------------|
| Digitalis DI | Low | Medium | Excellent |
| PHP-DI | Medium | High | Good |
| Symfony Container | High | Very High | Poor |
| Manual resolution | Very Low | Low | Native |

---

## 9. Conclusion

The Digitalis Dependency Injection system is a **well-crafted, domain-specific solution** for WordPress model resolution. It trades the flexibility of full DI containers for simplicity and WordPress-native integration.

### Key Strengths
- Eliminates boilerplate model resolution
- Provides type safety through hints
- Zero configuration overhead
- Consistent across multiple contexts

### Key Limitations
- Only supports `get_instance()` pattern
- No interface binding or scopes
- Reflection performance overhead
- Implicit behavior requires documentation

### Verdict

**Recommended for WordPress plugin development** where the primary DI need is converting IDs to model instances. For more complex dependency needs, consider supplementing with a dedicated service locator or container.

---

## 10. Next Steps

### Immediate Actions

1. **Add documentation link** from ARCHITECTURE.md to DEPENDENCY_INJECTION.md
2. **Add inline comments** to the trait explaining each method
3. **Create IDE helper file** for better autocompletion

### Short-Term Improvements

1. **Implement reflection caching** to reduce overhead
2. **Add debug logging** option for tracing injections
3. **Create validation helpers** for common patterns

### Medium-Term Considerations

1. **Evaluate PHP 8 attributes** for more explicit injection
2. **Add interface binding** for better testability
3. **Consider lazy loading** for performance optimization

### Documentation Tasks

1. **Add video walkthrough** demonstrating injection in different contexts
2. **Create cheat sheet** for quick reference
3. **Document all edge cases** and gotchas

### Research

1. **Benchmark reflection overhead** in production scenarios
2. **Survey usage patterns** in existing codebases
3. **Evaluate WordPress-specific DI libraries** for comparison

---

## Appendix: Technical Implementation Details

### Injection Flow

```
1. inject($call, $args, $values) called
   │
   ├─ 2. get_inject_args() inspects callable
   │     │
   │     └─ 3. Uses ReflectionClass/ReflectionFunction
   │
   ├─ 4. function_inject() iterates parameters
   │     │
   │     ├─ 5. Get type hint via $param->getType()
   │     │
   │     ├─ 6. Check class_exists($class)
   │     │
   │     ├─ 7. Check method_exists($class, 'get_instance')
   │     │
   │     └─ 8. Call $class::get_instance($value)
   │
   └─ 9. call_user_func_array($call, $resolved_args)
```

### Type Resolution Logic

```php
// From function_inject()
if (!$type = $param->getType()) continue;           // Skip untyped
if ($type instanceof ReflectionUnionType)           // Handle unions
    $type = $type->getTypes()[0];                   // Use first type
if (!$class = $type->getName()) continue;           // Get class name
if (!class_exists($class)) continue;                // Must exist
if (!method_exists($class, 'get_instance')) continue; // Must have factory

// Resolve
$args[$i] = $class::get_instance($args[$i] ?? null);
```

### View Injection Point

```php
// In View::print()
$this->inject_dependencies($this->params, static::get_defaults());

// inject_dependencies()
protected function inject_dependencies(&$params, $defaults) {
    foreach (static::get_skip_inject_keys() as $key) {
        if (isset($defaults[$key])) unset($defaults[$key]);
    }
    static::array_inject($params, $defaults);
}
```

### Route Injection Point

```php
// In Route::request_inject()
if ($params = $this->get_params()) {
    foreach ($params as $key => $param) {
        if ($class = $param['class'] ?? false) {
            $values[$class] = static::value_inject($class, $request_params[$key]);
        }
    }
}
return static::inject([$this, $method], [$request], $values);
```
