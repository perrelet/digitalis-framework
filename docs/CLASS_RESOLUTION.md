# Digitalis Framework: Model Class Resolution

The Digitalis Framework includes an automatic class resolution system that determines the most specific model subclass when retrieving instances. This enables polymorphic model handling where base class factory methods return the appropriate specialized subclass.

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Specificity System](#specificity-system)
- [Class Map Registration](#class-map-registration)
- [Validation Methods](#validation-methods)
- [Controlling Resolution](#controlling-resolution)
- [Real-World Examples](#real-world-examples)
- [Integration with Factory Pattern](#integration-with-factory-pattern)
- [Troubleshooting](#troubleshooting)

---

## Overview

### The Problem

WordPress stores all posts in a single `wp_posts` table, differentiated only by `post_type`. When retrieving a post, you typically get a generic `WP_Post` object regardless of its type.

```php
// Standard WordPress - always returns WP_Post
$post = get_post(123);  // WP_Post object

// You must manually check and instantiate
if ($post->post_type === 'project') {
    $project = new Project($post);
}
```

### The Solution

The Digitalis class resolution system automatically determines and returns the most specific class:

```php
// Digitalis - returns the appropriate class
$model = Post::get_instance(123);

// If post 123 is type 'project' → Project instance
// If post 123 is type 'document' → Document instance
// Otherwise → Post instance
```

### Key Characteristics

- **Automatic** - No manual type checking required
- **Hierarchical** - Respects inheritance chains
- **Extensible** - Add new specific classes by extending existing ones
- **Specificity-based** - Most specific matching class wins
- **Validation-driven** - Each class validates whether an ID belongs to it

---

## How It Works

### Resolution Flow

```
Post::get_instance(123)
    │
    ├─ 1. get_class_name(123) called
    │      │
    │      ├─ 2. Check if auto_resolve is enabled
    │      │
    │      ├─ 3. Get current class specificity
    │      │
    │      ├─ 4. Iterate Model::$class_map[Post::class]
    │      │      │
    │      │      ├─ Project::class → specificity: 1
    │      │      │   └─ Project::validate_id(123) → true/false
    │      │      │
    │      │      ├─ Document::class → specificity: 1
    │      │      │   └─ Document::validate_id(123) → true/false
    │      │      │
    │      │      └─ Active_Project::class → specificity: 11
    │      │          └─ Active_Project::validate_id(123) → true/false
    │      │
    │      └─ 5. Return highest-specificity class that validates
    │
    └─ 6. Call resolved_class::create(['id' => 123])
```

### Core Method: `get_class_name()`

```php
// From Model.abstract.php
public static function get_class_name($id, $auto_resolve = null) {
    $class_name = static::class;

    if (is_null($auto_resolve)) $auto_resolve = static::get_auto_resolve();

    if ($auto_resolve) {
        $specificity = static::get_specificity();

        if (static::$class_map[static::class] ?? 0) {
            foreach (static::$class_map[static::class] as $sub_class => $class_specificity) {
                if (($class_specificity >= $specificity) && $sub_class::validate_id($id)) {
                    $class_name  = $sub_class;
                    $specificity = $class_specificity;
                }
            }
        }
    }

    return Call::get_class_name($class_name, ['id' => $id]);
}
```

### Factory Method Integration

```php
// Model::create() uses get_class_name()
public static function create($args = []) {
    $id = $args['id'] ?? $args[0] ?? null;
    $class_name = static::get_class_name($id);

    // If resolved to different class, delegate
    if ($class_name !== static::class) {
        return $class_name::create($args);
    }

    // Otherwise create instance of this class
    return parent::create($args);
}
```

---

## Specificity System

### Purpose

Specificity determines which class "wins" when multiple classes could match an ID. Higher specificity = more specific class = preferred result.

### Post Specificity

Posts can be specific based on three criteria:

| Property | Weight | Description |
|----------|--------|-------------|
| `$post_type` | 1 | Specific post type |
| `$post_status` | 10 | Specific status(es) |
| `$term` | 100 | Has specific term |

**Formula:**
```php
public static function get_specificity() {
    return (int) (
        ((bool) static::$post_type)   * 1   +
        ((bool) static::$post_status) * 10  +
        ((bool) static::$term)        * 100
    );
}
```

**Examples:**

| Class | post_type | post_status | term | Specificity |
|-------|-----------|-------------|------|-------------|
| `Post` | - | - | - | 0 |
| `Project` | `project` | - | - | 1 |
| `Draft_Project` | `project` | `draft` | - | 11 |
| `Featured_Project` | `project` | - | `featured` | 101 |
| `Featured_Draft` | `project` | `draft` | `featured` | 111 |

### User Specificity

Users are specific based on role:

| Property | Weight | Description |
|----------|--------|-------------|
| `$role` | 1 | Specific role(s) |

**Formula:**
```php
public static function get_specificity() {
    return (int) ((bool) static::$role);
}
```

**Examples:**

| Class | role | Specificity |
|-------|------|-------------|
| `User` | - | 0 |
| `Account` | `account` | 1 |
| `Admin` | `administrator` | 1 |

### Term Specificity

Terms are specific based on taxonomy:

| Property | Weight | Description |
|----------|--------|-------------|
| `$taxonomy` | 1 | Specific taxonomy |

**Formula:**
```php
public static function get_specificity() {
    return (int) ((bool) static::$taxonomy);
}
```

---

## Class Map Registration

### How Classes Register

During autoload, each model's `static_init()` method registers it with all parent classes:

```php
// From Model.abstract.php
public static function static_init() {
    $specificity = static::get_specificity();
    $parent      = static::class;

    // Walk up inheritance chain
    while ($parent = get_parent_class($parent)) {
        if (!property_exists($parent, 'class_map')) break;

        if (!isset(static::$class_map[$parent])) {
            static::$class_map[$parent] = [];
        }

        static::$class_map[$parent][static::class] = $specificity;
    }
}
```

### Resulting Class Map Structure

```php
Model::$class_map = [
    'Digitalis\Post' => [
        'Digitalis_Co\Project'        => 1,
        'Digitalis_Co\Document'       => 1,
        'Digitalis_Co\Gateway'        => 1,
        'Digitalis_Co\Portfolio_Item' => 1,
    ],
    'Digitalis\User' => [
        'Digitalis_Co\Account' => 1,
    ],
    'Digitalis\Model' => [
        'Digitalis_Co\Project'        => 1,
        'Digitalis_Co\Document'       => 1,
        'Digitalis_Co\Account'        => 1,
        // ... all models
    ],
];
```

### Inheritance Chain Registration

When a class has multiple levels of inheritance, it registers with each ancestor:

```php
// Class hierarchy
class Post extends WP_Model { }
class Project extends Post { protected static $post_type = 'project'; }
class Active_Project extends Project { protected static $post_status = 'active'; }

// Active_Project registers with:
Model::$class_map['Digitalis\Post']['Active_Project'] = 11;
Model::$class_map['Digitalis\WP_Model']['Active_Project'] = 11;
Model::$class_map['Digitalis\Model']['Active_Project'] = 11;
```

---

## Validation Methods

### Post Validation

```php
// From Post.model.php
public static function validate_id($id) {
    // Check post type
    if (static::$post_type && (get_post_type($id) != static::$post_type)) {
        return false;
    }

    // Check term
    if (static::$term && (!has_term(static::$term, static::$taxonomy, $id))) {
        return false;
    }

    // Check post status
    if (static::$post_status) {
        if (!is_array(static::$post_status)) {
            static::$post_status = [static::$post_status];
        }
        if (!in_array(get_post_status($id), static::$post_status)) {
            return false;
        }
    }

    return parent::validate_id($id);
}
```

### User Validation

```php
// From User.model.php
public static function validate_id($id) {
    if (static::$role) {
        if (!is_array(static::$role)) {
            static::$role = [static::$role];
        }

        if (!$wp_user = get_user_by('id', $id)) {
            return false;
        }

        if (!array_intersect(static::$role, $wp_user->roles)) {
            return false;
        }
    }

    return parent::validate_id($id);
}
```

### Term Validation

```php
// From Term.model.php
public static function validate_id($id) {
    if (static::$taxonomy) {
        $term = get_term($id);
        if (!$term || $term->taxonomy !== static::$taxonomy) {
            return false;
        }
    }

    return parent::validate_id($id);
}
```

---

## Controlling Resolution

### Auto-Resolve Property

By default, base classes (`Post`, `User`, `Term`) have `$auto_resolve = true`. Specific subclasses typically don't need to enable it explicitly since they inherit this behavior.

```php
// Base class enables resolution
class Post extends WP_Model {
    protected static $auto_resolve = true;
}

// Subclass inherits behavior
class Project extends Post {
    protected static $post_type = 'project';
    // No need to set $auto_resolve
}
```

### Disabling Resolution

#### Per-Call Basis

```php
// Force base class
$post = Post::get_instance(123, false);  // Always returns Post

// Or use create() directly
$post = Post::create(['id' => 123]);  // May still resolve
```

#### Per-Class Basis

```php
class Unresolved_Post extends Post {
    protected static $auto_resolve = false;

    public static function get_auto_resolve() {
        return false;
    }
}
```

### Specificity as Gate

Resolution only considers classes with specificity >= the calling class:

```php
// Called on Post (specificity 0) → checks all subclasses
Post::get_instance(123);

// Called on Project (specificity 1) → only checks subclasses with specificity >= 1
Project::get_instance(123);  // Won't "downgrade" to Document
```

---

## Real-World Examples

### digitalis-co Plugin Models

#### Post-Based Models

```php
// Document model
class Document extends Post {
    protected static $post_type = 'document';

    public function get_download_url() {
        return wp_get_attachment_url($this->get_field('document_file'));
    }
}

// Project model
class Project extends Post {
    protected static $post_type = 'project';

    public function get_account() {
        return Account::get_instance($this->get_field('project_account'));
    }
}

// Gateway model
class Gateway extends Post {
    protected static $post_type = 'gateway';

    public function get_api_key() {
        return $this->get_field('api_key');
    }
}

// Portfolio Item model
class Portfolio_Item extends Post {
    protected static $post_type = 'portfolio';

    public function get_gallery() {
        return $this->get_field('portfolio_gallery');
    }
}
```

#### User-Based Models

```php
// Account model (business account)
class Account extends User {
    protected static $role = 'account';

    public function get_projects() {
        return Project::query()
            ->where_meta('project_account', $this->get_id())
            ->get();
    }

    public function get_orders() {
        return wc_get_orders([
            'meta_key'   => '_account_id',
            'meta_value' => $this->get_id(),
        ]);
    }
}
```

### Usage in Application Code

```php
// In a REST route
class Project_Route extends Route {
    public function callback(WP_REST_Request $request, ?Project $project = null) {
        // $project is already the correct type
        $account = $project->get_account();  // Returns Account instance

        return [
            'project' => $project->to_array(),
            'account' => $account->to_array(),
        ];
    }
}

// In an admin table
class Projects_Table extends Posts_Table {
    protected $post_type = 'project';

    public function column_account(Project $project) {
        // Injection gives us Project, not generic Post
        $account = $project->get_account();
        return $account->get_name();
    }
}

// In a view
class Project_Card extends View {
    protected static $defaults = [
        'project' => Project::class,
    ];

    public function view() {
        // $this['project'] is Project instance
        $project = $this['project'];
        ?>
        <div class="project-card">
            <h3><?= $project->get_title() ?></h3>
            <p>Account: <?= $project->get_account()->get_name() ?></p>
        </div>
        <?php
    }
}
```

### Query Builder Integration

```php
// Get all projects (returns Project instances)
$projects = Project::query()->get();

// Get from base class with resolution
$posts = Post::query()
    ->where('post_type', 'project')
    ->get();
// Each item is resolved to Project

// Mixed query (resolution per item)
$posts = Post::query()
    ->where_in('post_type', ['project', 'document'])
    ->get();
// Returns mix of Project and Document instances
```

---

## Integration with Factory Pattern

### Factory Cache Awareness

The Factory pattern caches instances by class and ID. Class resolution ensures the correct class is used for caching:

```php
// First call - resolves to Project, caches as Project
$project1 = Post::get_instance(123);  // Project instance

// Second call - cache hit on Project class
$project2 = Post::get_instance(123);  // Same Project instance (from cache)

// Direct call - also cache hit
$project3 = Project::get_instance(123);  // Same instance
```

### Cache Key Strategy

```php
// Cache structure
Factory::$cache = [
    'Digitalis_Co\Project' => [
        123 => Project instance,
        456 => Project instance,
    ],
    'Digitalis_Co\Document' => [
        789 => Document instance,
    ],
];
```

---

## Troubleshooting

### Class Not Resolving to Expected Type

**Symptom:** `Post::get_instance(123)` returns `Post` instead of `Project`

**Causes:**
1. `validate_id()` returning false
2. `$post_type` not set correctly
3. Class not autoloaded (not in class map)
4. `$auto_resolve` disabled

**Debug:**
```php
// Check what's registered
var_dump(Model::$class_map['Digitalis\Post']);

// Check validation
var_dump(Project::validate_id(123));

// Check specificity
var_dump(Project::get_specificity());
```

### Wrong Class Selected

**Symptom:** Getting `Document` when expecting `Project`

**Causes:**
1. Both classes have same specificity
2. Validation passing for wrong class
3. Post has wrong `post_type`

**Solution:**
```php
// Check the actual post type
$post_type = get_post_type(123);
var_dump($post_type);

// Verify each class validates correctly
var_dump(Project::validate_id(123));   // Should be true
var_dump(Document::validate_id(123));  // Should be false
```

### Circular Resolution

**Symptom:** Infinite loop or maximum recursion

**Causes:**
1. `get_class_name()` calling `get_instance()` which calls `get_class_name()`
2. Custom validation triggering resolution

**Solution:**
```php
// Use $auto_resolve = false in validation
public static function validate_id($id) {
    // Don't use get_instance() here
    $post_type = get_post_type($id);  // Direct WordPress call
    return $post_type === static::$post_type;
}
```

### Performance Concerns

**Symptom:** Slow resolution with many subclasses

**Causes:**
1. Many classes registered
2. Expensive `validate_id()` implementations

**Solutions:**
```php
// 1. Use specific class when known
$project = Project::get_instance(123);  // Skip resolution

// 2. Disable resolution for bulk operations
$posts = [];
foreach ($ids as $id) {
    $posts[] = Post::get_instance($id, false);  // Skip resolution
}

// 3. Optimize validate_id()
public static function validate_id($id) {
    // Cache WordPress queries
    static $cache = [];
    if (!isset($cache[$id])) {
        $cache[$id] = get_post_type($id);
    }
    return $cache[$id] === static::$post_type;
}
```

---

## Quick Reference

### Defining a Specific Post Model

```php
class My_Post_Type extends Post {
    protected static $post_type = 'my_type';
    // Specificity: 1
}
```

### Defining a Status-Specific Model

```php
class Draft_Project extends Project {
    protected static $post_type   = 'project';
    protected static $post_status = 'draft';
    // Specificity: 11
}
```

### Defining a Term-Specific Model

```php
class Featured_Project extends Project {
    protected static $post_type = 'project';
    protected static $term      = 'featured';
    protected static $taxonomy  = 'project_tag';
    // Specificity: 101
}
```

### Defining a Role-Specific User

```php
class Account extends User {
    protected static $role = 'account';
    // Specificity: 1
}
```

### Defining a Taxonomy-Specific Term

```php
class Project_Category extends Term {
    protected static $taxonomy = 'project_category';
    // Specificity: 1
}
```

### Resolution Behavior Summary

| Call | Result |
|------|--------|
| `Post::get_instance(123)` | Most specific Post subclass |
| `Post::get_instance(123, false)` | Always `Post` |
| `Project::get_instance(123)` | `Project` or more specific |
| `User::get_instance(5)` | Most specific User subclass |
| `Term::get_instance(10)` | Most specific Term subclass |
