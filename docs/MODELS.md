# Digitalis Framework: Model Reference

Quick reference for model classes and their available methods.

---

## Table of Contents

- [Model Hierarchy](#model-hierarchy)
- [Getting Instances](#getting-instances)
- [Post Model](#post-model)
- [User Model](#user-model)
- [Term Model](#term-model)
- [Order Model](#order-model)
- [Customer Model](#customer-model)
- [Shared Methods](#shared-methods)
- [ACF Fields](#acf-fields)
- [Meta Data](#meta-data)
- [Extending Models](#extending-models)

---

## Model Hierarchy

```
Model
├── WP_Model
│   ├── Post          # WordPress posts (any post type)
│   │   ├── Page
│   │   ├── Attachment
│   │   └── [Custom]  # Project, Document, etc.
│   │
│   ├── User          # WordPress users
│   │   ├── Customer  # WooCommerce customer
│   │   └── [Custom]  # Account, Admin, etc.
│   │
│   └── Term          # WordPress terms
│       └── [Custom]  # Project_Category, etc.
│
└── Order             # WooCommerce orders (not WP_Model)
```

---

## Getting Instances

### Single Instance

```php
// Auto-resolves to most specific subclass
$post = Post::get_instance($id);     // Returns Project if post_type='project'
$user = User::get_instance($id);     // Returns Account if role='account'
$term = Term::get_instance($id);     // Returns Category if taxonomy='category'

// Force base class (skip resolution)
$post = Post::get_instance($id, false);

// Current global (from WordPress loop/context)
$post = Post::get_instance();        // Current post in loop
$user = User::get_instance();        // Current logged-in user
$term = Term::get_instance();        // Current queried term
```

### Multiple Instances

```php
$posts = Post::get_instances([1, 2, 3]);
$users = User::get_instances($user_ids);
$terms = Term::get_instances($term_ids);
```

### Current User Shorthand

```php
$user = User::current();   // Alias for User::get_instance()
$user = User::inst();      // Shorter alias
```

### Find by Field

```php
// Post
$post = Post::get_by_slug('my-post');

// User
$user = User::get_by_email('user@example.com');
$user = User::get_by_login('username');
$user = User::get_by_slug('user-slug');

// Term
$term = Term::get_by_slug('term-slug');
$term = Term::get_by_name('Term Name');
```

---

## Post Model

### Class Properties

```php
class Project extends Post {
    protected static $post_type   = 'project';      // Required for resolution
    protected static $post_status = 'publish';      // Optional: filter by status
    protected static $term        = 'featured';     // Optional: filter by term
    protected static $taxonomy    = 'project_tag';  // Taxonomy for $term
}
```

### Basic Getters

| Method | Returns | Description |
|--------|---------|-------------|
| `get_id()` | `int` | Post ID |
| `get_title()` | `string` | Post title |
| `get_slug()` | `string` | Post name/slug |
| `get_content($apply_filters)` | `string` | Post content |
| `get_excerpt($force_trim)` | `string` | Post excerpt |
| `get_status()` | `string` | Post status |
| `get_type()` | `string` | Post type |
| `get_guid()` | `string` | Post GUID |

### Basic Setters

| Method | Description |
|--------|-------------|
| `set_title($title)` | Set post title |
| `set_slug($slug)` | Set post name |
| `set_content($content)` | Set post content |
| `set_excerpt($excerpt)` | Set post excerpt |
| `set_status($status)` | Set post status |
| `set_post_type($type)` | Set post type |

### URLs

| Method | Returns | Description |
|--------|---------|-------------|
| `get_url()` | `string` | Permalink |
| `get_permalink()` | `string` | Permalink (alias) |
| `get_edit_url()` | `string` | Admin edit URL |
| `get_archive_url()` | `string` | Post type archive URL |

### Dates

| Method | Returns | Description |
|--------|---------|-------------|
| `get_date($format)` | `string` | Formatted publish date |
| `get_date_modified($format)` | `string` | Formatted modified date |
| `get_post_datetime($field)` | `DateTime` | DateTime object |
| `get_post_timestamp($field)` | `int` | Unix timestamp |
| `set_date($date)` | `$this` | Set publish date |

### Author

| Method | Returns | Description |
|--------|---------|-------------|
| `get_author_id()` | `int` | Author user ID |
| `get_author()` | `User` | Author User instance |
| `set_author_id($id)` | `$this` | Set author by ID |
| `set_author($user)` | `$this` | Set author by User instance |

### Images/Attachments

| Method | Returns | Description |
|--------|---------|-------------|
| `has_thumbnail()` | `bool` | Has featured image |
| `has_image()` | `bool` | Alias for has_thumbnail |
| `get_thumbnail($size, $attr)` | `string` | Featured image HTML |
| `get_image($size)` | `string` | Alias for get_thumbnail |
| `get_thumbnail_url($size)` | `string` | Featured image URL |
| `get_image_url($size)` | `string` | Alias for get_thumbnail_url |
| `get_thumbnail_id()` | `int` | Attachment ID |
| `get_attachments($mime)` | `array` | Attached media |

### Terms/Taxonomies

| Method | Returns | Description |
|--------|---------|-------------|
| `get_terms($taxonomy)` | `array` | Terms for taxonomy |
| `has_term($term, $tax)` | `bool` | Has specific term |
| `set_terms($terms, $tax)` | `array\|WP_Error` | Replace terms |
| `add_terms($terms, $tax)` | `array\|WP_Error` | Add terms |
| `remove_terms($terms, $tax)` | `bool\|WP_Error` | Remove terms |

### Parent/Hierarchy

| Method | Returns | Description |
|--------|---------|-------------|
| `has_post_parent()` | `bool` | Has parent post |
| `get_parent_id()` | `int` | Parent post ID |
| `get_parent()` | `Post\|null` | Parent Post instance |
| `set_parent_id($id)` | `$this` | Set parent ID |
| `set_parent($post)` | `$this` | Set parent by instance |

### Revisions

| Method | Returns | Description |
|--------|---------|-------------|
| `is_revisions_enabled()` | `bool` | Revisions enabled |
| `get_revisions()` | `array` | Revision instances |
| `get_revision_count()` | `int` | Number of revisions |
| `get_latest_revision()` | `Revision` | Most recent revision |
| `get_revisions_url()` | `string` | Admin revisions URL |

### Comments

| Method | Returns | Description |
|--------|---------|-------------|
| `get_comments($args)` | `array` | Comment instances |
| `get_comment_count($args)` | `int` | Comment count |
| `is_comments_open()` | `bool` | Comments allowed |
| `get_comments_url()` | `string` | Comments anchor URL |

### State Checks

| Method | Returns | Description |
|--------|---------|-------------|
| `is_new()` | `bool` | Not yet saved |
| `is_dirty()` | `bool` | Has unsaved changes |
| `is_saved()` | `bool` | Persisted to database |
| `is_auto_draft()` | `bool` | Is auto-draft status |
| `is_sticky()` | `bool` | Is sticky post |
| `is_password_protected()` | `bool` | Requires password |

### CRUD

| Method | Description |
|--------|-------------|
| `save($post_array)` | Insert or update post |
| `delete($force)` | Delete post (trash or permanent) |
| `reload()` | Refresh from database |

### Querying Posts

```php
// Basic query
$posts = Post::query([
    'posts_per_page' => 10,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

// With custom post type (auto-applied if $post_type set)
$projects = Project::query([
    'meta_key'   => 'priority',
    'orderby'    => 'meta_value',
]);

// Returns array of model instances
```

---

## User Model

### Class Properties

```php
class Account extends User {
    protected static $role = 'account';  // Required for resolution
}
```

### Basic Getters

| Method | Returns | Description |
|--------|---------|-------------|
| `get_id()` | `int` | User ID |
| `get_username()` | `string` | Login name |
| `get_email()` | `string` | Email address |
| `get_display_name()` | `string` | Display name |
| `get_first_name()` | `string` | First name |
| `get_last_name()` | `string` | Last name |
| `get_full_name()` | `string` | First + Last name |
| `get_nicename()` | `string` | URL-friendly name |
| `get_nick_name()` | `string` | Nickname |
| `get_description()` | `string` | Biographical info |
| `get_user_url()` | `string` | Website URL |
| `get_registered_date()` | `string` | Registration date |

### Basic Setters

| Method | Description |
|--------|-------------|
| `set_username($username)` | Set login name |
| `set_email($email)` | Set email |
| `set_display_name($name)` | Set display name |
| `set_password($password)` | Set password (hashed) |
| `set_user_url($url)` | Set website |

### Roles & Capabilities

| Method | Returns | Description |
|--------|---------|-------------|
| `get_role()` | `string` | Primary role |
| `get_roles()` | `array` | All roles |
| `has_role($role)` | `bool` | Has specific role |
| `set_roles($roles)` | `$this` | Replace roles |
| `add_role($role)` | `$this` | Add a role |
| `can($cap, ...$args)` | `bool` | Has capability |
| `is_super_admin()` | `bool` | Is super admin |
| `can_access_dashboard()` | `bool` | Can access wp-admin |

### URLs

| Method | Returns | Description |
|--------|---------|-------------|
| `get_url()` | `string` | Author archive URL |
| `get_edit_url()` | `string` | Admin edit URL |
| `get_avatar_url($args)` | `string` | Avatar image URL |
| `get_avatar($size)` | `string` | Avatar HTML |

### Password

| Method | Returns | Description |
|--------|---------|-------------|
| `get_password()` | `string` | Hashed password |
| `set_password($pass)` | `$this` | Set new password |
| `get_password_reset_key()` | `string` | Reset key |
| `is_password_reset_allowed()` | `bool` | Can reset |

### User Options

| Method | Returns | Description |
|--------|---------|-------------|
| `get_option($option)` | `mixed` | User option value |
| `update_option($opt, $val)` | `bool` | Set user option |
| `delete_option($option)` | `bool` | Delete user option |

### Comments

| Method | Returns | Description |
|--------|---------|-------------|
| `get_comments($args)` | `array` | User's comments |
| `get_comment_count($args)` | `int` | Comment count |

### Misc

| Method | Returns | Description |
|--------|---------|-------------|
| `get_posts_count($type)` | `int` | Posts by user |
| `get_sites($all)` | `array` | Multisite blogs |
| `is_site_member($blog)` | `bool` | Member of blog |
| `is_spammer()` | `bool` | Marked as spam |
| `get_admin_color()` | `string` | Admin color scheme |

### CRUD

| Method | Description |
|--------|-------------|
| `save($user_data)` | Update user |
| `reload()` | Refresh from database |
| `send_new_user_notifications($notify)` | Send welcome email |

---

## Term Model

### Class Properties

```php
class Project_Category extends Term {
    protected static $taxonomy = 'project_category';  // Required for resolution
}
```

### Basic Getters

| Method | Returns | Description |
|--------|---------|-------------|
| `get_id()` | `int` | Term ID |
| `get_name()` | `string` | Term name |
| `get_slug()` | `string` | Term slug |
| `get_description()` | `string` | Term description |
| `get_taxonomy()` | `string` | Taxonomy name |
| `get_count()` | `int` | Post count |
| `get_term_group()` | `int` | Term group |
| `get_term_taxonomy_id()` | `int` | Term taxonomy ID |

### Basic Setters

| Method | Description |
|--------|-------------|
| `set_name($name)` | Set term name |
| `set_slug($slug)` | Set term slug |
| `set_description($desc)` | Set description |
| `set_taxonomy($tax)` | Set taxonomy |

### Hierarchy

| Method | Returns | Description |
|--------|---------|-------------|
| `get_parent_id()` | `int` | Parent term ID |
| `get_parent()` | `Term\|null` | Parent Term instance |
| `set_parent_id($id)` | `$this` | Set parent ID |
| `set_parent($term)` | `$this` | Set parent by instance |
| `get_all_parents($asc)` | `array` | All ancestor terms |
| `get_children()` | `array` | Direct children |

### URLs

| Method | Returns | Description |
|--------|---------|-------------|
| `get_url()` | `string` | Term archive URL |
| `get_feed($feed)` | `string` | Term feed URL |

### CRUD

| Method | Description |
|--------|-------------|
| `save($term_array)` | Insert or update term |
| `delete($args)` | Delete term |
| `reload()` | Refresh from database |

### Querying Terms

```php
// Basic query
$terms = Term::query([
    'taxonomy'   => 'category',
    'hide_empty' => false,
]);

// With hierarchy
$terms = Project_Category::query([
    'hierarchy' => true,  // Returns nested structure
    'parent'    => 0,     // Top-level only
]);

// Get terms for a post
$terms = Project_Category::query_post($post);
```

---

## Order Model

WooCommerce orders. Proxies to `WC_Order` methods.

### Getting Orders

```php
$order = Order::get_instance($order_id);
$order = Order::get_instance($wc_order);  // From WC_Order object

// Query orders
$orders = Order::query([
    'customer_id' => $user_id,
    'status'      => 'completed',
    'limit'       => 10,
]);
```

### Proxied WC_Order Methods

All `WC_Order` methods are available via `__call`:

```php
$order->get_id();
$order->get_status();
$order->get_total();
$order->get_billing_email();
$order->get_billing_first_name();
$order->get_shipping_address_1();
$order->get_items();
$order->get_customer_id();
$order->get_date_created();
$order->get_payment_method();
// ... all WC_Order methods
```

### Native Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `get_wc_order()` | `WC_Order` | Underlying WC_Order |

---

## Customer Model

Extends User with WooCommerce customer data. Proxies to `WC_Customer`.

### Getting Customers

```php
$customer = Customer::get_instance($user_id);
$customer = Customer::get_instance();  // Current user
```

### Proxied WC_Customer Methods

All `WC_Customer` methods available via `__call`:

```php
$customer->get_billing_first_name();
$customer->get_billing_last_name();
$customer->get_billing_email();
$customer->get_billing_phone();
$customer->get_billing_address_1();
$customer->get_billing_city();
$customer->get_billing_state();
$customer->get_billing_postcode();
$customer->get_billing_country();

$customer->get_shipping_first_name();
$customer->get_shipping_address_1();
// ... all WC_Customer methods
```

### Native Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `get_customer()` | `WC_Customer` | Underlying WC_Customer |
| `get_orders($args)` | `array` | Customer's orders |
| `has_ordered($product_id)` | `WC_Order\|false` | Has purchased product |
| `get_first_name()` | `string` | First name (falls back to billing) |
| `get_last_name()` | `string` | Last name (falls back to billing) |

### Order Query Args

```php
$orders = $customer->get_orders([
    'status' => 'paid',            // paid, pending, paid_and_pending, or WC status
    'limit'  => 10,
]);
```

---

## Shared Methods

Available on Post, User, and Term via `WP_Model`:

### State

| Method | Returns | Description |
|--------|---------|-------------|
| `is_new()` | `bool` | Not yet saved to DB |
| `is_dirty()` | `bool` | Has unsaved changes |
| `is_saved()` | `bool` | Persisted to DB |
| `is_unsaved()` | `bool` | New or dirty |

### Caching

| Method | Description |
|--------|-------------|
| `cache_instance()` | Cache in static array |
| `clear_wp_model_cache()` | Clear WP object cache |
| `reload()` | Refresh from database |

### WordPress Object

| Method | Returns | Description |
|--------|---------|-------------|
| `get_wp_post()` | `WP_Post` | (Post) Underlying WP_Post |
| `get_wp_user()` | `WP_User` | (User) Underlying WP_User |
| `get_wp_term()` | `WP_Term` | (Term) Underlying WP_Term |

---

## ACF Fields

Available on Post, User, and Term when ACF is active.

### Reading Fields

| Method | Returns | Description |
|--------|---------|-------------|
| `get_field($selector)` | `mixed` | Field value |
| `get_fields()` | `array` | All field values |
| `esc_field($selector)` | `string` | Escaped field value |
| `get_field_object($selector)` | `array` | Full field config |
| `get_field_objects()` | `array` | All field configs |
| `field_has_rows($selector)` | `bool` | Repeater has rows |

### Writing Fields

| Method | Returns | Description |
|--------|---------|-------------|
| `update_field($selector, $value)` | `bool` | Update field |
| `update_fields($data)` | `void` | Update multiple fields |
| `delete_field($selector)` | `bool` | Delete field |

### Repeater Fields

| Method | Description |
|--------|-------------|
| `field_add_row($selector, $row)` | Add repeater row |
| `field_update_row($selector, $i, $row)` | Update row at index |
| `field_delete_row($selector, $i)` | Delete row at index |
| `field_add_sub_row($selector, $row)` | Add nested row |
| `field_update_sub_row($selector, $i, $row)` | Update nested row |
| `field_delete_sub_row($selector, $i)` | Delete nested row |

### Usage Example

```php
$project = Project::get_instance($id);

// Read
$account_id = $project->get_field('project_account');
$documents  = $project->get_field('project_documents');
$all_fields = $project->get_fields();

// Write
$project->update_field('project_status', 'active');
$project->update_fields([
    'project_status'   => 'active',
    'project_priority' => 'high',
]);

// Repeater
if ($project->field_has_rows('milestones')) {
    while (have_rows('milestones', $project->get_id())) {
        the_row();
        // ...
    }
}
$project->field_add_row('milestones', [
    'title' => 'New Milestone',
    'date'  => '2024-01-01',
]);
```

---

## Meta Data

Available on Post, User, and Term.

### Reading Meta

| Method | Returns | Description |
|--------|---------|-------------|
| `get_meta($key, $single)` | `mixed` | Meta value |
| `get_all_meta()` | `array` | All meta values |

### Writing Meta

| Method | Returns | Description |
|--------|---------|-------------|
| `add_meta($key, $value, $unique)` | `int\|false` | Add meta |
| `add_unique_meta($key, $value)` | `int\|false` | Add unique meta |
| `update_meta($key, $value)` | `int\|bool` | Update meta |
| `update_metas($data)` | `void` | Update multiple |
| `delete_meta($key, $value)` | `bool` | Delete meta |

### Usage Example

```php
$post = Post::get_instance($id);

// Read
$value = $post->get_meta('_custom_key');
$all   = $post->get_all_meta();

// Write
$post->update_meta('_custom_key', 'value');
$post->update_metas([
    '_key_1' => 'value1',
    '_key_2' => 'value2',
]);

// Delete
$post->delete_meta('_custom_key');
```

---

## Extending Models

### Custom Post Type Model

```php
namespace Digitalis;

class Project extends Post {
    protected static $post_type = 'project';

    public function get_account(): ?Account {
        $id = $this->get_field('project_account');
        return $id ? Account::get_instance($id) : null;
    }

    public function get_documents(): array {
        $ids = $this->get_field('project_documents') ?: [];
        return Document::get_instances($ids);
    }

    public function is_active(): bool {
        return $this->get_status() === 'publish';
    }

    public function complete(): self {
        $this->set_status('completed');
        $this->update_field('completed_date', date('Y-m-d'));
        return $this->save();
    }
}
```

### Custom User Model

```php
namespace Digitalis;

class Account extends User {
    protected static $role = 'account';

    public function get_company(): string {
        return $this->get_meta('company_name') ?: '';
    }

    public function get_projects(): array {
        return Project::query([
            'meta_key'   => 'project_account',
            'meta_value' => $this->get_id(),
        ]);
    }

    public function can(string $cap, ...$args): bool {
        switch ($cap) {
            case 'view_project':
                $project = Project::get_instance($args[0] ?? null);
                return $project?->get_account()?->get_id() === $this->get_id();
            default:
                return parent::can($cap, ...$args);
        }
    }
}
```

### Custom Term Model

```php
namespace Digitalis;

class Project_Category extends Term {
    protected static $taxonomy = 'project_category';

    public function get_projects(): array {
        return Project::query([
            'tax_query' => [[
                'taxonomy' => $this->get_taxonomy(),
                'terms'    => $this->get_id(),
            ]],
        ]);
    }

    public function get_icon(): string {
        return $this->get_field('category_icon') ?: 'folder';
    }
}
```

### Custom Order Model

```php
namespace Digitalis;

class Invoice extends Order {
    public function get_account(): ?Account {
        $id = $this->get_meta('_account_id');
        return $id ? Account::get_instance($id) : null;
    }

    public function get_pdf_url(): string {
        return add_query_arg([
            'action'   => 'download_invoice',
            'order_id' => $this->get_id(),
            'nonce'    => wp_create_nonce('invoice_' . $this->get_id()),
        ], admin_url('admin-ajax.php'));
    }

    public function mark_sent(): void {
        $this->update_meta('_invoice_sent', current_time('mysql'));
    }
}
```

---

## Quick Reference Card

### Instantiation

```php
Post::get_instance($id)      // Auto-resolve
Post::get_instance($id, false) // Force base class
Post::get_instances($ids)    // Multiple
User::current()              // Current user
User::inst()                 // Alias
```

### Common Patterns

```php
// Post
$post->get_title()
$post->get_content()
$post->get_url()
$post->get_field('name')
$post->get_terms('category')
$post->save()

// User
$user->get_email()
$user->get_full_name()
$user->can('edit_posts')
$user->has_role('admin')
$user->get_meta('key')

// Term
$term->get_name()
$term->get_url()
$term->get_parent()
$term->get_field('icon')

// Order (WC)
$order->get_total()
$order->get_status()
$order->get_billing_email()

// Customer (WC)
$customer->get_orders()
$customer->has_ordered($product_id)
$customer->get_billing_address_1()
```
