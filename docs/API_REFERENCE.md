# Digitalis Framework API Reference

## Table of Contents

- [Design Patterns](#design-patterns)
- [Core Objects](#core-objects)
- [WordPress Models](#wordpress-models)
- [Views & Components](#views--components)
- [Form Fields](#form-fields)
- [Admin Classes](#admin-classes)
- [Iterators](#iterators)
- [WooCommerce](#woocommerce)
- [Traits](#traits)
- [Utilities](#utilities)
- [Hooks Reference](#hooks-reference)

---

## Design Patterns

### `Digitalis\Design_Pattern`

Base abstract class for all design patterns.

| Property | Type | Description |
|----------|------|-------------|
| — | — | No public properties |

| Method | Signature | Description |
|--------|-----------|-------------|
| — | — | Base class only |

---

### `Digitalis\Singleton`

Ensures single instance per class.

```php
abstract class Singleton extends Design_Pattern
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_instance()` | `static get_instance(): static` | Returns the singleton instance |
| `__clone()` | `public __clone(): void` | Throws Exception (cloning disabled) |
| `__wakeup()` | `public __wakeup(): void` | Throws Exception (unserialization disabled) |

---

### `Digitalis\Factory`

Creates and manages instances with optional caching.

```php
abstract class Factory extends Design_Pattern
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$cache_property` | `string\|null` | `null` | Property name to use as cache key |
| `$cache` | `array` | `[]` | Instance cache storage |

| Method | Signature | Description |
|--------|-----------|-------------|
| `create()` | `static create(array $data = []): static` | Creates new instance |
| `get_instance()` | `static get_instance(mixed $identifier): ?static` | Gets cached or creates new instance |
| `get_instances()` | `static get_instances(array $identifiers): array` | Gets multiple instances |

---

### `Digitalis\Creational`

Base for creational patterns with instance tracking.

```php
abstract class Creational extends Design_Pattern
```

| Property | Type | Description |
|----------|------|-------------|
| `$instances` | `array` | Tracks all created instances |

---

## Core Objects

### `Digitalis\Model`

Entity model with auto-resolution and caching.

```php
abstract class Model extends Factory
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$class_map` | `array` | `[]` | Maps IDs to specific subclasses |
| `$id` | `mixed` | `null` | Entity identifier |

| Method | Signature | Description |
|--------|-----------|-------------|
| `create()` | `static create(array $data = []): static` | Creates model instance |
| `get_instance()` | `static get_instance(mixed $id): ?static` | Gets model by ID with auto-resolution |
| `get_instances()` | `static get_instances(array $ids): array` | Gets multiple models |
| `resolve_class()` | `static resolve_class(mixed $id): string` | Resolves most specific class for ID |
| `validate_id()` | `static validate_id(mixed $id): bool` | Validates ID for this model |
| `is_new()` | `public is_new(): bool` | Checks if model is unsaved |

---

### `Digitalis\Service`

Service factory without auto-instantiation.

```php
abstract class Service extends Factory
```

| Method | Signature | Description |
|--------|-----------|-------------|
| Inherits from Factory | | |

---

### `Digitalis\Feature`

Feature factory with WordPress hook support.

```php
abstract class Feature extends Factory
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `run()` | `public run(): void` | Called on instantiation |
| `get_hooks()` | `public get_hooks(): array` | Returns hooks to register |

---

### `Digitalis\Integration`

Singleton for third-party integrations.

```php
abstract class Integration extends Singleton
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_hooks()` | `public get_hooks(): array` | Returns hooks to register |
| `init()` | `public init(): void` | Initialization logic |

---

### `Digitalis\App`

Plugin application base class.

```php
abstract class App extends Creational
```

| Property | Type | Description |
|----------|------|-------------|
| `$path` | `string` | Plugin directory path |
| `$url` | `string` | Plugin directory URL |

| Method | Signature | Description |
|--------|-----------|-------------|
| `autoload()` | `public autoload(string $path = null, bool $recursive = true): void` | Autoloads classes from directory |
| `load_class()` | `public load_class(string $path, callable $instantiation = null): void` | Loads single class file |
| `register_bricks_elements()` | `public register_bricks_elements(string $path): void` | Registers Bricks page builder elements |

---

## WordPress Models

### `Digitalis\WP_Model`

Base for WordPress entity models.

```php
abstract class WP_Model extends Model
```

| Property | Type | Description |
|----------|------|-------------|
| `$dirty` | `bool` | Whether model has unsaved changes |
| `$stash` | `array` | Temporary storage |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_id()` | `public get_id(): mixed` | Returns entity ID |
| `is_dirty()` | `public is_dirty(): bool` | Checks for unsaved changes |
| `mark_dirty()` | `public mark_dirty(): void` | Marks model as modified |

---

### `Digitalis\Post`

WordPress post model.

```php
class Post extends WP_Model
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$post_type` | `string\|array` | `'post'` | Valid post type(s) |
| `$post_status` | `string\|array` | `'publish'` | Valid status(es) |
| `$term` | `int\|null` | `null` | Required term ID |
| `$taxonomy` | `string\|null` | `null` | Taxonomy for term validation |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_post()` | `public get_post(): ?WP_Post` | Returns WP_Post object |
| `get_by_slug()` | `static get_by_slug(string $slug): ?static` | Gets post by slug |
| `query()` | `static query(array $args = []): array` | Queries posts |
| `save()` | `public save(): int\|WP_Error` | Saves post to database |
| `delete()` | `public delete(bool $force = false): bool` | Deletes post |
| `get_meta()` | `public get_meta(string $key): mixed` | Gets post meta |
| `set_meta()` | `public set_meta(string $key, mixed $value): void` | Sets post meta |
| `get_title()` | `public get_title(): string` | Returns post title |
| `get_content()` | `public get_content(): string` | Returns post content |
| `get_excerpt()` | `public get_excerpt(): string` | Returns post excerpt |
| `get_permalink()` | `public get_permalink(): string` | Returns post URL |
| `get_thumbnail_id()` | `public get_thumbnail_id(): int` | Returns featured image ID |

---

### `Digitalis\User`

WordPress user model.

```php
class User extends WP_Model
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$role` | `string\|array\|null` | `null` | Required user role(s) |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_user()` | `public get_user(): ?WP_User` | Returns WP_User object |
| `get_by()` | `static get_by(string $field, mixed $value): ?static` | Gets user by field |
| `get_by_email()` | `static get_by_email(string $email): ?static` | Gets user by email |
| `get_by_login()` | `static get_by_login(string $login): ?static` | Gets user by login |
| `get_meta()` | `public get_meta(string $key): mixed` | Gets user meta |
| `set_meta()` | `public set_meta(string $key, mixed $value): void` | Sets user meta |
| `get_display_name()` | `public get_display_name(): string` | Returns display name |
| `get_email()` | `public get_email(): string` | Returns email |
| `has_role()` | `public has_role(string $role): bool` | Checks if user has role |

---

### `Digitalis\Term`

WordPress taxonomy term model.

```php
class Term extends WP_Model
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$taxonomy` | `string` | `'category'` | Taxonomy slug |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_term()` | `public get_term(): ?WP_Term` | Returns WP_Term object |
| `get_by()` | `static get_by(string $field, mixed $value): ?static` | Gets term by field |
| `get_by_slug()` | `static get_by_slug(string $slug): ?static` | Gets term by slug |
| `query_post()` | `public query_post(array $args = []): array` | Queries posts with this term |
| `get_name()` | `public get_name(): string` | Returns term name |
| `get_slug()` | `public get_slug(): string` | Returns term slug |
| `get_link()` | `public get_link(): string` | Returns term archive URL |

---

### `Digitalis\Post_Type`

Custom post type registration.

```php
abstract class Post_Type extends Singleton
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$slug` | `string` | `''` | Post type slug |
| `$singular` | `string` | `''` | Singular label |
| `$plural` | `string` | `''` | Plural label |
| `$icon` | `string` | `'dashicons-admin-post'` | Menu icon |
| `$position` | `int` | `25` | Menu position |
| `$archive` | `bool` | `true` | Has archive page |
| `$public` | `bool` | `true` | Is publicly queryable |
| `$hierarchical` | `bool` | `false` | Supports parent/child |
| `$supports` | `array` | `['title', 'editor']` | Feature support |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_args()` | `public get_args(): array` | Returns registration args |
| `columns()` | `public columns(array $columns): array` | Modifies admin columns |
| `column()` | `public column(string $column, int $post_id): void` | Renders column content |
| `get_query_vars()` | `public get_query_vars(): array` | Returns custom query vars |

---

### `Digitalis\Taxonomy`

Custom taxonomy registration.

```php
abstract class Taxonomy extends Singleton
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$slug` | `string` | `''` | Taxonomy slug |
| `$singular` | `string` | `''` | Singular label |
| `$plural` | `string` | `''` | Plural label |
| `$post_types` | `array` | `[]` | Associated post types |
| `$hierarchical` | `bool` | `true` | Is hierarchical |

---

### `Digitalis\Digitalis_Query`

Extended WP_Query with helpers.

```php
class Digitalis_Query extends WP_Query
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `query()` | `public query(array $args = []): array` | Executes query |
| `set_var()` | `public set_var(string $key, mixed $value): self` | Sets query var |
| `get_var()` | `public get_var(string $key): mixed` | Gets query var |
| `add_meta_query()` | `public add_meta_query(array $query): self` | Adds meta query |
| `add_tax_query()` | `public add_tax_query(array $query): self` | Adds taxonomy query |
| `merge()` | `public merge(array $args): self` | Merges query args |

---

## Views & Components

### `Digitalis\View`

Base view class with template rendering.

```php
abstract class View implements ArrayAccess
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$template` | `string` | `''` | Template file name |
| `$template_path` | `string` | `''` | Template directory |
| `$defaults` | `array` | `[]` | Default parameters |
| `$required` | `array` | `[]` | Required parameters |
| `$merge` | `array` | `[]` | Parameters to merge (arrays) |

| Method | Signature | Description |
|--------|-----------|-------------|
| `render()` | `static render(array $params = [], bool $print = true): string` | Renders view |
| `print()` | `public print(bool $return = false): string` | Outputs HTML |
| `get_template_path()` | `public get_template_path(): string` | Returns full template path |
| `offsetGet()` | `public offsetGet(mixed $key): mixed` | ArrayAccess: gets parameter |
| `offsetSet()` | `public offsetSet(mixed $key, mixed $value): void` | ArrayAccess: sets parameter |
| `offsetExists()` | `public offsetExists(mixed $key): bool` | ArrayAccess: checks parameter |
| `offsetUnset()` | `public offsetUnset(mixed $key): void` | ArrayAccess: removes parameter |

---

### `Digitalis\Component`

UI component with element rendering.

```php
class Component extends View
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$tag` | `string` | `'div'` | HTML tag |
| `$id` | `string` | `''` | Element ID |
| `$class` | `string\|array` | `[]` | CSS classes |
| `$style` | `string\|array` | `[]` | Inline styles |
| `$attr` | `array` | `[]` | HTML attributes |
| `$href` | `string` | `''` | Link URL (changes tag to 'a') |
| `$content` | `string` | `''` | Inner content |

| Method | Signature | Description |
|--------|-----------|-------------|
| `element()` | `public element(string $name, array $params = []): string` | Creates child element |
| `get_tag()` | `public get_tag(): string` | Returns HTML tag |
| `get_classes()` | `public get_classes(): string` | Returns class string |
| `get_attributes()` | `public get_attributes(): string` | Returns attribute string |

---

### `Digitalis\Route`

REST API endpoint registration.

```php
abstract class Route extends Factory
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$namespace` | `string` | `'digitalis'` | API namespace |
| `$version` | `string` | `'v1'` | API version |
| `$route` | `string` | `''` | Route path |
| `$methods` | `string\|array` | `'GET'` | HTTP methods |
| `$view` | `string` | `''` | View class to render |
| `$require_nonce` | `bool` | `false` | Require nonce verification |
| `$html_prefix` | `string` | `''` | Prefix for HTMX responses |

| Method | Signature | Description |
|--------|-----------|-------------|
| `register_api_routes()` | `public register_api_routes(): void` | Registers REST route |
| `handle()` | `public handle(WP_REST_Request $request): mixed` | Handles request |
| `permission_callback()` | `public permission_callback(): bool` | Permission check |

---

## Form Fields

All fields extend `Digitalis\Field`.

### `Digitalis\Field`

Base form field class.

```php
class Field extends Component
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$name` | `string` | `''` | Field name attribute |
| `$key` | `string` | `''` | Field key (for data binding) |
| `$id` | `string` | `''` | Field ID |
| `$label` | `string` | `''` | Field label |
| `$value` | `mixed` | `''` | Field value |
| `$options` | `array` | `[]` | Options (for select/radio) |
| `$placeholder` | `string` | `''` | Placeholder text |
| `$required` | `bool` | `false` | Is required |
| `$disabled` | `bool` | `false` | Is disabled |
| `$condition` | `array` | `[]` | Conditional display rules |

### Available Field Types

| Class | Description |
|-------|-------------|
| `Input` | Text input field |
| `Hidden` | Hidden input field |
| `File` | File upload field |
| `Textarea` | Multi-line text |
| `Number` | Numeric input |
| `Button` | Button element |
| `Submit` | Submit button |
| `Checkbox` | Single checkbox |
| `Checkbox_Group` | Multiple checkboxes |
| `Checkbox_Buttons` | Button-styled checkboxes |
| `Date` | Date input (native) |
| `Date_Picker` | Date picker widget |
| `Date_Range` | Date range selector |
| `Radio` | Radio buttons |
| `Radio_Buttons` | Button-styled radios |
| `Range` | Range slider |
| `Select` | Dropdown select |
| `Select_Nice` | Enhanced select |

---

## Admin Classes

### `Digitalis\Admin_Page`

Admin menu page.

```php
abstract class Admin_Page extends Singleton
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$slug` | `string` | `''` | Page slug |
| `$title` | `string` | `''` | Page title |
| `$menu_title` | `string` | `''` | Menu title |
| `$capability` | `string` | `'manage_options'` | Required capability |
| `$icon` | `string` | `''` | Menu icon |
| `$position` | `int` | `null` | Menu position |

| Method | Signature | Description |
|--------|-----------|-------------|
| `render()` | `public render(): void` | Renders page content |
| `enqueue_scripts()` | `public enqueue_scripts(): void` | Enqueues admin scripts |

---

### `Digitalis\Admin_Sub_Page`

Admin submenu page.

```php
abstract class Admin_Sub_Page extends Admin_Page
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$parent` | `string` | `''` | Parent page slug |

---

### `Digitalis\Admin_Table`

WP_List_Table wrapper.

```php
abstract class Admin_Table extends Singleton
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_columns()` | `public get_columns(): array` | Returns column definitions |
| `get_items()` | `public get_items(): array` | Returns table data |
| `column_default()` | `public column_default(object $item, string $column): string` | Default column render |

---

### `Digitalis\Meta_Box`

Post meta box registration.

```php
abstract class Meta_Box extends Feature
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$id` | `string` | `''` | Meta box ID |
| `$title` | `string` | `''` | Meta box title |
| `$screen` | `string\|array` | `null` | Post type(s) |
| `$context` | `string` | `'normal'` | Position context |
| `$priority` | `string` | `'default'` | Display priority |

| Method | Signature | Description |
|--------|-----------|-------------|
| `render()` | `public render(WP_Post $post): void` | Renders meta box |
| `save()` | `public save(int $post_id): void` | Saves meta box data |

---

## Iterators

### `Digitalis\Iterator`

Batch processing base class.

```php
abstract class Iterator extends Singleton
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$title` | `string` | `''` | Iterator title |
| `$key` | `string` | `''` | Unique key |
| `$batch_size` | `int` | `50` | Items per batch |
| `$capability` | `string` | `'manage_options'` | Required capability |
| `$halt_on_fail` | `bool` | `false` | Stop on error |
| `$dynamic_total` | `bool` | `false` | Recalculate total each batch |
| `$cron` | `bool` | `false` | Enable cron scheduling |
| `$cron_schedule` | `string` | `'hourly'` | Cron schedule |

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_items()` | `abstract public get_items(): array` | Returns items to process |
| `process_item()` | `abstract public process_item(mixed $item): bool` | Processes single item |
| `get_total_items()` | `public get_total_items(): int` | Returns total count |
| `get_item_id()` | `public get_item_id(mixed $item): mixed` | Returns item identifier |
| `cron_condition()` | `public cron_condition(): bool` | Check if cron should run |

### Iterator Types

| Class | Description |
|-------|-------------|
| `CSV_Iterator` | Process CSV file rows |
| `Post_Iterator` | Process posts |
| `Product_Iterator` | Process WooCommerce products |
| `Order_Iterator` | Process WooCommerce orders |
| `User_Iterator` | Process users |

---

## WooCommerce

### `Digitalis\Customer`

WooCommerce customer model.

```php
class Customer extends User
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_wc_customer()` | `public get_wc_customer(): ?WC_Customer` | Returns WC_Customer object |
| `get_orders()` | `public get_orders(array $args = []): array` | Gets customer orders |

---

### `Digitalis\Order`

WooCommerce order model.

```php
class Order extends Model
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_wc_order()` | `public get_wc_order(): ?WC_Order` | Returns WC_Order object |
| `query()` | `static query(array $args = []): array` | Queries orders |
| `get_items()` | `public get_items(): array` | Returns order items |
| `get_total()` | `public get_total(): float` | Returns order total |
| `get_status()` | `public get_status(): string` | Returns order status |

---

### `Digitalis\Order_Item`

WooCommerce order item model.

```php
class Order_Item extends Model
```

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_wc_item()` | `public get_wc_item(): ?WC_Order_Item` | Returns WC_Order_Item object |
| `get_product()` | `public get_product(): ?WC_Product` | Returns associated product |

---

## Traits

### `Has_WP_Hooks`

WordPress hook management.

| Method | Signature | Description |
|--------|-----------|-------------|
| `add_hook()` | `public add_hook(string $name, callable $callback, int $priority = 10, string $type = 'filter'): void` | Registers hook |
| `get_hooks()` | `public get_hooks(): array` | Returns hooks array |
| `remove_hook()` | `public remove_hook(string $name, callable $callback, int $priority = 10): void` | Removes hook |

### `Has_Meta`

Meta data management.

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_meta()` | `public get_meta(string $key): mixed` | Gets meta value |
| `set_meta()` | `public set_meta(string $key, mixed $value): void` | Sets meta value |
| `delete_meta()` | `public delete_meta(string $key): void` | Deletes meta |

### `Has_ACF_Fields`

ACF field support.

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_field()` | `public get_field(string $selector): mixed` | Gets ACF field value |
| `update_field()` | `public update_field(string $selector, mixed $value): void` | Updates ACF field |

### `Dependency_Injection`

Constructor/method injection.

| Method | Signature | Description |
|--------|-----------|-------------|
| `inject()` | `public inject(string $method, array $params = []): mixed` | Injects dependencies |

### `Inherits_Props`

Property inheritance from parent classes.

| Method | Signature | Description |
|--------|-----------|-------------|
| `get_inherited()` | `static get_inherited(string $property): mixed` | Gets inherited property |

---

## Utilities

### `Digitalis\Call`

Dynamic call utilities.

| Method | Signature | Description |
|--------|-----------|-------------|
| `method()` | `static method(object $obj, string $method, array $args = []): mixed` | Calls method dynamically |
| `static_method()` | `static static_method(string $class, string $method, array $args = []): mixed` | Calls static method |

### `Digitalis\List_Utility`

Array/list operations.

| Method | Signature | Description |
|--------|-----------|-------------|
| `pluck()` | `static pluck(array $list, string $key): array` | Extracts values by key |
| `group_by()` | `static group_by(array $list, string $key): array` | Groups by key |
| `filter()` | `static filter(array $list, callable $callback): array` | Filters list |

---

## Hooks Reference

### Framework Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `digitalis_loaded` | Action | — | Framework fully loaded |
| `digitalis_before_render` | Filter | `$html, $view` | Before view renders |
| `digitalis_after_render` | Filter | `$html, $view` | After view renders |

### Post Type Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `digitalis_{$post_type}_columns` | Filter | `$columns` | Modify admin columns |
| `digitalis_{$post_type}_query_vars` | Filter | `$vars` | Modify query vars |

### Model Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `digitalis_model_created` | Action | `$model` | After model created |
| `digitalis_model_saved` | Action | `$model` | After model saved |
| `digitalis_model_deleted` | Action | `$model` | After model deleted |

### Iterator Hooks

| Hook | Type | Parameters | Description |
|------|------|------------|-------------|
| `digitalis_iterator_before_batch` | Action | `$iterator, $items` | Before batch processes |
| `digitalis_iterator_after_batch` | Action | `$iterator, $results` | After batch processes |
| `digitalis_iterator_item_processed` | Action | `$iterator, $item, $result` | After item processed |

---

## Real-World Usage Examples (digitalis-co)

### WooCommerce Account Pages

The framework includes `Woo_Account_Page` for custom WooCommerce account area pages.

```php
namespace Digitalis;

// Custom account page with icon and access control
class Dashboard_Account_Page extends Woo_Account_Page {
    protected static $slug     = 'dashboard';
    protected static $title    = 'Dashboard';
    protected static $icon     = 'home';
    protected static $position = 5;

    public function render(): void {
        $user = User::current();
        $account = $user->get_account();

        // Render dashboard widgets
        Widget_Grid::render([
            'columns' => 12,
            'widgets' => [
                ['class' => Box_Link_Widget::class, 'params' => [
                    'title' => 'Projects',
                    'icon'  => 'folder',
                    'href'  => wc_get_account_endpoint_url('projects'),
                ]],
                ['class' => Box_Link_Widget::class, 'params' => [
                    'title' => 'Invoices',
                    'icon'  => 'file-text',
                    'href'  => wc_get_account_endpoint_url('invoices'),
                ]],
            ],
        ]);
    }
}
```

### Custom Order Status Flow

```php
namespace Digitalis;

// Register custom order status
class Order_Status_Approved extends Order_Status {
    protected static $slug  = 'wc-approved';
    protected static $label = 'Approved';
    protected static $color = '#28a745';

    // Email to send when order reaches this status
    protected static $email = Approved_Email::class;
}

// Handle status transitions
class Order_Status_Handler extends Feature {
    public function get_hooks(): array {
        return [
            'woocommerce_order_status_estimate_to_approved' => 'on_approved',
        ];
    }

    public function on_approved($order_id): void {
        $order = Order::get_instance($order_id);
        $account = $order->get_account();

        // Create project when estimate approved
        $project = Project::create([
            'post_title'  => 'Project for Order #' . $order_id,
            'post_status' => 'publish',
        ]);

        $project->set_meta('_order_id', $order_id);
        $account->add_project($project);
    }
}
```

### REST API with HTMX Support

```php
namespace Digitalis;

// API route that returns HTML for HTMX
class Project_Status_Route extends Route {
    protected static $route   = 'project/(?P<id>\d+)/status';
    protected static $methods = 'POST';
    protected static $view    = Project_Status_View::class;

    public function permission_callback(): bool {
        return User::current()?->can('edit_project', $this->get_param('id'));
    }

    public function handle(WP_REST_Request $request): mixed {
        $project = Project::get_instance($request->get_param('id'));
        $new_status = sanitize_text_field($request->get_param('status'));

        $project->set_meta('_status', $new_status);

        // Returns rendered HTML for HTMX swap
        return static::$view::render([
            'project' => $project,
            'status'  => $new_status,
        ], false);
    }
}
```

### Model Relationships

```php
namespace Digitalis;

class Project extends Post {
    protected static $post_type = 'project';

    // Get owning account
    public function get_account(): ?Account {
        $account_id = $this->get_field('project_account');
        return $account_id ? Account::get_instance($account_id) : null;
    }

    // Get team members
    public function get_team(): array {
        $user_ids = $this->get_field('project_team') ?: [];
        return User::get_instances($user_ids);
    }

    // Get related orders
    public function get_orders(): array {
        return Order::query([
            'meta_key'   => '_project_id',
            'meta_value' => $this->get_id(),
        ]);
    }

    // Calculate project progress
    public function get_progress(): array {
        $tasks = $this->get_field('project_tasks') ?: [];
        $completed = array_filter($tasks, fn($t) => $t['complete']);

        return [
            'current' => count($completed),
            'total'   => count($tasks),
            'percent' => count($tasks) ? (count($completed) / count($tasks)) * 100 : 0,
        ];
    }
}
```

### View Components with Conditional Display

```php
namespace Digitalis;

class Order_Actions_View extends View {
    protected static $defaults = [
        'order'        => null,
        'show_approve' => true,
        'show_pay'     => true,
    ];

    protected static $required = ['order'];

    public function print(bool $return = false): string {
        $order = $this['order'];
        $user = User::current();

        ob_start();
        ?>
        <div class="order-actions">
            <?php if ($this['show_approve'] && $order->get_status() === 'estimate'): ?>
                <?php if ($user->can('approve_estimate', $order->get_id())): ?>
                    <button
                        hx-post="/wp-json/digitalis/v1/estimate/<?= $order->get_id() ?>/approve"
                        hx-swap="outerHTML"
                        class="button button-primary"
                    >
                        Approve Estimate
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this['show_pay'] && $order->needs_payment()): ?>
                <a href="<?= $order->get_checkout_payment_url() ?>" class="button">
                    Pay Now
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
```

### Batch Processing with Iterator

```php
namespace Digitalis;

class Sync_Projects_Iterator extends Iterator {
    protected static $title      = 'Sync Project Statuses';
    protected static $key        = 'sync_projects';
    protected static $batch_size = 25;
    protected static $cron       = true;

    public function get_items(): array {
        return Project::query([
            'meta_query' => [
                [
                    'key'     => '_last_synced',
                    'value'   => date('Y-m-d', strtotime('-1 day')),
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
            ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
    }

    public function process_item($project_id): bool {
        $project = Project::get_instance($project_id);

        // Sync with external system
        $external_status = $this->fetch_external_status($project);
        $project->set_meta('_external_status', $external_status);
        $project->set_meta('_last_synced', current_time('mysql'));

        return true;
    }

    public function cron_condition(): bool {
        // Only run during business hours
        $hour = (int) date('G');
        return $hour >= 9 && $hour <= 17;
    }
}
```
