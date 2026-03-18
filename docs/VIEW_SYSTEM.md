# Digitalis Framework: View System

The Digitalis Framework provides a powerful view system for rendering UI components with dependency injection, parameter inheritance, and lifecycle hooks.

---

## Table of Contents

- [Overview](#overview)
- [Class Hierarchy](#class-hierarchy)
- [Static Properties](#static-properties)
- [Property Inheritance](#property-inheritance)
- [Rendering Methods](#rendering-methods)
- [Parameter Access](#parameter-access)
- [Template System](#template-system)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Validation System](#validation-system)
- [Dependency Injection](#dependency-injection)
- [Component Class](#component-class)
- [Field Classes](#field-classes)
- [Real-World Examples](#real-world-examples)
- [Best Practices](#best-practices)

---

## Overview

### The Problem

Traditional PHP templating leads to repetitive code:

```php
// Without View system
function render_invoice($order_id, $user_id) {
    $order = Order::get_instance($order_id);  // Manual resolution
    $user = User::get_instance($user_id);     // Manual resolution

    if (!$user || !$order) return;            // Manual validation
    if (!$user->can('view_invoice', $order)) return;

    include 'templates/invoice.php';          // No parameter safety
}
```

### The Solution

The View system provides declarative, self-documenting views:

```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,  // Auto-injected
        'user'  => User::class,   // Auto-injected
    ];

    protected static $required = ['order'];

    public function condition() {
        return $this['user']->can('view_invoice', $this['order']);
    }
}

// Clean usage
Invoice_View::render(['order' => 721, 'user' => 1]);
```

### Key Characteristics

- **Declarative** - Define requirements via static properties
- **Self-documenting** - Defaults show what a view needs
- **Inheritable** - Child views extend parent defaults
- **Type-safe** - Dependency injection validates types
- **Lifecycle-aware** - Hooks for before/after rendering
- **Flexible** - Use inline `view()` or external templates

---

## Class Hierarchy

```
View (abstract, ArrayAccess)
│   - Core rendering logic
│   - Parameter management
│   - Dependency injection
│   - Validation system
│
├── Component
│   │   - HTML element creation
│   │   - Attribute handling
│   │   - Element composition
│   │
│   └── Field
│       │   - Form field base
│       │   - Value handling
│       │   - Row/wrapper structure
│       │
│       ├── Input
│       ├── Textarea
│       ├── Select
│       ├── Checkbox
│       ├── Radio
│       ├── Hidden
│       ├── Button
│       ├── Date_Picker
│       └── (more field types...)
│
└── [Application Views]
    ├── Widget
    ├── Invoice_View
    ├── Document_View
    └── ...
```

---

## Static Properties

### `$defaults`

Default parameter values. Class names trigger dependency injection.

```php
protected static $defaults = [
    'title'   => 'Untitled',           // Simple default
    'columns' => 3,                     // Numeric default
    'order'   => Order::class,          // DI: resolves to Order instance
    'user'    => User::class,           // DI: resolves to User instance
    'items'   => [],                    // Array default
];
```

### `$required`

Parameters that must be present (not null) after injection.

```php
protected static $required = ['order', 'user'];
```

For DI parameters, `required()` checks if the injected value is an instance of the expected class.

### `$merge`

Parameter keys whose values should be merged (not replaced) in inheritance.

```php
protected static $merge = ['classes', 'styles', 'attr'];
```

**Example:**
```php
class Parent_View extends View {
    protected static $defaults = [
        'classes' => ['parent-class'],
    ];
    protected static $merge = ['classes'];
}

class Child_View extends Parent_View {
    protected static $defaults = [
        'classes' => ['child-class'],
    ];
}

// Child_View defaults: ['classes' => ['parent-class', 'child-class']]
```

### `$skip_inject`

Parameters that should not have dependency injection applied.

```php
protected static $defaults = [
    'order'    => Order::class,     // Will be injected
    'class_name' => Order::class,   // Just a string, not injected
];

protected static $skip_inject = ['class_name'];
```

### `$template` and `$template_path`

For template-based rendering:

```php
protected static $template = 'widgets/box-link';  // Template filename (no .php)
protected static $template_path = '/path/to/templates/';  // Directory path
```

### `$inherited_props`

Defines which properties use inheritance merging:

```php
protected static $inherited_props = [
    'defaults',
    'required',
    'merge',
    'skip_inject',
];
```

---

## Property Inheritance

### How It Works

The `Inherits_Props` trait walks up the inheritance chain, merging static properties from parent classes.

```php
class Base_View extends View {
    protected static $defaults = [
        'wrapper' => true,
        'classes' => ['base'],
    ];
    protected static $merge = ['classes'];
}

class Card_View extends Base_View {
    protected static $defaults = [
        'title'   => '',
        'classes' => ['card'],
    ];
}

class Product_Card extends Card_View {
    protected static $defaults = [
        'product' => Product::class,
        'classes' => ['product-card'],
    ];
}

// Product_Card effective defaults:
// [
//     'wrapper' => true,              // From Base_View
//     'title'   => '',                // From Card_View
//     'product' => Product::class,    // From Product_Card
//     'classes' => ['base', 'card', 'product-card'],  // Merged
// ]
```

### Merge Behavior

For keys in `$merge`, arrays are combined up the chain:

```php
// Parent has: ['classes' => ['a', 'b']]
// Child has:  ['classes' => ['c']]
// Result:     ['classes' => ['a', 'b', 'c']]
```

Non-merge keys are overwritten:

```php
// Parent has: ['title' => 'Default']
// Child has:  ['title' => 'Custom']
// Result:     ['title' => 'Custom']
```

### Accessing Inherited Props

```php
// Get merged defaults
$defaults = static::get_defaults();

// Get merged required keys
$required = static::get_required_keys();

// Get merged merge keys
$merge = static::get_merge_keys();

// Get merged skip_inject keys
$skip = static::get_skip_inject_keys();
```

---

## Rendering Methods

### Static `render()`

Factory method for one-line rendering:

```php
// Render and print
View::render(['param' => 'value']);

// Render and return HTML
$html = View::render(['param' => 'value'], false);
```

### Instance `print()`

For more control:

```php
$view = new My_View(['param' => 'value']);
$view->print();          // Print to output

$html = $view->print(true);  // Return as string
```

### `__toString()`

Enable string casting:

```php
echo new My_View(['param' => 'value']);

// Or in templates
<?= new My_View(['param' => 'value']) ?>
```

### View Index

Each view class tracks render count:

```php
$view->get_index();    // Current render index (0-based)
$view->is_first();     // True if first render of this class

// Automatic param
$this['view_index'];   // 0, 1, 2, ... for each render
```

---

## Parameter Access

### Array Access

```php
$view = new My_View(['title' => 'Hello']);

$view['title'];        // 'Hello'
$view['title'] = 'Hi'; // Set value
isset($view['title']); // true
unset($view['title']); // Remove
```

### Property Overloading

```php
$view->title;          // 'Hello'
$view->title = 'Hi';   // Set value
isset($view->title);   // true
unset($view->title);   // Remove
```

### Method Access

```php
$view->get_param('title');           // Get
$view->set_param('title', 'Hi');     // Set
$view->has_param('title');           // Check
$view->unset_param('title');         // Remove
$view->merge_param('classes', 'new'); // Merge into array
```

### The `params()` Method

Override to transform/calculate parameters at runtime:

```php
public function params(&$p) {
    // Calculate derived values
    $p['account'] = $p['order']->get_account();

    // Set conditional values
    if ($p['order']->is_paid()) {
        $p['status_class'] = 'paid';
    }

    // Call parent for inherited behavior
    parent::params($p);
}
```

---

## Template System

### Template File Approach

Define template location in view class:

```php
class Invoice extends View {
    protected static $template = 'invoice';  // templates/invoice.php
    protected static $template_path = PLUGIN_PATH . 'templates/';
}
```

Template file receives extracted parameters:

```php
<!-- templates/invoice.php -->
<div class="invoice">
    <h1><?= $title ?></h1>
    <p>Order #<?= $order->get_id() ?></p>
    <p>Customer: <?= $user->get_name() ?></p>
</div>
```

### Inline `view()` Method

For simpler views, define markup directly:

```php
class Simple_Alert extends View {
    protected static $defaults = [
        'message' => '',
        'type'    => 'info',
    ];

    public function view() {
        ?>
        <div class="alert alert-<?= $this['type'] ?>">
            <?= $this['message'] ?>
        </div>
        <?php
    }
}
```

### Choosing Between Approaches

| Use Template Files When | Use `view()` Method When |
|------------------------|-------------------------|
| Complex markup | Simple/short markup |
| Shared by multiple views | View-specific layout |
| Designer collaboration | Developer-only |
| Need raw PHP flexibility | Prefer encapsulation |

---

## Lifecycle Hooks

### Hook Order

```
print() called
    │
    ├── inject_dependencies()
    ├── params()
    ├── validate()
    │
    ├── [If first render] before_first()
    ├── before()
    │
    ├── [Template or view()]
    │
    ├── [If first render] after_first()
    └── after()
```

### `before_first()` and `after_first()`

Called only on the first render of a view class:

```php
public function before_first() {
    // Load CSS/JS once
    wp_enqueue_style('my-view-styles');
    wp_enqueue_script('my-view-scripts');
}

public function after_first() {
    // Output one-time initialization
    echo '<script>MyView.init();</script>';
}
```

### `before()` and `after()`

Called on every render:

```php
class Widget extends View {
    public function before() {
        echo "<div class='widget'>";
    }

    public function after() {
        echo "</div>";
    }
}
```

### Common Patterns

**Wrapper Element:**
```php
public function before() {
    echo "<{$this['tag']} {$this['attributes']}>";
}

public function after() {
    echo "</{$this['tag']}>";
}
```

**Conditional Wrapper:**
```php
public function before() {
    if ($this['wrap']) {
        echo '<div class="wrapper">';
    }
}

public function after() {
    if ($this['wrap']) {
        echo '</div>';
    }
}
```

---

## Validation System

### `validate()` Method

Orchestrates all validation checks:

```php
public function validate() {
    if (!$this->required())   return false;
    if (!$this->permission()) return false;
    if (!$this->condition())  return false;
    return true;
}
```

If `validate()` returns false, nothing is rendered.

### `required()` Method

Checks that required parameters are present:

```php
protected static $required = ['order', 'user'];

public function required() {
    // For each required key:
    // - If default is a class name, checks instanceof
    // - Otherwise, checks not null

    // Override for custom logic:
    if (!$this['order'] instanceof Order) return false;
    return parent::required();
}
```

### `permission()` Method

For access control validation:

```php
public function permission() {
    $user = User::current();
    return $user && $user->can('view_invoices');
}
```

### `condition()` Method

For arbitrary conditional rendering:

```php
public function condition() {
    // Don't render for draft orders
    if ($this['order']->get_status() === 'draft') return false;

    // Check user can view this specific order
    if (!$this['user']->can('view_invoice', $this['order'])) return false;

    return true;
}
```

### Complete Example

```php
class Invoice_View extends View {
    protected static $defaults = [
        'order' => Order::class,
        'user'  => User::class,
    ];

    protected static $required = ['order', 'user'];

    public function permission() {
        return $this['user']->has_capability('view_invoices');
    }

    public function condition() {
        // Order must belong to user's account
        $account = $this['user']->get_account();
        return $this['order']->get_account_id() === $account->get_id();
    }
}
```

---

## Dependency Injection

### How It Works

When a default value is a class name with a `get_instance()` method, the passed value is treated as an identifier:

```php
protected static $defaults = [
    'order' => Order::class,
];

// When you call:
new My_View(['order' => 721]);

// The system does:
$params['order'] = Order::get_instance(721);
```

### Integration with View Lifecycle

```
new View(['order' => 721])
    │
    ├── set_params(defaults)     // order = Order::class
    ├── merge_params(params)     // order = 721
    │
    └── print()
        ├── inject_dependencies() // order = Order::get_instance(721)
        ├── params()              // Transform/calculate
        └── [render]
```

### Skipping Injection

Use `$skip_inject` for class names that should remain as strings:

```php
protected static $defaults = [
    'model'      => Order::class,     // Injected
    'model_class' => Order::class,    // Just a string
];

protected static $skip_inject = ['model_class'];
```

### Nullable Injection

For optional model parameters:

```php
protected static $defaults = [
    'order' => Order::class,
    'user'  => User::class,
];

public function condition() {
    // Handle case where order wasn't found
    if (!$this['order'] instanceof Order) return false;
    return true;
}
```

---

## Component Class

The `Component` class extends `View` with HTML element handling through the `Element` and `Attributes` classes.

### Class Relationship

```
View
 └── Component
      │
      ├── uses Element (for HTML structure)
      │    └── uses Attributes (for HTML attributes)
      │
      └── Field (extends Component)
           └── Input, Select, Checkbox, etc.
```

### Element System

Components can define named elements:

```php
protected static $elements = ['header', 'body', 'footer'];

protected static $defaults = [
    'header_tag'     => 'header',
    'header_classes' => ['component-header'],
    'body_tag'       => 'div',
    'body_classes'   => ['component-body'],
    'footer_tag'     => 'footer',
    'footer_classes' => ['component-footer'],
];
```

### Element Creation

The `params()` method creates `Element` objects:

```php
public function params(&$p) {
    // Creates $this['element'] (main element)
    $this->create_element();

    // Creates $this['header'], $this['body'], etc.
    foreach (static::get_elements() as $element) {
        $this->create_element($element);
    }
}
```

### Using Elements

```php
public function view() {
    echo $this['header']->open();
    echo $this['title'];
    echo $this['header']->close();

    echo $this['body']->open();
    echo $this['content'];
    echo $this['body']->close();
}
```

### Attribute Handling

Components support attribute arrays that get merged:

```php
protected static $merge = ['attr', 'attributes', 'class', 'classes', 'style', 'styles'];

// Usage
new My_Component([
    'classes' => ['custom-class'],
    'styles'  => ['color' => 'red'],
    'attr'    => ['data-id' => '123'],
]);
```

---

## Attributes Class

The `Attributes` class manages HTML attributes with automatic escaping, type normalization, and fluent methods.

### Core Features

| Feature | Description |
|---------|-------------|
| **ArrayAccess** | Access attributes via `$attrs['name']` |
| **Auto-escaping** | Values escaped via `htmlspecialchars()` |
| **Type normalization** | Arrays converted to appropriate formats |
| **Output caching** | String output cached for performance |

### Value Normalization

Different attribute types are automatically converted:

```php
$attrs = new Attributes([
    'class' => ['btn', 'btn-primary'],           // → "btn btn-primary"
    'style' => ['color' => 'red', 'margin' => 0], // → "color: red; margin: 0"
    'data-config' => ['key' => 'value'],          // → JSON encoded
    'disabled' => true,                           // → "disabled"
]);
```

**Normalization Rules:**
| Attribute | Array Input | Output |
|-----------|-------------|--------|
| `class` | `['a', 'b']` | `"a b"` (space-separated) |
| `style` | `['color' => 'red']` | `"color: red;"` (CSS format) |
| `data-*` | `['key' => 'val']` | `"{\"key\":\"val\"}"` (JSON) |
| Other | `['a', 'b']` | `"a b"` (space-separated) |

### Fluent Methods

```php
$attrs = new Attributes(['class' => 'base']);

// Add classes
$attrs->add_class('new-class');
$attrs->add_class('a', 'b', 'c');  // Multiple at once

// Add styles
$attrs->add_style('color', 'red');
$attrs->add_style(['margin' => '10px', 'padding' => '5px']);

// Add data attributes
$attrs->add_data('id', 123);        // data-id="123"
$attrs->add_data('config', ['a' => 1]); // data-config='{"a":1}'

// Set ID
$attrs->set_id('my-element');
```

### Output Methods

```php
// As attribute string (for HTML tag)
echo "<div{$attrs}>";  // __toString includes leading space

// As array
$array = $attrs->get_attrs();

// Specific attribute
$class = $attrs['class'];
$id = $attrs->get_id();
```

### Cache Invalidation

The string output is cached. Cache clears automatically when attributes change:

```php
$attrs = new Attributes(['class' => 'a']);
echo $attrs;  // Cached: " class='a'"

$attrs['class'] = 'b';  // Cache cleared
echo $attrs;  // Recalculated: " class='b'"
```

---

## Element Class

The `Element` class represents an HTML element with tag, content, and attributes.

### Core Features

| Feature | Description |
|---------|-------------|
| **ArrayAccess** | Access attributes via `$el['name']` |
| **Property overloading** | Access attributes via `$el->name` |
| **Method proxying** | Attribute methods available on Element |
| **Void tag handling** | Self-closing tags handled automatically |

### Creating Elements

```php
// Basic element
$el = new Element('div');

// With content
$el = new Element('p', 'Hello World');

// With attributes
$el = new Element('a', 'Click me', [
    'href' => '/path',
    'class' => ['btn', 'btn-primary'],
]);

// With Attributes object
$attrs = new Attributes(['class' => 'container']);
$el = new Element('div', '', $attrs);
```

### Rendering

```php
$el = new Element('div', 'Content', ['class' => 'box']);

// Full element
echo $el;  // <div class='box'>Content</div>

// Opening tag only
echo $el->open();  // <div class='box'>

// Closing tag only
echo $el->close(); // </div>
```

### Void Tags

Self-closing tags are handled automatically:

```php
$img = new Element('img', '', ['src' => 'photo.jpg', 'alt' => 'Photo']);
echo $img;  // <img src='photo.jpg' alt='Photo'>

$br = new Element('br');
echo $br;  // <br>
echo $br->close();  // "" (empty string for void tags)
```

**Void tags:** `area`, `base`, `br`, `col`, `embed`, `hr`, `img`, `input`, `keygen`, `link`, `meta`, `param`, `source`, `track`, `wbr`

### Attribute Access

Elements proxy to their Attributes object:

```php
$el = new Element('div', '', ['class' => 'box']);

// ArrayAccess
$el['class'];            // 'box'
$el['id'] = 'my-div';    // Set attribute
isset($el['class']);     // true

// Property overloading
$el->class;              // 'box'
$el->id = 'my-div';      // Set attribute

// Method proxying (calls Attributes methods)
$el->add_class('new');
$el->add_style('color', 'red');
$el->add_data('id', 123);
$el->set_id('unique');

// Get Attributes object
$attrs = $el->get_attributes();
```

### Modifying Content

```php
$el = new Element('p', 'Initial');

$el->set_content('New content');
$el->set_tag('div');

// Content can include other elements
$inner = new Element('strong', 'Bold');
$el->set_content("Text with {$inner} word");
```

### Practical Example

```php
class Card_Component extends Component {
    protected static $elements = ['header', 'body'];

    protected static $defaults = [
        'tag'     => 'article',
        'classes' => ['card'],
        'title'   => '',
        'content' => '',
        'header_tag'     => 'header',
        'header_classes' => ['card-header'],
        'body_tag'       => 'div',
        'body_classes'   => ['card-body'],
    ];

    public function view() {
        // $this['element'], $this['header'], $this['body'] are Element objects

        echo $this['element']->open();

        echo $this['header']->open();
        echo "<h3>{$this['title']}</h3>";
        echo $this['header']->close();

        echo $this['body']->open();
        echo $this['content'];
        echo $this['body']->close();

        echo $this['element']->close();
    }
}

// Usage
echo new Card_Component([
    'title'   => 'Welcome',
    'content' => '<p>Card body content</p>',
    'classes' => ['card', 'card-featured'],  // Merged with defaults
]);

// Output:
// <article class='card card-featured'>
//     <header class='card-header'>
//         <h3>Welcome</h3>
//     </header>
//     <div class='card-body'>
//         <p>Card body content</p>
//     </div>
// </article>
```

### Component Element Flow

```
Component instantiation
    │
    ├── params() called
    │   ├── create_element()           → $this['element']
    │   └── create_element('header')   → $this['header']
    │       └── create_element('body') → $this['body']
    │
    │   Each create_element():
    │   ├── Gets tag from $params['{name}_tag']
    │   ├── Gets classes from $params['{name}_classes']
    │   ├── Gets styles from $params['{name}_styles']
    │   ├── Gets attrs from $params['{name}_attr']
    │   └── Creates Element object with Attributes
    │
    └── view() / template renders using Element objects
```

---

## Field Classes

Fields extend Component for form inputs.

### Basic Usage

```php
use Digitalis\Field\Input;
use Digitalis\Field\Select;

echo new Input([
    'name'        => 'email',
    'label'       => 'Email Address',
    'placeholder' => 'you@example.com',
    'required'    => true,
]);

echo new Select([
    'name'    => 'country',
    'label'   => 'Country',
    'options' => [
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
    ],
    'default' => 'us',
]);
```

### Field Structure

Fields render with row/wrapper structure:

```html
<div class="row field-row row-email">
    <label for="email-field">Email Address</label>
    <div class="field-wrap">
        <input type="text" name="email" id="email-field" class="digitalis-field field field-input">
    </div>
</div>
```

### Available Fields

| Class | Description |
|-------|-------------|
| `Input` | Text input |
| `Textarea` | Multiline text |
| `Select` | Dropdown select |
| `Select_Nice` | Enhanced select with search |
| `Checkbox` | Single checkbox |
| `Checkbox_Group` | Multiple checkboxes |
| `Radio` | Radio buttons |
| `Hidden` | Hidden input |
| `Button` | Button element |
| `Submit` | Submit button |
| `Date` | Date input |
| `Date_Picker` | Enhanced date picker |
| `Date_Range` | Date range picker |
| `Number` | Numeric input |
| `Range` | Range slider |
| `File` | File upload |

### Creating Custom Fields

```php
class Price_Field extends \Digitalis\Field\Input {
    protected static $defaults = [
        'prefix' => '$',
        'classes' => ['price-field'],
    ];

    public function before() {
        parent::before();
        echo "<span class='prefix'>{$this['prefix']}</span>";
    }
}
```

---

## Real-World Examples

### Widget with Template

```php
// View class
class Box_Link extends Widget {
    protected static $template = 'widgets/box-link';

    protected static $defaults = [
        'slug'     => 'box-link',
        'icon'     => 'box-iso',
        'label'    => 'Box Link',
        'href'     => '#',
        'tag'      => 'a',
        'progress' => null,
    ];
}

// Template: templates/widgets/box-link.php
<h4 class="label"><?= $label ?></h4>
<?php if ($icon): ?>
    <?php if (!is_null($progress)): ?>
        <div class="progress" style="--progress: <?= $progress ?>">
            <div class="progress-value"><?= floor($progress * 100) ?>%</div>
        </div>
    <?php else: ?>
        <i class='icon iconoir-<?= $icon ?>'></i>
    <?php endif; ?>
<?php endif; ?>
```

### View with Inline Markup

```php
class Account_Switcher extends View {
    public function condition() {
        return User::inst()->has_multiple_accounts();
    }

    public function view() {
        $user     = User::inst();
        $accounts = $user->get_accounts();

        $options = [];
        foreach ($accounts as $acc) {
            $options[$acc->get_id()] = $acc->get_name();
        }

        echo "<form method='post' class='account-switcher'>";
        echo new \Digitalis\Field\Hidden([
            'name'  => 'nonce',
            'value' => wp_create_nonce('switch-account'),
        ]);
        echo new Nav_Selector([
            'options' => $options,
            'default' => $user->get_account()->get_id(),
        ]);
        echo "</form>";
    }
}
```

### Inheritance Chain

```php
// Base view with common defaults
abstract class Document_View extends View {
    protected static $defaults = [
        'document' => Document::class,
        'user'     => User::class,
    ];

    public function params(&$p) {
        if ($p['document'] instanceof Document) {
            $p['account'] = $p['document']->get_account();
        }
    }

    public function condition() {
        if (!($this->document instanceof Document)) return false;
        if (!($this->user instanceof User)) return false;
        return $this->user->can('view_document', $this['document']);
    }
}

// Specific document view
class Document_Page extends Document_View {
    protected static $template = 'document-page';

    protected static $defaults = [
        'show_actions' => true,
    ];
}

// Another specific view
class Document_Renderer extends Document_View {
    protected static $template = 'document';

    protected static $defaults = [
        'print_mode' => false,
    ];
}
```

---

## Best Practices

### 1. Use Semantic Defaults

```php
// Good - self-documenting
protected static $defaults = [
    'order'      => Order::class,
    'show_total' => true,
    'columns'    => 3,
];

// Avoid - unclear types
protected static $defaults = [
    'o'    => null,
    'st'   => 1,
    'cols' => 3,
];
```

### 2. Validate Early

```php
public function condition() {
    // Check prerequisites before any rendering
    if (!$this['order'] instanceof Order) return false;
    if (!$this['order']->is_visible()) return false;
    return true;
}
```

### 3. Use `params()` for Derived Values

```php
public function params(&$p) {
    // Calculate once, use everywhere
    $p['is_paid'] = $p['order']->is_paid();
    $p['total']   = $p['order']->get_formatted_total();
    $p['items']   = $p['order']->get_items();

    parent::params($p);
}
```

### 4. Leverage Inheritance

```php
// Base styles in parent
class Widget extends Component {
    protected static $defaults = [
        'classes' => ['widget'],
    ];
    protected static $merge = ['classes'];
}

// Specific widgets add classes
class Stats_Widget extends Widget {
    protected static $defaults = [
        'classes' => ['stats-widget'],  // ['widget', 'stats-widget']
    ];
}
```

### 5. Separate Logic from Markup

```php
// Calculate in params()
public function params(&$p) {
    $p['can_edit'] = $p['user']->can('edit', $p['order']);
    $p['status_class'] = 'status-' . $p['order']->get_status();
}

// Template stays clean
// <?php if ($can_edit): ?>
//     <button>Edit</button>
// <?php endif; ?>
```

### 6. Use `before_first()` for Assets

```php
public function before_first() {
    wp_enqueue_style('my-component');
    wp_enqueue_script('my-component');
}
```

---

## Quick Reference

### View Creation

```php
// Static factory (print)
View::render(['param' => 'value']);

// Static factory (return)
$html = View::render(['param' => 'value'], false);

// Instance
$view = new My_View(['param' => 'value']);
$view->print();
$html = $view->print(true);

// String cast
echo new My_View(['param' => 'value']);
```

### Static Properties

```php
protected static $defaults = [];      // Default params
protected static $required = [];      // Required keys
protected static $merge = [];         // Keys to merge in inheritance
protected static $skip_inject = [];   // Skip DI for these keys
protected static $template = null;    // Template filename
protected static $template_path = ''; // Template directory
```

### Lifecycle Methods

```php
public function params(&$p) {}        // Transform params
public function validate() {}         // Return bool
public function required() {}         // Check required params
public function permission() {}       // Access control
public function condition() {}        // Arbitrary conditions
public function before_first() {}     // First render only
public function before() {}           // Every render (before content)
public function view() {}             // Inline markup
public function after_first() {}      // First render only
public function after() {}            // Every render (after content)
```

### Parameter Access

```php
$view['key'];                // Get (ArrayAccess)
$view->key;                  // Get (property)
$view->get_param('key');     // Get (method)
$view['key'] = 'value';      // Set
$view->set_param('key', 'v'); // Set
$view->has_param('key');     // Check
$view->merge_param('k', $v); // Merge into array
$view->get_index();          // Render index
$view->is_first();           // First render?
```
