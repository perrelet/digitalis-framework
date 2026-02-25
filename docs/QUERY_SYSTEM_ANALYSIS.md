# Query System Analysis: Digitalis_Query & Query_Vars

Deep technical analysis of the framework's query building system.

---

## 1. Short Summary

The query system consists of two classes working in tandem:

- **`Query_Vars`** - A fluent builder for WP_Query arguments that implements `ArrayAccess`, provides smart merging with WordPress quirk handling, and enables by-reference modification of nested meta/tax queries.

- **`Digitalis_Query`** - An extended `WP_Query` that wraps `Query_Vars` and critically **defers query execution** until `query()` is explicitly called. This separation of construction from execution enables query composition from multiple sources.

The core innovation is treating query building as a first-class concern rather than a side effect of instantiation.

---

## 2. Pros

### Deferred Execution
- Queries can be built incrementally from multiple sources (main query, user filters, programmatic conditions)
- No wasted database queries when query args need modification
- Enables "prepare once, execute when ready" patterns

### Fluent Interface
- All modification methods return `$this` for chaining
- Reads naturally: `$query->set_var(...)->add_meta_query(...)->query()`
- Reduces intermediate variable clutter

### Smart Merging
- `merge_var()` handles WP quirks automatically:
  - `post_type` and `post_status` convert to arrays when merging
  - `'any'` takes precedence appropriately
  - `meta_query` and `tax_query` append rather than replace
- Distinguishes between merge (combine) and overwrite (replace)

### By-Reference Query Finding
- `find_meta_query()` and `find_tax_query()` return references
- Enables modifying nested query clauses in place
- Recursive search handles nested relation groups

### ArrayAccess Implementation
- Query_Vars can be used with array syntax: `$qv['post_type'] = 'project'`
- Familiar interface for WordPress developers
- Works with existing array-expecting code

### Static Utilities
- `compare_post_type()` handles all edge cases (taxonomy archives, arrays, 'any')
- `is_multiple()` centralizes archive detection logic
- Reusable across the framework without instantiation

### Constructor Defaults
- Initializes `meta_query` and `tax_query` as empty arrays
- Eliminates null checks before adding clauses
- Prevents "cannot push to null" errors

---

## 3. Cons

### Inheritance Coupling
- `Digitalis_Query` extends `WP_Query`, coupling to WordPress internals
- Any WP_Query changes could affect behavior
- Cannot easily swap to a different query implementation

### Dual State
- After `query()` is called, both `$query_vars_obj` and parent's `$query_vars` exist
- Potential confusion about which is authoritative
- Modifications after execution don't affect results

### Falsy Value Handling
- By default, `merge()` skips falsy values
- Can be surprising: `merge(['posts_per_page' => 0])` is ignored
- Requires explicit `$merge_falsy = true` parameter
- Comment in code acknowledges: "What about falsey values??"

### Missing Relation Control
- `add_meta_query()` appends clauses but doesn't set relation
- Default AND relation may not be desired
- No fluent method to set `meta_query['relation']`

### find_* Parameter Order
- `find_meta_query($value, $compare, $key)` vs `find_tax_query($value, $compare, $key)`
- Parameter names suggest different defaults could be confusing
- ~~`find_meta_query` had parameter order bug (now fixed)~~

---

## 4. Potential Pitfalls

### Forgetting to Call query()
```php
$query = new Digitalis_Query(['post_type' => 'project']);
$query->add_meta_query([...]);
// BUG: Forgot to call $query->query()!
foreach ($query->posts as $post) { ... }  // Empty! No query executed.
```

### Modifying After Execution
```php
$query = new Digitalis_Query(['post_type' => 'project']);
$query->query();
$query->set_var('posts_per_page', 5);  // Does nothing to results!
// Must call query() again to apply changes
```

### Reference Assignment Without &
```php
// WRONG: No reference, modifying a copy
$meta = $qv->find_meta_query('status');
$meta['value'] = 'active';  // Original unchanged!

// CORRECT: Use reference
$meta =& $qv->find_meta_query('status');
$meta['value'] = 'active';  // Original modified
```

### Merge vs Overwrite Confusion
```php
$qv->set('post_status', 'publish');
$qv->merge(['post_status' => 'draft']);     // Now ['publish', 'draft']
$qv->overwrite(['post_status' => 'draft']); // Now just 'draft'
// Easy to use wrong method
```

### Global $wp_query Fallback in is_multiple()
```php
// Passing null uses global $wp_query
Digitalis_Query::is_multiple(null);  // Checks global
Digitalis_Query::is_multiple();      // Also checks global
// Could be surprising in AJAX/REST contexts
```

### AJAX Action Prefix Check
```php
// is_multiple() returns true for AJAX actions starting with 'query'
// If you have an action named 'query_settings', it's treated as multiple
```

---

## 5. Recommended Tweaks or Adjustments

### Add Relation Methods
```php
public function set_meta_relation($relation = 'AND') {
    $this->query['meta_query']['relation'] = $relation;
    return $this;
}

public function set_tax_relation($relation = 'AND') {
    $this->query['tax_query']['relation'] = $relation;
    return $this;
}
```

### Fix find_meta_query Parameter Order (FIXED)
```php
// Was (incorrect):
public function &find_meta_query($value, $compare = '=', $key = 'key') {
    return $this->find($this->query['meta_query'], $value, $key, $compare);
    //                                             ^^^^^^^^^^^^^ swapped!
}

// Now (correct):
public function &find_meta_query($value, $compare = '=', $key = 'key') {
    return $this->find($this->query['meta_query'], $value, $compare, $key);
}
```

### Add Executed State Check
```php
protected $executed = false;

public function query($query = [], $merge_falsy = false) {
    if ($query) $this->merge($query, $merge_falsy);
    $this->executed = true;
    return parent::query($this->get_query_vars());
}

public function is_executed() {
    return $this->executed;
}
```

### Consider Immutable Builder Pattern
```php
// Return new instance instead of mutating
public function with_var($key, $value) {
    $clone = clone $this;
    $clone->set_var($key, $value);
    return $clone;
}
```

---

## 6. Candidate Feature Improvements

### Query Presets/Scopes
```php
class Project extends Post {
    public static function query_active() {
        return (new Digitalis_Query(['post_type' => 'project']))
            ->add_meta_query(['key' => 'status', 'value' => 'active']);
    }
}

// Usage: Project::query_active()->query();
```

### Debugging/Logging
```php
public function get_sql() {
    // Return the SQL that would be executed
}

public function explain() {
    // Return EXPLAIN output for the query
}
```

### Named Meta/Tax Queries
```php
$qv->add_meta_query([
    'status_clause' => [  // Named clause
        'key' => 'status',
        'value' => 'active',
    ],
]);

// Later, find by name
$clause =& $qv->find_meta_query('status_clause', '=', 'name');
```

### Pagination Helper
```php
public function paginate($page = 1, $per_page = 10) {
    return $this
        ->set_var('posts_per_page', $per_page)
        ->set_var('paged', $page);
}
```

### Count Without Fetching Posts
```php
public function count() {
    $this->set_var('fields', 'ids');
    $this->set_var('no_found_rows', false);
    $this->query();
    return $this->found_posts;
}
```

### Collection Return Type
```php
public function get() {
    $this->query();
    return new Post_Collection($this->posts, $this);
}
```

---

## 7. Overall Reflection

The query system represents a thoughtful solution to a real WordPress pain point: the immediate execution of WP_Query in its constructor makes query composition difficult. By deferring execution and wrapping query vars in a dedicated builder class, the framework enables patterns that would otherwise require awkward workarounds.

The design shows pragmatism over purity. Rather than creating an entirely new query abstraction, it extends WP_Query and preserves full compatibility. This means developers can fall back to standard WP_Query patterns when needed while benefiting from the fluent interface when desired.

The smart merging logic in `merge_var()` demonstrates deep WordPress knowledge, handling quirks like `post_type` arrays and `'any'` values that would trip up naive implementations. The by-reference finding methods show understanding of real-world needs: modifying nested meta queries in place is a common requirement that's surprisingly hard with raw arrays.

However, the system carries some cognitive load. Developers must remember that construction and execution are separate steps, and the difference between `merge()` and `overwrite()` requires understanding. The falsy value handling is a footgun waiting to happen.

---

## 8. Professional Opinion

This is a **well-designed utility layer** that solves real problems without over-engineering. The deferred execution pattern is the standout feature - it transforms WP_Query from a "configure and forget" API into a composable building block.

The implementation quality is solid. Fluent interfaces are consistent, ArrayAccess integration is complete, and the static utilities handle edge cases thoroughly. The `compare_post_type()` method in particular shows attention to WordPress's quirky query semantics.

**I would recommend this pattern for any WordPress project** that deals with complex query building. The learning curve is minimal for WordPress developers, and the benefits compound as query complexity grows.

The main improvement opportunity is documentation and guardrails. Adding an "executed" state and clearer error messages would reduce the "forgot to call query()" footgun.

---

## 9. Conclusion

The Query_Vars and Digitalis_Query classes form a cohesive query building system that addresses WordPress's eager execution model while remaining fully compatible with WP_Query. The fluent interface, smart merging, and by-reference finding capabilities make complex query composition manageable.

**Key takeaways:**
1. Always call `query()` to execute - construction is separate from execution
2. Use `merge()` to combine values intelligently, `overwrite()` to replace
3. Use `&` when capturing find results for in-place modification
4. Static utilities handle edge cases - prefer them over manual checks
5. The system works best when query building spans multiple contexts

---

## 10. Next Steps

1. ~~**Fix `find_meta_query` parameter order bug**~~ ✓ Fixed

2. **Add relation methods** for meta_query and tax_query

3. **Consider adding execution state tracking** to prevent silent failures

4. **Document falsy value behavior** more prominently to prevent surprises

5. **Explore adding query scopes/presets** for common query patterns

6. **Consider a `QueryBuilder` factory** for even more fluent construction:
   ```php
   Project::where('status', 'active')
          ->whereMeta('priority', 'high')
          ->orderBy('date', 'DESC')
          ->limit(10)
          ->get();
   ```
