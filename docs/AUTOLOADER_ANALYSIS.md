# Digitalis Autoloader: Comprehensive Analysis

A detailed technical analysis of the Digitalis Framework's inheritance-aware autoloading system.

---

## 1. Short Summary

The Digitalis Autoloader is a custom PHP class loading system that uses **file naming conventions** to encode inheritance relationships, enabling automatic dependency resolution and correct load ordering. Instead of relying on PSR-4's namespace-to-directory mapping, it parses file names in the format `class-name.parent-class.php` to build an inheritance graph, then topologically sorts files to ensure parent classes are always loaded before their children.

Key characteristics:
- **Convention-based** - Inheritance encoded in file names
- **Self-organizing** - No manual registration or configuration needed
- **Recursive** - Processes directory trees automatically
- **Conditional** - Supports plugin-dependent loading via `~` prefix
- **Lifecycle-aware** - Calls `hello()` and `static_init()` hooks post-load

---

## 2. Pros

### Eliminates Manual Dependency Management
No need to maintain a separate autoload map or worry about include order. Simply name files correctly and the system handles the rest.

### Self-Documenting Structure
File names immediately reveal class relationships:
```
dashboard.account-page.php    → Dashboard extends Account_Page
account-page.woo-account-page.php → Account_Page extends Woo_Account_Page
```

### Plugin-Safe Conditional Loading
The `~pluginname/` convention prevents fatal errors when optional plugins (WooCommerce, ACF, etc.) are deactivated. Code is simply not loaded rather than crashing.

### Clean Separation of Contexts
The `_directory/` convention enables clean separation:
- `_admin/` - Admin-only code
- `_cli/` - CLI-only code
- `_templates/` - Non-class files

### Automatic Instantiation
Singletons and features can auto-instantiate via `get_auto_instantiation()`, reducing boilerplate bootstrap code.

### Lifecycle Hooks
`hello()` and `static_init()` provide clean extension points for registration logic without requiring instantiation.

### Zero Configuration
New classes "just work" when added to the correct directory with proper naming - no registration, no composer dump-autoload, no build step.

### WordPress-Native Feel
Aligns with WordPress's hook-driven, convention-over-configuration philosophy.

---

## 3. Cons

### Non-Standard Convention
Developers must learn a custom system rather than relying on PSR-4 knowledge. This creates onboarding friction.

### File Name Constraints
Class names are coupled to file names. Renaming a class requires renaming the file (and updating all child file names).

### Limited IDE Support
IDEs expect PSR-4 conventions. Features like "Go to Definition" may not work correctly without additional configuration.

### Namespace Independence
The file-based inheritance resolution is independent of PHP namespaces. A file named `user.user.php` with `class User extends \Digitalis\User` works, but the naming can feel redundant.

### Global State Dependencies
Conditional loading via `is_plugin_active()` requires WordPress to be loaded, limiting testability in isolation.

### Token-Based Parsing
The `extract_class_name()` method uses token parsing which, while robust, adds overhead compared to convention-based namespace resolution.

### No Composer Integration
Can't leverage Composer's optimized classmap autoloading for production.

---

## 4. Potential Pitfalls

### Circular Naming Dependencies
If `a.b.php` and `b.a.php` exist, the sorter enters infinite recursion protection but may produce unexpected ordering.

**Mitigation:** The algorithm has cycle detection, but complex hierarchies should be reviewed.

### Case Sensitivity
File system case sensitivity varies by OS. `User.user.php` vs `user.user.php` may cause issues when deploying from Mac/Windows to Linux.

**Best Practice:** Always use lowercase file names.

### Hidden Load Order Bugs
When inheritance is incorrectly encoded, errors may only appear in specific code paths.

```php
// File: special-order.order.php
class Special_Order extends Base_Order {}  // Should be special-order.base-order.php!
```

### Performance at Scale
Large directories with many files require sorting on every request (unless object cached).

### Conditional Directory Matching
The `~pluginname/` matching uses `dirname($plugin_name)` which may not match plugins with non-standard structures.

### Abstract Detection Fragility
The `.abstract.` substring check in file names is a secondary guard. Inconsistent naming can cause instantiation attempts on abstract classes.

---

## 5. Recommended Tweaks or Adjustments

### Add Caching Layer
Cache the sorted file list per directory to avoid re-sorting on every request:

```php
protected function get_file_names_cached($path, $ext = 'php') {
    $cache_key = 'digitalis_autoload_' . md5($path);

    if ($cached = wp_cache_get($cache_key, 'digitalis')) {
        return $cached;
    }

    $names = $this->get_file_names($path, $ext);
    wp_cache_set($cache_key, $names, 'digitalis', HOUR_IN_SECONDS);

    return $names;
}
```

### Validation Mode
Add a development-mode validator that checks file names match actual class declarations:

```php
if (WP_DEBUG) {
    $this->validate_class_name($path, $class_name);
}
```

### PSR-4 Fallback
Register a PSR-4 fallback for classes not found via convention:

```php
spl_autoload_register(function($class) {
    // Fallback to PSR-4 resolution
});
```

### Explicit Priority Support
Allow numeric prefixes for fine-grained ordering:

```
01-base-handler.class.php
02-specific-handler.base-handler.php
```

### Enhanced Conditional Loading
Support version constraints in directory names:

```
~woocommerce>=8.0/  # Only if WooCommerce 8.0+
```

---

## 6. Candidate Feature Improvements

### 1. Manifest Generation
Generate a static manifest during deployment:

```php
// digitalis-manifest.php (generated)
return [
    'Digitalis_Co\\User' => 'include/models/user.user.php',
    'Digitalis_Co\\Account' => 'include/models/account.user.php',
    // ...
];
```

### 2. Dependency Injection Container Integration
Integrate with a DI container for complex instantiation:

```php
public static function get_auto_instantiation() {
    return ['@container' => ['LoggerInterface', 'ConfigInterface']];
}
```

### 3. Lazy Loading Support
Defer class loading until first use:

```php
protected static $lazy = true;  // Don't load until accessed
```

### 4. Hot Reloading for Development
Watch file system changes and reload affected classes:

```php
if (WP_DEBUG && $this->files_changed($path)) {
    $this->reload_directory($path);
}
```

### 5. Load Event Hooks
WordPress actions for load events:

```php
do_action('digitalis/before_load', $class_name, $path);
do_action('digitalis/after_load', $class_name, $instance);
```

### 6. Namespace Auto-Detection
Infer namespace from directory structure:

```
include/models/woocommerce/  →  Digitalis_Co\Models\WooCommerce\
```

### 7. Multi-File Classes
Support splitting large classes across files:

```
user.user.php
user.user.part-2.php  # Additional methods
```

### 8. Interface/Trait Requirement Declaration
Encode required traits in file names:

```
feature.feature+has-hooks+has-meta.php
```

---

## 7. Overall Reflection

The Digitalis Autoloader represents a thoughtful trade-off between **convention-driven simplicity** and **standards compliance**. It solves real problems in WordPress plugin development:

1. **Load ordering** - WordPress doesn't provide native dependency resolution
2. **Conditional code** - Plugin interoperability requires safe loading
3. **Boilerplate reduction** - Auto-instantiation eliminates repetitive setup

The design reflects deep WordPress experience - prioritizing **practical functionality** over theoretical purity. The file naming convention, while non-standard, creates a remarkably **self-documenting codebase** where inheritance relationships are visible in the file system.

However, this comes at the cost of **ecosystem integration**. Modern PHP development relies heavily on Composer, PSR-4, and IDE tooling that assumes standard conventions. The framework essentially maintains a parallel universe of autoloading that doesn't benefit from these investments.

The `~conditional/` and `_skipped/` conventions are particularly clever solutions to WordPress-specific problems. They provide declarative control over loading without complex configuration files.

---

## 8. Professional Opinion

### Strengths Worth Preserving

1. **The convention-based inheritance encoding is genuinely useful.** Even if switching to PSR-4, the file naming pattern could serve as documentation.

2. **Conditional loading solves a real problem** that PSR-4 doesn't address. This feature alone justifies custom autoloading logic.

3. **Auto-instantiation reduces boilerplate** significantly for singleton/feature patterns common in WordPress.

### Recommendations for Evolution

1. **Consider hybrid approach**: Use PSR-4 for class loading but keep the custom sorter for load ordering and conditional logic.

2. **Invest in tooling**: Create IDE plugins or phpstan rules to validate naming conventions.

3. **Document extensively**: The system's value depends on developers understanding it. Current documentation should be expanded (as we're doing).

4. **Add production optimization**: Manifest generation or compiled autoloaders for performance.

### When This System Excels

- **WordPress plugin development** with complex class hierarchies
- **Teams comfortable with conventions** over explicit configuration
- **Projects requiring tight plugin interoperability**
- **Rapid prototyping** where setup overhead matters

### When to Consider Alternatives

- **Library development** intended for Composer distribution
- **Teams with strong PSR-4 expectations**
- **Projects requiring IDE-driven refactoring**
- **Microservices** or non-WordPress deployment targets

---

## 9. Conclusion

The Digitalis Autoloader is a **domain-specific solution** to WordPress plugin development challenges. It trades universal compatibility for WordPress-native convenience. This is a valid trade-off when:

- The codebase will primarily run as WordPress plugins
- Developers are trained in the conventions
- The benefits of self-organizing code outweigh PSR-4 compatibility

The system demonstrates sophisticated understanding of both PHP autoloading mechanics and WordPress plugin architecture. While non-standard, it's **well-implemented** - the topological sort is correct, edge cases are handled, and the API is clean.

For teams committed to this approach, the investment in learning the conventions pays dividends in reduced configuration and self-documenting code structure.

---

## 10. Next Steps

### Immediate Actions

1. **Add AUTOLOADER.md link** to main README or documentation index
2. **Create IDE snippets** for common file naming patterns
3. **Add validation** in development mode for name/class mismatches

### Short-Term Improvements

1. **Implement caching** for sorted file lists
2. **Add load-time profiling** to identify slow directories
3. **Create debugging tools** for visualizing load order

### Medium-Term Considerations

1. **Evaluate PSR-4 hybrid** approach for future projects
2. **Build Composer bridge** for distributable packages
3. **Create phpstan rules** for convention validation

### Documentation Tasks

1. **Add video walkthrough** of naming conventions
2. **Create migration guide** for existing codebases
3. **Document all edge cases** and error scenarios

### Research

1. **Benchmark against PSR-4** for large codebases
2. **Survey developer experience** with the conventions
3. **Evaluate compiled autoloader** for production

---

## Appendix: Technical Implementation Notes

### Sorting Algorithm

```php
protected function sort_inherits($inherits, $sorted = []) {
    // 1. Prioritize traits, interfaces, standalone
    if ($priority = array_intersect($inherits, ['', 'trait', 'interface'])) {
        foreach ($priority as $child => $parent) {
            $sorted[$child] = $parent;
            unset($inherits[$child]);
        }
    }

    // 2. Add classes whose parents are already sorted or external
    foreach ($inherits as $child => $parent) {
        if (!array_key_exists($parent, $inherits) || ($child == $parent)) {
            $sorted[$child] = $parent;
            unset($inherits[$child]);
        }
    }

    // 3. Recurse until all sorted
    return $inherits ? $this->sort_inherits($inherits, $sorted) : $sorted;
}
```

### Class Name Extraction

The `extract_class_name()` method uses PHP's tokenizer to reliably extract class names, handling:
- Namespaces (including `T_NAME_QUALIFIED` for PHP 8+)
- Anonymous classes
- `::class` constant references
- Files with multiple classes (returns first)

### Filter Integration Points

```php
// Global filter (all classes)
apply_filters('Digitalis/Instantiate/', $instantiation, $class_name, $path);

// Class-specific filter
apply_filters('Digitalis/Instantiate/Namespace/Class_Name', $instantiation, $path);
```

These filters enable:
- Disabling instantiation globally during testing
- Overriding specific class instantiation
- Logging/profiling load behavior
- Integration with dependency injection containers
