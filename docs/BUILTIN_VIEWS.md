# Digitalis Framework: Built-in Views Reference

Complete documentation of all views, components, and fields shipped with the Digitalis Framework.

---

## Table of Contents

- [Overview](#overview)
- [Core Views](#core-views)
  - [Debug](#debug)
  - [Archive](#archive)
  - [Iterator_UI](#iterator_ui)
  - [Query_Filters](#query_filters)
- [Components](#components)
  - [Component (Base)](#component-base)
  - [Field_Group](#field_group)
  - [Form](#form)
  - [HTMX](#htmx)
  - [Link](#link)
  - [Table](#table)
  - [Menu](#menu)
  - [Menu_Item](#menu_item)
- [Fields](#fields)
  - [Field (Base)](#field-base)
  - [Input](#input)
  - [Textarea](#textarea)
  - [Select](#select)
  - [Select_Nice](#select_nice)
  - [Checkbox](#checkbox)
  - [Checkbox_Group](#checkbox_group)
  - [Checkbox_Buttons](#checkbox_buttons)
  - [Radio](#radio)
  - [Radio_Buttons](#radio_buttons)
  - [Hidden](#hidden)
  - [Hidden_Group](#hidden_group)
  - [Button](#button)
  - [Submit](#submit)
  - [Number](#number)
  - [Range](#range)
  - [File](#file)
  - [Date](#date)
  - [Date_Picker](#date_picker)
  - [Date_Range](#date_range)
- [Class Hierarchy](#class-hierarchy)

---

## Overview

The framework provides a comprehensive library of pre-built views organized into three categories:

| Category | Purpose | Examples |
|----------|---------|----------|
| **Core Views** | Debugging, archives, iterators | `Debug`, `Archive`, `Iterator_UI` |
| **Components** | Reusable UI elements | `Table`, `Menu`, `Form`, `HTMX` |
| **Fields** | Form inputs | `Input`, `Select`, `Checkbox`, `Date_Picker` |

All built-in views follow the same patterns documented in [VIEW_SYSTEM.md](./VIEW_SYSTEM.md).

---

## Core Views

### Debug

**Namespace:** `Digitalis\Debug`
**File:** `include/views/debug.view.php`

A powerful debugging view that displays variables in a collapsible debugger panel. Only visible to administrators.

#### Features
- Multiple output modes: debugger panel, inline `<pre>`, or browser console
- Automatic variable name extraction from source code
- Backtrace display with file/line information
- Collapsible nested data structures
- `print_r`, `var_export`, or `var_dump` formatting

#### Global Helper Functions

```php
// Display in debugger panel
dump($variable, $another);

// Append to existing debugger (don't create new panel)
damp($variable);

// Dump and die
dd($variable);

// Inline print_r output
dprint($variable);

// Use var_export formatting
dexp($variable);

// Output to browser console
js_log($variable);
```

#### Direct Usage

```php
use Digitalis\Debug;
use Digitalis\Debug_Options;

// Basic usage
Debug::write($user, $order, $items);

// With options
Debug::write($data, new Debug_Options([
    'view'   => 'debugger',  // 'debugger', 'inline', 'js'
    'expand' => 'print_r',   // 'print_r', 'var_export', 'var_dump'
    'title'  => 'My Debug',
    'open'   => true,        // Start expanded
    'die'    => false,       // Die after output
]));
```

#### Use Cases
- Development debugging
- Inspecting complex data structures
- Tracing execution flow
- Quick variable inspection without IDE debugger

---

### Debug_Code_Block

**Namespace:** `Digitalis\Debug_Code_Block`
**File:** `include/views/debug-code-block.view.php`

A sub-component used by Debug to render individual code blocks in the debugger panel.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `label` | `string\|false` | `false` | Label for the code block |
| `code` | `string` | `''` | The code/data to display |

---

### Archive

**Namespace:** `Digitalis\Archive`
**File:** `include/views/archive.view.php`

Abstract base class for rendering paginated lists of items with filtering controls.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | `string` | `'digitalis-archive'` | Archive container ID |
| `classes` | `array` | `['digitalis-archive']` | CSS classes |
| `query_vars` | `array` | `[]` | Query arguments |
| `skip_main` | `bool` | `false` | Skip main WordPress query |
| `items_only` | `bool` | `false` | Render only items (no wrapper) |
| `items` | `array\|null` | `null` | Pre-fetched items |
| `no_items` | `string\|false` | `'No items found.'` | Empty state message |
| `pagination` | `bool` | `true` | Show pagination |
| `paginate_args` | `array` | `[]` | Pagination arguments |
| `loader` | `string` | `'sliding-dots.gif'` | Loading indicator |
| `loader_type` | `string` | `'image'` | Loader type: image, file, callback, html |
| `controls` | `array` | `[]` | Filter controls (fields) |
| `item_model` | `string\|null` | `null` | Model class for items |

#### Extending Archive

```php
class Product_Archive extends Post_Archive {
    protected static $defaults = [
        'item_model' => Product::class,
        'no_items'   => 'No products found.',
    ];

    public function render_item($product, $index) {
        echo new Product_Card(['product' => $product]);
    }
}

// Usage
Product_Archive::render([
    'query_vars' => [
        'post_type'      => 'product',
        'posts_per_page' => 12,
    ],
]);
```

#### Overridable Methods

| Method | Purpose |
|--------|---------|
| `get_items($query_vars, &$query, $skip_main)` | Fetch items |
| `before_items()` | Before item loop |
| `render_items($items)` | Render all items |
| `render_item($item, $i)` | Render single item |
| `after_items()` | After item loop |
| `render_no_items()` | Empty state |
| `get_page_links($query)` | Generate pagination |
| `render_pagination($page_links)` | Render pagination |
| `get_controls()` | Return filter fields |
| `get_loader()` | Return loader HTML |

---

### Post_Archive

**Namespace:** `Digitalis\Post_Archive`
**File:** `include/views/post-archive.archive.php`

Archive implementation for WordPress posts with WP_Query pagination.

#### Parameters

Inherits from Archive, plus:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `item_model` | `string` | `Post::class` | Model class |
| `no_items` | `string` | `'No posts found.'` | Empty message |

#### Usage

```php
Post_Archive::render([
    'query_vars' => [
        'post_type'      => 'project',
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ],
]);
```

---

### Term_Archive

**Namespace:** `Digitalis\Term_Archive`
**File:** `include/views/term-archive.archive.php`

Abstract archive implementation for WordPress terms.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `item_model` | `string` | `Term::class` | Model class |
| `no_items` | `string` | `'No terms found.'` | Empty message |
| `pagination` | `bool` | `false` | Pagination (limited support) |

---

### Iterator_UI

**Namespace:** `Digitalis\Iterator_UI`
**File:** `include/views/iterator-ui.view.php`

Renders progress UI for batch processing iterators.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `iterator` | `Iterator\|null` | `null` | Iterator instance |

#### Usage

```php
$iterator = new CSV_Import_Iterator($file);

// During iteration
Iterator_UI::render(['iterator' => $iterator]);

// Shows progress: "Processing 45 of 1000..."
```

#### Use Cases
- CSV imports with progress
- Batch post updates
- Data migration scripts
- Long-running admin operations

---

### Query_Filters

**Namespace:** `Digitalis\Query_Filters`
**File:** `include/views/query-filters.view.php`

Abstract field group for AJAX-powered archive filtering.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `archive_id` | `string` | `'digitalis-archive'` | Target archive ID |
| `selectors` | `array` | (auto-generated) | DOM selectors |
| `module_url` | `string` | (framework URL) | Query JS module |
| `action` | `string` | `'query_[post_type]'` | AJAX action |
| `js_params_object` | `string` | `'query_params'` | JS params variable |
| `fields` | `array` | `[]` | Filter fields |

#### Usage

```php
class Product_Filters extends Query_Filters {
    protected static $defaults = [
        'archive_id' => 'product-archive',
        'action'     => 'query_products',
    ];

    public function get_fields() {
        return [
            [
                'field'   => Select::class,
                'name'    => 'category',
                'label'   => 'Category',
                'options' => $this->get_category_options(),
            ],
            [
                'field' => Input::class,
                'name'  => 'search',
                'label' => 'Search',
            ],
        ];
    }
}
```

---

## Components

### Component (Base)

**Namespace:** `Digitalis\Component`
**File:** `include/views/component.view.php`

Base class for HTML components with element and attribute handling.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tag` | `string` | `'div'` | HTML tag |
| `id` | `string\|null` | `null` | Element ID |
| `class` | `array` | `[]` | CSS classes |
| `style` | `array` | `[]` | Inline styles |
| `attr` | `array` | `[]` | HTML attributes |
| `href` | `string\|null` | `null` | Link href (for `<a>` tag) |
| `content` | `string` | `''` | Inner content |

#### Element System

Components can define named elements:

```php
class Card extends Component {
    protected static $elements = ['header', 'body', 'footer'];

    protected static $defaults = [
        'header_tag'     => 'header',
        'header_classes' => ['card-header'],
        'body_tag'       => 'div',
        'body_classes'   => ['card-body'],
    ];
}
```

---

### Field_Group

**Namespace:** `Digitalis\Field_Group`
**File:** `include/views/components/field-group.component.php`

Renders a group of form fields together.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fields` | `array` | `[]` | Field definitions |
| `defaults` | `array` | `[]` | Default values by field name |
| `label` | `string\|false` | `false` | Group label |
| `id` | `string\|false` | `false` | Group ID |
| `tag` | `string` | `'div'` | Wrapper tag |
| `condition` | `array\|null` | `null` | Conditional display |
| `classes` | `array` | `['digitalis-field-group', 'field-group']` | CSS classes |

#### Usage

```php
Field_Group::render([
    'id'     => 'user-settings',
    'fields' => [
        [
            'field' => Input::class,
            'name'  => 'first_name',
            'label' => 'First Name',
        ],
        [
            'field' => Input::class,
            'name'  => 'last_name',
            'label' => 'Last Name',
        ],
        [
            'field'   => Select::class,
            'name'    => 'role',
            'label'   => 'Role',
            'options' => ['admin' => 'Admin', 'user' => 'User'],
        ],
    ],
    'defaults' => [
        'first_name' => 'John',
        'role'       => 'user',
    ],
]);
```

#### Field Definition Format

```php
[
    'field'      => Input::class,  // Field class (default: Input)
    'name'       => 'field_name',   // Field name attribute
    'label'      => 'Field Label',  // Label text
    'default'    => '',             // Default value
    'options'    => [],             // For select/radio/checkbox
    // ... any other field parameters
]
```

---

### Form

**Namespace:** `Digitalis\Form`
**File:** `include/views/components/form.field-group.php`

Field group with `<form>` tag and form attributes.

#### Parameters

Inherits from Field_Group, plus:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tag` | `string` | `'form'` | Always form |
| `method` | `string\|null` | `null` | Form method |
| `action` | `string\|null` | `null` | Form action URL |

#### Usage

```php
Form::render([
    'id'     => 'contact-form',
    'method' => 'post',
    'action' => admin_url('admin-post.php'),
    'fields' => [
        ['name' => 'action', 'field' => Hidden::class, 'value' => 'submit_contact'],
        ['name' => 'name', 'label' => 'Name'],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
        ['name' => 'message', 'field' => Textarea::class, 'label' => 'Message'],
        ['field' => Submit::class, 'text' => 'Send Message'],
    ],
]);
```

---

### HTMX

**Namespace:** `Digitalis\Component\HTMX`
**File:** `include/views/components/htmx.component.php`

Component pre-configured for HTMX interactions.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tag` | `string` | `'a'` | HTML tag |
| `content` | `string` | `''` | Inner content |
| `url` | `string\|null` | `null` | Request URL |
| `method` | `string` | `'get'` | HTTP method |
| `trigger` | `string` | `'click'` | Event trigger |
| `target` | `string` | `'body'` | Response target |
| `swap` | `string` | `'innerHTML'` | Swap strategy |
| `swap_oob` | `string\|null` | `null` | Out-of-band swap |
| `select` | `string\|null` | `null` | Select from response |
| `select_oob` | `string\|null` | `null` | OOB select |
| `vals` | `string\|null` | `null` | Additional values |
| `push_url` | `string\|null` | `null` | Push URL to history |
| `confirm` | `string\|null` | `null` | Confirmation message |

#### Usage

```php
use Digitalis\Component\HTMX;

HTMX::render([
    'content' => 'Load More',
    'url'     => '/api/posts?page=2',
    'target'  => '#posts-container',
    'swap'    => 'beforeend',
    'classes' => ['load-more-btn'],
]);

// Outputs:
// <a href="#" hx-get="/api/posts?page=2" hx-trigger="click"
//    hx-target="#posts-container" hx-swap="beforeend"
//    class="load-more-btn">Load More</a>
```

#### Use Cases
- AJAX content loading
- Form submissions without page reload
- Infinite scroll
- Dynamic UI updates
- SPA-like interactions

---

### Link

**Namespace:** `Digitalis\Component\Link`
**File:** `include/views/components/link.component.php`

Simple link component.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `tag` | `string` | `'a'` | Always anchor |
| `href` | `string` | `'#'` | Link URL |

#### Usage

```php
use Digitalis\Component\Link;

Link::render([
    'href'    => '/products',
    'content' => 'View Products',
    'classes' => ['nav-link'],
]);
```

---

### Table

**Namespace:** `Digitalis\Component\Table`
**File:** `include/views/components/table.component.php`

Data table with automatic header/attribute handling.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `rows` | `array` | `[]` | Table data (2D array) |
| `first_row` | `bool` | `true` | First row is header |
| `first_col` | `bool` | `false` | First column is header |
| `last_col` | `bool` | `false` | Last column is header |
| `last_row` | `bool` | `false` | Last row is footer |
| `data_labels` | `bool\|string` | `false` | Add data-label attributes |
| `data_titles` | `bool\|string` | `false` | Add data-title attributes |
| `row_classes` | `array` | `[]` | Classes per row |
| `row_atts` | `array` | `[]` | Attributes per row |
| `col_classes` | `array` | `[]` | Classes per column |
| `col_atts` | `array` | `[]` | Attributes per column |
| `cell_atts` | `array` | `[]` | Attributes per cell |

#### Usage

```php
use Digitalis\Component\Table;

Table::render([
    'rows' => [
        ['Name', 'Email', 'Role'],           // Header row
        ['John', 'john@example.com', 'Admin'],
        ['Jane', 'jane@example.com', 'User'],
    ],
    'first_row'   => true,
    'data_labels' => true,  // For responsive tables
    'classes'     => ['data-table'],
]);
```

#### Programmatic Row Building

```php
$table = new Table(['first_row' => true]);

$table->add_row(['Product', 'Price', 'Quantity']);
$table->add_row(['Widget', '$10.00', '5'], ['highlight']);
$table->add_row(['Gadget', '$25.00', '3']);

$table->print();
```

#### Use Cases
- Data display tables
- Pricing tables
- Comparison tables
- Admin list tables
- Responsive data grids

---

### Menu

**Namespace:** `Digitalis\Menu`
**File:** `include/views/components/menu.component.php`

Accessible navigation menu with mobile support.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | `string` | `'digitalis-menu'` | Menu ID |
| `items` | `array` | `[]` | Menu items |
| `aria_label` | `string` | `'Menu'` | Accessibility label |
| `role` | `string` | `'menubar'` | ARIA role |
| `direction` | `string` | `'row'` | Flex direction |
| `mobile` | `bool` | `true` | Enable mobile menu |
| `breakpoint` | `string` | `'1000px'` | Mobile breakpoint |
| `hamburger_params` | `array` | (defaults) | Hamburger button config |
| `mobile_menu_params` | `array` | (defaults) | Mobile menu config |
| `mobile_item_params` | `array` | (defaults) | Mobile item config |
| `menu_item_class` | `string` | `Menu_Item::class` | Item component class |

#### Usage

```php
Menu::render([
    'id'    => 'main-nav',
    'items' => [
        ['text' => 'Home', 'url' => '/'],
        ['text' => 'Products', 'url' => '/products', 'child' => [
            'items' => [
                ['text' => 'Widgets', 'url' => '/products/widgets'],
                ['text' => 'Gadgets', 'url' => '/products/gadgets'],
            ],
        ]],
        ['text' => 'About', 'url' => '/about'],
        ['text' => 'Contact', 'url' => '/contact'],
    ],
    'breakpoint' => '768px',
]);
```

#### Item Definition

```php
[
    'text'     => 'Menu Item',     // Display text
    'url'      => '/path',         // Link URL (null for non-link)
    'child'    => [                // Submenu (optional)
        'items' => [...],
    ],
    'position' => 'static',        // Submenu position
    'triggers' => ['click', 'hover', 'keys'],
]
```

#### Use Cases
- Site navigation
- Admin menus
- Dropdown menus
- Mobile hamburger menus
- Mega menus

---

### Menu_Item

**Namespace:** `Digitalis\Menu_Item`
**File:** `include/views/components/menu-item.component.php`

Individual menu item with submenu support.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `text` | `string` | `'Menu Item'` | Item text |
| `url` | `string\|null` | `null` | Item URL |
| `child` | `array\|View\|null` | `null` | Submenu |
| `position` | `string` | `'static'` | Submenu position |
| `aria_label` | `string\|null` | `null` | Accessibility label |
| `role` | `string` | `'menuitem'` | ARIA role |
| `triggers` | `array` | `['click', 'hover', 'keys']` | Open triggers |
| `in_delay` | `int` | `0` | Open delay (ms) |
| `out_delay` | `int` | `250` | Close delay (ms) |
| `close_button` | `bool\|null` | `null` | Show close button |

#### Position Options

| Position | Description |
|----------|-------------|
| `static` | Normal flow |
| `relative` | Relative to parent |
| `absolute` | Absolute position |
| `over` | Overlay parent |
| `full-screen` | Full screen overlay |
| `left-screen` | Slide from left |
| `block-below` | Block below trigger |
| `block-above` | Block above trigger |

---

## Fields

### Field (Base)

**Namespace:** `Digitalis\Field`
**File:** `include/views/field.view.php`

Base class for all form fields.

#### Common Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | `string\|null` | `null` | Field name attribute |
| `id` | `string\|null` | `null` | Field ID (auto-generated) |
| `label` | `string\|false` | `false` | Field label |
| `default` | `mixed` | `''` | Default value |
| `value` | `mixed\|null` | `null` | Current value |
| `placeholder` | `string\|false` | `false` | Placeholder text |
| `required` | `bool\|null` | `null` | Required field |
| `disabled` | `bool\|null` | `null` | Disabled state |
| `readonly` | `bool\|null` | `null` | Read-only state |
| `classes` | `array` | `['digitalis-field', 'field']` | Field classes |
| `attributes` | `array` | `[]` | HTML attributes |
| `wrap` | `bool` | `true` | Wrap in row/wrapper |
| `width` | `number` | `1` | Flex width ratio |
| `condition` | `array\|null` | `null` | Conditional display |

#### Field Structure

```html
<div class="row field-row row-{name}">
    <label for="{id}">{label}</label>
    <div class="field-wrap">
        <input ...field attributes... />
    </div>
</div>
```

---

### Input

**Namespace:** `Digitalis\Field\Input`
**File:** `include/views/fields/input.field.php`

Standard text input field.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'text'` | Input type |
| `maxlength` | `int\|null` | `null` | Max length |
| `minlength` | `int\|null` | `null` | Min length |

#### Usage

```php
use Digitalis\Field\Input;

Input::render([
    'name'        => 'email',
    'label'       => 'Email Address',
    'type'        => 'email',
    'placeholder' => 'you@example.com',
    'required'    => true,
]);

Input::render([
    'name'      => 'password',
    'label'     => 'Password',
    'type'      => 'password',
    'minlength' => 8,
]);
```

---

### Textarea

**Namespace:** `Digitalis\Field\Textarea`
**File:** `include/views/fields/textarea.field.php`

Multi-line text input.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `rows` | `int\|null` | `null` | Visible rows |
| `cols` | `int\|null` | `null` | Visible columns |
| `spellcheck` | `bool\|null` | `null` | Enable spellcheck |

#### Usage

```php
use Digitalis\Field\Textarea;

Textarea::render([
    'name'        => 'description',
    'label'       => 'Description',
    'rows'        => 5,
    'placeholder' => 'Enter a description...',
]);
```

---

### Select

**Namespace:** `Digitalis\Field\Select`
**File:** `include/views/fields/select.field.php`

Dropdown select field.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `options` | `array` | `[]` | Options (value => label) |
| `option_atts` | `array` | `[]` | Per-option attributes |

#### Usage

```php
use Digitalis\Field\Select;

// Simple options
Select::render([
    'name'    => 'country',
    'label'   => 'Country',
    'options' => [
        ''   => 'Select a country',
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
    ],
    'default' => 'us',
]);

// Option groups
Select::render([
    'name'    => 'city',
    'label'   => 'City',
    'options' => [
        'North America' => [
            'nyc' => 'New York',
            'la'  => 'Los Angeles',
            'tor' => 'Toronto',
        ],
        'Europe' => [
            'lon' => 'London',
            'par' => 'Paris',
            'ber' => 'Berlin',
        ],
    ],
]);
```

---

### Select_Nice

**Namespace:** `Digitalis\Field\Select_Nice`
**File:** `include/views/fields/select-nice.field.php`

Enhanced select with search (uses Nice Select 2 library).

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `nice-select` | `array` | (defaults) | Nice Select options |
| `load_scripts` | `bool` | `true` | Load JS library |
| `load_styles` | `bool` | `true` | Load CSS |

#### Nice Select Options

```php
[
    'searchable'  => true,   // Enable search
    'placeholder' => 'Select',
]
```

#### Usage

```php
use Digitalis\Field\Select_Nice;

Select_Nice::render([
    'name'    => 'user',
    'label'   => 'Select User',
    'options' => $user_options,  // Large list
    'nice-select' => [
        'searchable'  => true,
        'placeholder' => 'Search users...',
    ],
]);
```

---

### Checkbox

**Namespace:** `Digitalis\Field\Checkbox`
**File:** `include/views/fields/checkbox.field.php`

Single checkbox field.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `null_value` | `mixed` | `0` | Value when unchecked |
| `checked_value` | `mixed` | `1` | Value when checked |

#### Usage

```php
use Digitalis\Field\Checkbox;

Checkbox::render([
    'name'  => 'agree_terms',
    'label' => 'I agree to the terms and conditions',
]);

Checkbox::render([
    'name'          => 'subscribe',
    'label'         => 'Subscribe to newsletter',
    'checked_value' => 'yes',
    'null_value'    => 'no',
    'default'       => 'yes',
]);
```

---

### Checkbox_Group

**Namespace:** `Digitalis\Field\Checkbox_Group`
**File:** `include/views/fields/checkbox-group.field.php`

Multiple checkboxes for multi-select.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `options` | `array` | `[]` | Options (value => label) |
| `select_all` | `bool\|string` | `false` | Show "Select All" option |
| `select_all_key` | `string` | `'all'` | Select All value |

#### Usage

```php
use Digitalis\Field\Checkbox_Group;

Checkbox_Group::render([
    'name'    => 'categories',
    'label'   => 'Categories',
    'options' => [
        'tech'    => 'Technology',
        'design'  => 'Design',
        'business' => 'Business',
    ],
    'default' => ['tech', 'design'],
]);

// With "Select All"
Checkbox_Group::render([
    'name'       => 'permissions',
    'label'      => 'Permissions',
    'options'    => [
        'read'   => 'Read',
        'write'  => 'Write',
        'delete' => 'Delete',
    ],
    'select_all' => 'Select All Permissions',
]);
```

---

### Checkbox_Buttons

**Namespace:** `Digitalis\Field\Checkbox_Buttons`
**File:** `include/views/fields/checkbox-buttons.field.php`

Checkbox group styled as buttons.

Same parameters as Checkbox_Group, different visual styling.

---

### Radio

**Namespace:** `Digitalis\Field\Radio`
**File:** `include/views/fields/radio.field.php`

Radio button group for single selection.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `options` | `array` | `[]` | Options (value => label) |
| `option_atts` | `array` | `[]` | Per-option attributes |

#### Usage

```php
use Digitalis\Field\Radio;

Radio::render([
    'name'    => 'size',
    'label'   => 'Size',
    'options' => [
        'small'  => 'Small',
        'medium' => 'Medium',
        'large'  => 'Large',
    ],
    'default' => 'medium',
]);
```

---

### Radio_Buttons

**Namespace:** `Digitalis\Field\Radio_Buttons`
**File:** `include/views/fields/radio-buttons.field.php`

Radio group styled as buttons.

Same parameters as Radio, different visual styling.

---

### Hidden

**Namespace:** `Digitalis\Field\Hidden`
**File:** `include/views/fields/hidden.field.php`

Hidden input field.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'hidden'` | Always hidden |
| `wrap` | `bool` | `false` | No wrapper |

#### Usage

```php
use Digitalis\Field\Hidden;

Hidden::render([
    'name'  => 'action',
    'value' => 'submit_form',
]);

Hidden::render([
    'name'  => 'nonce',
    'value' => wp_create_nonce('my_action'),
]);
```

---

### Hidden_Group

**Namespace:** `Digitalis\Field\Hidden_Group`
**File:** `include/views/fields/hidden-group.field-group.php`

Multiple hidden fields from key-value data.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `data` | `array` | `[]` | Key-value pairs |

#### Usage

```php
use Digitalis\Field\Hidden_Group;

Hidden_Group::render([
    'data' => [
        'action'   => 'process_order',
        'order_id' => 123,
        'nonce'    => wp_create_nonce('process_order'),
    ],
]);

// Outputs three hidden inputs
```

---

### Button

**Namespace:** `Digitalis\Field\Button`
**File:** `include/views/fields/button.field.php`

Button element.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'button'` | Button type |
| `text` | `string` | `'Button'` | Button text |

#### Usage

```php
use Digitalis\Field\Button;

Button::render([
    'text'    => 'Click Me',
    'classes' => ['btn', 'btn-primary'],
    'attributes' => [
        'onclick' => 'handleClick()',
    ],
]);
```

---

### Submit

**Namespace:** `Digitalis\Field\Submit`
**File:** `include/views/fields/submit.field.php`

Form submit button.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'submit'` | Always submit |
| `text` | `string` | `'Submit'` | Button text |

#### Usage

```php
use Digitalis\Field\Submit;

Submit::render([
    'text'    => 'Save Changes',
    'classes' => ['btn-success'],
]);
```

---

### Number

**Namespace:** `Digitalis\Field\Number`
**File:** `include/views/fields/number.field.php`

Numeric input with min/max/step.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'number'` | Always number |
| `min` | `number\|null` | `null` | Minimum value |
| `max` | `number\|null` | `null` | Maximum value |
| `step` | `number\|null` | `null` | Step increment |

#### Usage

```php
use Digitalis\Field\Number;

Number::render([
    'name'    => 'quantity',
    'label'   => 'Quantity',
    'min'     => 1,
    'max'     => 100,
    'step'    => 1,
    'default' => 1,
]);

Number::render([
    'name'    => 'price',
    'label'   => 'Price',
    'min'     => 0,
    'step'    => 0.01,
]);
```

---

### Range

**Namespace:** `Digitalis\Field\Range`
**File:** `include/views/fields/range.field.php`

Range slider with optional value display.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `min` | `number` | `0` | Minimum value |
| `max` | `number` | `1` | Maximum value |
| `step` | `number` | `0.01` | Step increment |
| `show_value` | `bool` | `true` | Show current value |
| `value_prefix` | `string` | `''` | Value prefix (e.g., "$") |
| `value_suffix` | `string` | `''` | Value suffix (e.g., "%") |

#### Usage

```php
use Digitalis\Field\Range;

Range::render([
    'name'         => 'volume',
    'label'        => 'Volume',
    'min'          => 0,
    'max'          => 100,
    'step'         => 1,
    'default'      => 50,
    'value_suffix' => '%',
]);

Range::render([
    'name'         => 'price_range',
    'label'        => 'Max Price',
    'min'          => 0,
    'max'          => 1000,
    'step'         => 10,
    'value_prefix' => '$',
]);
```

---

### File

**Namespace:** `Digitalis\Field\File`
**File:** `include/views/fields/file.field.php`

File upload input.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `accept` | `array\|string` | `[]` | Accepted file types |
| `multiple` | `bool` | `false` | Allow multiple files |

#### Usage

```php
use Digitalis\Field\File;

File::render([
    'name'   => 'document',
    'label'  => 'Upload Document',
    'accept' => ['.pdf', '.doc', '.docx'],
]);

File::render([
    'name'     => 'images',
    'label'    => 'Upload Images',
    'accept'   => ['image/*'],
    'multiple' => true,
]);
```

---

### Date

**Namespace:** `Digitalis\Field\Date`
**File:** `include/views/fields/date.field.php`

Native HTML5 date input.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | `string` | `'date'` | Always date |

#### Usage

```php
use Digitalis\Field\Date;

Date::render([
    'name'    => 'birth_date',
    'label'   => 'Date of Birth',
    'default' => '1990-01-01',
]);
```

---

### Date_Picker

**Namespace:** `Digitalis\Field\Date_Picker`
**File:** `include/views/fields/date-picker.field.php`

Enhanced date picker (uses Vanilla JS Datepicker).

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `date_picker` | `array` | (defaults) | Datepicker options |

#### Datepicker Options

```php
[
    'autohide' => false,
    'format'   => 'dd/mm/yyyy',
]
```

#### Usage

```php
use Digitalis\Field\Date_Picker;

Date_Picker::render([
    'name'        => 'event_date',
    'label'       => 'Event Date',
    'date_picker' => [
        'format'   => 'yyyy-mm-dd',
        'autohide' => true,
    ],
]);
```

---

### Date_Range

**Namespace:** `Digitalis\Field\Date_Range`
**File:** `include/views/fields/date-range.field.php`

Date range picker with start and end dates.

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `value_start` | `string\|null` | `null` | Start date value |
| `value_end` | `string\|null` | `null` | End date value |
| `default_start` | `string` | `''` | Default start date |
| `default_end` | `string` | `''` | Default end date |
| `name_start` | `string\|null` | `null` | Start field name |
| `name_end` | `string\|null` | `null` | End field name |
| `seperator` | `string` | `'→'` | Visual separator |

#### Usage

```php
use Digitalis\Field\Date_Range;

Date_Range::render([
    'name'          => 'booking',
    'label'         => 'Booking Dates',
    'default_start' => date('Y-m-d'),
    'default_end'   => date('Y-m-d', strtotime('+7 days')),
]);
```

---

## Class Hierarchy

```
View (abstract)
│
├── Debug
├── Debug_Code_Block
├── Iterator_UI
│
├── Component
│   │
│   ├── Link
│   ├── HTMX
│   ├── Table
│   ├── Menu
│   ├── Menu_Item
│   │
│   ├── Archive (abstract)
│   │   ├── Post_Archive
│   │   └── Term_Archive (abstract)
│   │
│   ├── Field_Group
│   │   ├── Form
│   │   ├── Query_Filters (abstract)
│   │   └── Hidden_Group
│   │
│   └── Field
│       │
│       ├── Textarea
│       ├── Select
│       │   └── Select_Nice
│       │
│       └── Input
│           ├── Checkbox
│           │   └── Checkbox_Group
│           │       └── Checkbox_Buttons
│           ├── Radio
│           │   └── Radio_Buttons
│           ├── Hidden
│           ├── Number
│           ├── Range
│           ├── File
│           ├── Date
│           ├── Date_Picker
│           │   └── Date_Range
│           └── Button
│               └── Submit
```

---

## Quick Reference

### Importing Classes

```php
use Digitalis\Field\Input;
use Digitalis\Field\Select;
use Digitalis\Field\Textarea;
use Digitalis\Field\Checkbox;
use Digitalis\Field\Hidden;
use Digitalis\Field\Submit;
use Digitalis\Field_Group;
use Digitalis\Form;
use Digitalis\Component\Table;
use Digitalis\Component\HTMX;
use Digitalis\Menu;
```

### Common Field Usage

```php
// Text input
Input::render(['name' => 'email', 'label' => 'Email', 'type' => 'email']);

// Select dropdown
Select::render(['name' => 'role', 'label' => 'Role', 'options' => [...]]);

// Textarea
Textarea::render(['name' => 'bio', 'label' => 'Biography', 'rows' => 5]);

// Checkbox
Checkbox::render(['name' => 'agree', 'label' => 'I agree']);

// Submit button
Submit::render(['text' => 'Save']);
```
