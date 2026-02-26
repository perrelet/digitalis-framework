# Digitalis Framework: Admin Reference

Reference for admin pages, tables, meta boxes, and ACF integration.

---

## Table of Contents

- [Admin Pages](#admin-pages)
- [Admin Sub Pages](#admin-sub-pages)
- [Commands Page](#commands-page)
- [Logs Page](#logs-page)
- [Posts Table](#posts-table)
- [Users Table](#users-table)
- [Terms Table](#terms-table)
- [Meta Boxes](#meta-boxes)
- [ACF Field Groups](#acf-field-groups)
- [ACF Blocks](#acf-blocks)
- [ACF Options Pages](#acf-options-pages)
- [Quick Reference](#quick-reference)

---

## Admin Pages

Create top-level admin menu pages.

### Basic Admin Page

**File:** `include/admin/settings.admin-page.php`

```php
namespace Digitalis;

class Settings_Page extends Admin_Page {

    protected $slug       = 'my-settings';
    protected $title      = 'My Settings';
    protected $menu_title = 'Settings';
    protected $capability = 'manage_options';
    protected $icon       = 'dashicons-admin-settings';
    protected $position   = 30;

    public function callback() {
        ?>
        <h1><?= esc_html($this->title) ?></h1>
        <p>Settings page content here.</p>
        <?php
    }
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$slug` | `string` | `'admin-menu-page'` | URL slug and menu ID |
| `$title` | `string` | `'Page Title'` | Browser/page title |
| `$menu_title` | `string` | `'Menu Title'` | Sidebar menu label |
| `$capability` | `string` | `'manage_options'` | Required capability |
| `$icon` | `string` | `'dashicons-marker'` | Dashicon or URL |
| `$position` | `int\|null` | `null` | Menu position |

### Methods

| Method | Description |
|--------|-------------|
| `callback()` | Override to render page content |
| `get_url($blog_id)` | Get admin page URL |

`Admin_Page` is a Factory cached by `$slug`, so `Admin_Page::get_instance('my-settings')` returns the registered instance.

### With View

```php
class Dashboard_Page extends Admin_Page {

    protected $slug  = 'dashboard';
    protected $title = 'Dashboard';

    public function callback() {
        Dashboard_View::render([
            'user'  => User::current(),
            'stats' => $this->get_stats(),
        ]);
    }

    private function get_stats() {
        return [
            'projects' => Project::query(['posts_per_page' => -1])->found_posts,
            'orders'   => Order::query(['limit' => -1])->total,
        ];
    }
}
```

### With ACF Options

```php
class Settings_Page extends Admin_Page {

    protected $slug       = 'theme-settings';
    protected $title      = 'Theme Settings';
    protected $capability = 'manage_options';

    public function __construct() {
        parent::__construct();

        // Register ACF options page attached to this menu
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page([
                'page_title'  => $this->title,
                'menu_slug'   => $this->slug,
                'parent_slug' => $this->slug,  // Use same slug to integrate
                'capability'  => $this->capability,
            ]);
        }
    }

    public function callback() {
        // ACF renders the form automatically
    }
}
```

---

## Admin Sub Pages

Create submenu pages under a parent menu.

### Basic Sub Page

**File:** `include/admin/advanced-settings.admin-sub-page.php`

```php
namespace Digitalis;

class Advanced_Settings extends Admin_Sub_Page {

    protected $parent     = 'my-settings';  // Parent slug
    protected $slug       = 'advanced-settings';
    protected $title      = 'Advanced Settings';
    protected $menu_title = 'Advanced';
    protected $capability = 'manage_options';
    protected $position   = 10;

    public function callback() {
        ?>
        <h1><?= esc_html($this->title) ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('advanced_settings_group');
            do_settings_sections('advanced-settings');
            submit_button();
            ?>
        </form>
        <?php
    }
}
```

### Using Parent Class Reference

```php
class Import_Page extends Admin_Sub_Page {

    protected $parent     = Settings_Page::class;  // Reference parent class
    protected $slug       = 'import';
    protected $title      = 'Import Data';
    protected $menu_title = 'Import';

    // Parent resolves to Settings_Page::$slug automatically
}
```

### Under WordPress Core Menus

```php
// Under Tools
class My_Tool extends Admin_Sub_Page {
    protected $parent = 'tools.php';
    protected $slug   = 'my-tool';
}

// Under Settings
class My_Settings extends Admin_Sub_Page {
    protected $parent = 'options-general.php';
    protected $slug   = 'my-settings';
}

// Under Users
class User_Reports extends Admin_Sub_Page {
    protected $parent = 'users.php';
    protected $slug   = 'user-reports';
}

// Under WooCommerce
class WC_Reports extends Admin_Sub_Page {
    protected $parent = 'woocommerce';
    protected $slug   = 'custom-reports';
}
```

---

## Commands Page

A specialized `Admin_Sub_Page` for running arbitrary PHP commands from the WP admin. Extend it to create a developer tools or maintenance page.

**Location:** `include/admin/commands-page.abstract.php`

```php
namespace Digitalis;

class Dev_Commands_Page extends Commands_Page {

    protected $parent     = 'tools.php';
    protected $slug       = 'dev-commands';
    protected $title      = 'Developer Commands';
    protected $menu_title = 'Dev Commands';
    protected $capability = 'manage_options';

    public function get_commands() {
        return [
            'clear_transients' => [
                'label'  => 'Clear Transients',
                'call'   => [$this, 'run_clear_transients'],
                'fields' => [],
            ],
            'sync_accounts' => [
                'label'  => 'Sync Accounts',
                'call'   => [$this, 'run_sync_accounts'],
                'fields' => [
                    'limit' => ['label' => 'Limit', 'type' => 'number', 'default' => 100],
                ],
            ],
        ];
    }

    public function run_clear_transients() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
    }

    public function run_sync_accounts($limit = 100) {
        // ...
    }
}
```

### Nonce Verification

Commands_Page automatically injects a `_wpnonce` hidden field into the command form and verifies it on submission. You do not need to add nonce handling yourself — commands that fail the nonce check are rejected with an admin notice.

### Command Definition

```php
'command_key' => [
    'label'  => 'Human-readable label',  // Displayed in the UI
    'call'   => callable,                // Function/method to call
    'fields' => [                        // Input fields (optional)
        'field_name' => [
            'label'   => 'Field Label',
            'type'    => 'text',         // Standard HTML input type
            'default' => '',
        ],
    ],
]
```

Field values passed to `$call` are sanitized with `sanitize_text_field()` before use.

---

## Logs Page

A specialized `Admin_Sub_Page` for viewing and clearing log files in the WP admin. Uses the `Log` service for file reading.

**Location:** `include/admin/logs-page.abstract.php`

```php
namespace Digitalis;

class Plugin_Logs_Page extends Logs_Page {

    protected $parent     = 'tools.php';
    protected $slug       = 'plugin-logs';
    protected $title      = 'Plugin Logs';
    protected $menu_title = 'Logs';
    protected $capability = 'manage_options';

    public function get_logs() {
        return [
            [
                'name'     => 'Application Log',
                'slug'     => 'app',
                'instance' => Log::get_instance('app.log'),
            ],
            [
                'name'     => 'Error Log',
                'slug'     => 'error',
                'path'     => ini_get('error_log'),
            ],
        ];
    }
}
```

### Log Definition

```php
[
    'name'        => 'Display Name',
    'slug'        => 'unique-slug',     // Used in query string
    'path'        => '/abs/path.log',   // Direct path (or use instance)
    'instance'    => Log::get_instance(), // Log instance (preferred)
    'theme'       => ['basic'],          // Syntax highlighting theme(s)
    'permissions' => [],                 // Additional cap checks
]
```

The page renders the log tail with pagination. The `theme` values are validated against the page's `get_syntax_rules()` allowlist — unrecognised values are ignored.

---

## Posts Table

Customize the admin posts list table with columns and filters.

### Basic Posts Table

**File:** `include/admin/projects-table.posts-table.php`

```php
namespace Digitalis;

class Projects_Table extends Posts_Table {

    protected $post_type = 'project';

    public function columns(&$columns) {
        // Insert after title
        $this->insert_column(['account' => 'Account'], 'title');
        $this->insert_column(['status' => 'Status'], 'account');

        // Remove date column
        $this->remove_column('date');

        // Append at end
        $this->append_column(['priority' => 'Priority']);
    }

    public function sortable(&$columns) {
        $columns['account']  = 'account';
        $columns['priority'] = 'priority';
    }

    // Column renderers: column_{name}
    // Receives model instance via DI
    public function column_account(Project $project) {
        if ($account = $project->get_account()) {
            $url = $account->get_edit_url();
            echo "<a href='{$url}'>{$account->get_name()}</a>";
        } else {
            echo '—';
        }
    }

    public function column_status(Project $project) {
        $status = $project->get_field('project_status');
        echo "<span class='status-badge status-{$status}'>{$status}</span>";
    }

    public function column_priority(Project $project) {
        echo esc_html($project->get_field('priority') ?: 'Normal');
    }

    // Sort handlers: sort_column_{name}
    public function sort_column_account(Query_Vars $qv) {
        $qv->set_var('meta_key', 'project_account');
        $qv->set_var('orderby', 'meta_value_num');
    }

    public function sort_column_priority(Query_Vars $qv) {
        $qv->set_var('meta_key', 'priority');
        $qv->set_var('orderby', 'meta_value');
    }
}
```

### Column Methods

| Method | Description |
|--------|-------------|
| `columns(&$columns)` | Modify columns array |
| `sortable(&$columns)` | Define sortable columns |
| `column_{name}($model)` | Render column content (DI-enabled) |
| `sort_column_{name}($qv)` | Handle sorting for column |

### Column Helpers

```php
// Insert after a specific column
$this->insert_column(['new_col' => 'Label'], 'title');

// Insert before a specific column
$this->insert_column(['new_col' => 'Label'], 'title', false);

// Insert at position (0-indexed)
$this->insert_column(['new_col' => 'Label'], 2);

// Append to end
$this->append_column(['new_col' => 'Label']);
$this->append_column('new_col', 'Label');  // Alternative

// Prepend at start
$this->prepend_column(['new_col' => 'Label']);

// Remove column
$this->remove_column('date');
$this->remove_column('comments');
```

### Row Actions

Override `row_actions()` to add or modify the inline action links on each row. The post model is injected via DI.

```php
class Projects_Table extends Posts_Table {

    protected $post_type = 'project';

    public function row_actions (&$actions, Project $project) {
        $actions['archive'] = sprintf(
            '<a href="%s">Archive</a>',
            esc_url(add_query_arg([
                'action'     => 'archive_project',
                'project_id' => $project->get_id(),
                '_wpnonce'   => wp_create_nonce('archive_project_' . $project->get_id()),
            ], admin_url('admin-post.php')))
        );

        // Optionally remove an existing action
        unset($actions['trash']);
    }
}
```

`$actions` is passed by reference, so you can add, modify, or remove entries in place. The `row_actions_wrap()` method is hooked to `post_row_actions` (or `page_row_actions` for the `page` post type) automatically.

### Table Filters

Add dropdown filters above the table.

```php
class Projects_Table extends Posts_Table {

    protected $post_type = 'project';
    protected $filters = [
        'project_category' => 'taxonomy',           // Taxonomy dropdown
        'project_status'   => 'acf',                // ACF field dropdown
        'project_account'  => [                      // ACF with options
            'type'             => 'acf',
            'null_label'       => 'All Accounts',
            'null_label_prefix' => 'Filter by ',
        ],
    ];

    // Or via method
    public function filters() {
        return [
            'priority' => [
                'type'           => 'meta',
                'args'           => [
                    'field'      => \Digitalis\Field\Select::class,
                    'options'    => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                    'placeholder' => 'Priority',
                ],
                'query_callback' => [$this, 'filter_by_priority'],
            ],
        ];
    }

    public function filter_by_priority(Query_Vars $qv, $filter) {
        if ($filter['args']['value']) {
            $qv->add_meta_query([
                'key'     => 'priority',
                'value'   => $filter['args']['value'],
                'compare' => '=',
            ]);
        }
        return $qv;
    }
}
```

### Filter Types

| Type | Description |
|------|-------------|
| `taxonomy` | WordPress taxonomy dropdown |
| `acf` | ACF field-based filter (uses field choices) |
| `meta` | Custom meta field filter |
| `field` | Generic field filter |

### Filter Options

```php
'field_name' => [
    'type'              => 'acf',           // taxonomy, acf, meta, field
    'name'              => 'field_name',    // Defaults to key
    'null_label'        => 'All Items',     // Empty option label
    'null_label_prefix' => 'Filter by ',    // Prefix for null label
    'null_value'        => '',              // Value for empty option
    'select_nice'       => false,           // Use Select_Nice component
    'value_callback'    => null,            // Transform selected value
    'query_callback'    => null,            // Custom query modifier
    'compare'           => '=',             // Meta compare operator
]
```

---

## Users Table

Customize the admin users list table.

### Basic Users Table

**File:** `include/admin/accounts-table.users-table.php`

```php
namespace Digitalis;

class Accounts_Table extends Users_Table {

    public function columns(&$columns) {
        $this->insert_column(['company' => 'Company'], 'email');
        $this->insert_column(['projects' => 'Projects'], 'company');
    }

    public function column_company($output, User $user) {
        return esc_html($user->get_meta('company_name') ?: '—');
    }

    public function column_projects($output, User $user) {
        $count = count(Project::query([
            'meta_key'   => 'project_account',
            'meta_value' => $user->get_id(),
        ]));
        return "<span class='count'>{$count}</span>";
    }
}
```

### Row Actions

Same as Posts Table. Override `row_actions()` — the user model is injected via DI. Hooked to `user_row_actions`.

```php
class Accounts_Table extends Users_Table {

    public function row_actions (&$actions, Account $user) {
        $actions['impersonate'] = sprintf(
            '<a href="%s">Login as user</a>',
            esc_url(add_query_arg(['user_id' => $user->get_id()], admin_url('admin-post.php')))
        );
    }
}
```

### Users Table Filters

```php
class Accounts_Table extends Users_Table {

    protected $filters = [
        'account_status' => 'acf',
        'user_taxonomy'  => 'taxonomy',
    ];

    public function filters() {
        return [
            'has_projects' => [
                'type'           => 'field',
                'args'           => [
                    'field'   => \Digitalis\Field\Select::class,
                    'key'     => 'has_projects',
                    'options' => ['' => 'All', '1' => 'Has Projects', '0' => 'No Projects'],
                ],
                'query_callback' => [$this, 'filter_has_projects'],
            ],
        ];
    }

    public function filter_has_projects(Query_Vars $qv) {
        $value = $_GET['has_projects'] ?? '';
        if ($value === '1') {
            $qv->add_meta_query([
                'key'     => 'project_count',
                'value'   => 0,
                'compare' => '>',
            ]);
        } elseif ($value === '0') {
            $qv->add_meta_query([
                'key'     => 'project_count',
                'value'   => 0,
                'compare' => '=',
            ]);
        }
    }
}
```

---

## Terms Table

Customize taxonomy term list tables.

**File:** `include/admin/categories-table.terms-table.php`

```php
namespace Digitalis;

class Categories_Table extends Terms_Table {

    protected $slug = 'category';  // Taxonomy slug

    public function columns(&$columns) {
        $this->insert_column(['icon' => 'Icon'], 'name');
    }

    public function column_icon($output, $column, $term_id) {
        $icon = get_field('category_icon', 'term_' . $term_id);
        return $icon ? "<i class='icon-{$icon}'></i>" : '—';
    }
}
```

---

## Meta Boxes

Add custom meta boxes to post edit screens.

### Basic Meta Box

**File:** `include/admin/project-details.meta-box.php`

```php
namespace Digitalis;

class Project_Details_Box extends Meta_Box {

    protected $id       = 'project-details';
    protected $title    = 'Project Details';
    protected $screen   = 'project';           // Post type
    protected $context  = 'side';              // normal, side, advanced
    protected $priority = 'high';              // high, core, default, low

    public function render($post, $args) {
        $project = Project::get_instance($post->ID);
        ?>
        <p><strong>Account:</strong> <?= esc_html($project->get_account()?->get_name() ?: '—') ?></p>
        <p><strong>Status:</strong> <?= esc_html($project->get_field('project_status')) ?></p>
        <p><strong>Created:</strong> <?= esc_html($project->get_date('F j, Y')) ?></p>
        <?php
    }
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$id` | `string` | `'digitalis-metabox'` | Unique ID |
| `$title` | `string` | `'Digitalis Metabox'` | Box title |
| `$screen` | `string\|array\|null` | `null` | Post type(s) or screen |
| `$context` | `string` | `'advanced'` | Position: normal, side, advanced |
| `$priority` | `string` | `'default'` | Priority: high, core, default, low |

### Conditional Meta Box

```php
class Premium_Box extends Meta_Box {

    protected $id     = 'premium-options';
    protected $title  = 'Premium Options';
    protected $screen = 'product';

    public function condition() {
        global $post;
        if (!$post) return false;

        // Only show for premium products
        $product = Product::get_instance($post->ID);
        return $product && $product->is_premium();
    }

    public function render($post, $args) {
        // ...
    }
}
```

### Meta Box with View

```php
class Stats_Box extends Meta_Box {

    protected $id     = 'project-stats';
    protected $title  = 'Statistics';
    protected $screen = 'project';
    protected $view   = Project_Stats_View::class;

    // View receives ($post, $args) as params
}
```

### Multiple Screens

```php
class Notes_Box extends Meta_Box {

    protected $id     = 'internal-notes';
    protected $title  = 'Internal Notes';
    protected $screen = ['project', 'document', 'invoice'];

    public function render($post, $args) {
        $notes = get_post_meta($post->ID, '_internal_notes', true);
        ?>
        <textarea name="internal_notes" style="width:100%"><?= esc_textarea($notes) ?></textarea>
        <?php
    }
}
```

---

## ACF Field Groups

Register ACF field groups programmatically.

### Field Group Feature

**File:** `include/acf/project-fields.feature.php`

```php
namespace Digitalis;

class Project_Fields extends Feature {

    public function run() {
        add_action('acf/include_fields', [$this, 'register_fields']);
    }

    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key'      => 'group_project_details',
            'title'    => 'Project Details',
            'fields'   => $this->get_fields(),
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'project',
                    ],
                ],
            ],
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    protected function get_fields() {
        return [
            [
                'key'   => 'field_project_account',
                'label' => 'Account',
                'name'  => 'project_account',
                'type'  => 'post_object',
                'post_type' => ['account'],
                'return_format' => 'id',
            ],
            [
                'key'   => 'field_project_status',
                'label' => 'Status',
                'name'  => 'project_status',
                'type'  => 'select',
                'choices' => [
                    'draft'    => 'Draft',
                    'active'   => 'Active',
                    'complete' => 'Complete',
                ],
                'default_value' => 'draft',
            ],
            [
                'key'   => 'field_project_deadline',
                'label' => 'Deadline',
                'name'  => 'project_deadline',
                'type'  => 'date_picker',
                'return_format' => 'Y-m-d',
            ],
        ];
    }
}
```

### Loading from App

Create a centralized place to load all ACF configurations.

**File:** `include/acf/acf-config.feature.php`

```php
namespace Digitalis;

class ACF_Config extends Feature {

    public function run() {
        add_action('acf/include_fields', [$this, 'include_fields']);
    }

    public function include_fields() {
        if (!function_exists('acf_add_local_field_group')) return;

        // Load all field groups
        $this->register_project_fields();
        $this->register_account_fields();
        $this->register_settings_fields();
    }

    protected function register_project_fields() {
        acf_add_local_field_group([
            'key'      => 'group_project',
            'title'    => 'Project Details',
            'fields'   => [
                $this->field_post_object('project_account', 'Account', ['account']),
                $this->field_select('project_status', 'Status', [
                    'draft'  => 'Draft',
                    'active' => 'Active',
                ]),
                $this->field_date('project_deadline', 'Deadline'),
            ],
            'location' => $this->location_post_type('project'),
        ]);
    }

    protected function register_account_fields() {
        acf_add_local_field_group([
            'key'      => 'group_account',
            'title'    => 'Account Details',
            'fields'   => [
                $this->field_text('company_name', 'Company Name'),
                $this->field_email('billing_email', 'Billing Email'),
                $this->field_image('company_logo', 'Logo'),
            ],
            'location' => $this->location_user_role('account'),
        ]);
    }

    protected function register_settings_fields() {
        acf_add_local_field_group([
            'key'      => 'group_settings',
            'title'    => 'Site Settings',
            'fields'   => [
                $this->field_text('company_name', 'Company Name'),
                $this->field_textarea('company_address', 'Address'),
            ],
            'location' => $this->location_options_page('theme-settings'),
        ]);
    }

    // Field helpers

    protected function field_text($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'text',
        ], $args);
    }

    protected function field_textarea($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'textarea',
            'rows'  => 4,
        ], $args);
    }

    protected function field_email($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'email',
        ], $args);
    }

    protected function field_select($name, $label, $choices, $args = []) {
        return array_merge([
            'key'     => "field_{$name}",
            'label'   => $label,
            'name'    => $name,
            'type'    => 'select',
            'choices' => $choices,
        ], $args);
    }

    protected function field_date($name, $label, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'date_picker',
            'return_format' => 'Y-m-d',
        ], $args);
    }

    protected function field_image($name, $label, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'image',
            'return_format' => 'id',
        ], $args);
    }

    protected function field_post_object($name, $label, $post_types, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'post_object',
            'post_type'     => $post_types,
            'return_format' => 'id',
        ], $args);
    }

    protected function field_repeater($name, $label, $sub_fields, $args = []) {
        return array_merge([
            'key'        => "field_{$name}",
            'label'      => $label,
            'name'       => $name,
            'type'       => 'repeater',
            'layout'     => 'table',
            'sub_fields' => $sub_fields,
        ], $args);
    }

    // Location helpers

    protected function location_post_type($post_type) {
        return [[['param' => 'post_type', 'operator' => '==', 'value' => $post_type]]];
    }

    protected function location_user_role($role) {
        return [[['param' => 'user_role', 'operator' => '==', 'value' => $role]]];
    }

    protected function location_options_page($page_slug) {
        return [[['param' => 'options_page', 'operator' => '==', 'value' => $page_slug]]];
    }

    protected function location_block($block_name) {
        return [[['param' => 'block', 'operator' => '==', 'value' => "acf/{$block_name}"]]];
    }
}
```

---

## ACF Blocks

Create Gutenberg blocks with ACF fields.

### Basic Block

**File:** `include/blocks/testimonial.acf-block.php`

```php
namespace Digitalis;

class Testimonial_Block extends ACF_Block {

    protected $slug = 'testimonial';
    protected $view = Testimonial_View::class;

    protected $block = [
        'title'       => 'Testimonial',
        'description' => 'Display a customer testimonial',
        'category'    => 'common',
        'icon'        => 'format-quote',
        'keywords'    => ['quote', 'review', 'testimonial'],
    ];

    protected $fields = [
        'quote' => [
            'label' => 'Quote',
            'type'  => 'textarea',
            'rows'  => 3,
        ],
        'author' => [
            'label' => 'Author Name',
            'type'  => 'text',
        ],
        'company' => [
            'label' => 'Company',
            'type'  => 'text',
        ],
        'image' => [
            'label'         => 'Photo',
            'type'          => 'image',
            'return_format' => 'id',
        ],
    ];
}
```

### Block with Custom Render

```php
class CTA_Block extends ACF_Block {

    protected $slug = 'cta';

    protected $block = [
        'title'    => 'Call to Action',
        'category' => 'common',
        'icon'     => 'megaphone',
    ];

    protected $fields = [
        'heading'     => 'Heading',
        'description' => ['label' => 'Description', 'type' => 'textarea'],
        'button_text' => 'Button Text',
        'button_url'  => ['label' => 'Button URL', 'type' => 'url'],
    ];

    public function view($params) {
        return "
            <div class='cta-block'>
                <h2>{$params['heading']}</h2>
                <p>{$params['description']}</p>
                <a href='{$params['button_url']}' class='btn'>{$params['button_text']}</a>
            </div>
        ";
    }
}
```

### Block Properties

| Property | Description |
|----------|-------------|
| `$slug` | Block identifier (acf/slug) |
| `$view` | View class for rendering |
| `$block` | Block registration args |
| `$fields` | ACF field definitions |
| `$defaults` | Default values for view params |

---

## ACF Options Pages

Create settings pages with ACF fields.

### Standalone Options Page

**File:** `include/acf/theme-options.feature.php`

```php
namespace Digitalis;

class Theme_Options extends Feature {

    public function run() {
        if (!function_exists('acf_add_options_page')) return;

        acf_add_options_page([
            'page_title' => 'Theme Settings',
            'menu_title' => 'Theme Settings',
            'menu_slug'  => 'theme-settings',
            'capability' => 'manage_options',
            'position'   => 30,
            'icon_url'   => 'dashicons-admin-customizer',
        ]);

        acf_add_options_sub_page([
            'page_title'  => 'Header Settings',
            'menu_title'  => 'Header',
            'parent_slug' => 'theme-settings',
        ]);

        acf_add_options_sub_page([
            'page_title'  => 'Footer Settings',
            'menu_title'  => 'Footer',
            'parent_slug' => 'theme-settings',
        ]);

        add_action('acf/include_fields', [$this, 'register_fields']);
    }

    public function register_fields() {
        // Header fields
        acf_add_local_field_group([
            'key'      => 'group_header_options',
            'title'    => 'Header Options',
            'fields'   => [
                ['key' => 'field_logo', 'label' => 'Logo', 'name' => 'site_logo', 'type' => 'image'],
                ['key' => 'field_phone', 'label' => 'Phone', 'name' => 'header_phone', 'type' => 'text'],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-header']]],
        ]);

        // Footer fields
        acf_add_local_field_group([
            'key'      => 'group_footer_options',
            'title'    => 'Footer Options',
            'fields'   => [
                ['key' => 'field_copyright', 'label' => 'Copyright', 'name' => 'footer_copyright', 'type' => 'text'],
                ['key' => 'field_social', 'label' => 'Social Links', 'name' => 'social_links', 'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_platform', 'label' => 'Platform', 'name' => 'platform', 'type' => 'text'],
                        ['key' => 'field_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                    ],
                ],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-footer']]],
        ]);
    }
}
```

### Reading Options

```php
// Get option field
$logo = get_field('site_logo', 'option');
$phone = get_field('header_phone', 'option');

// In a View
class Header_View extends View {

    protected static $defaults = [
        'logo'  => null,
        'phone' => null,
    ];

    public function params(&$p) {
        $p['logo']  = $p['logo'] ?: get_field('site_logo', 'option');
        $p['phone'] = $p['phone'] ?: get_field('header_phone', 'option');
    }
}
```

---

## Quick Reference

### File Naming

| Pattern | Class Type |
|---------|------------|
| `name.admin-page.php` | Admin_Page |
| `name.admin-sub-page.php` | Admin_Sub_Page |
| `name.posts-table.php` | Posts_Table |
| `name.users-table.php` | Users_Table |
| `name.terms-table.php` | Terms_Table |
| `name.meta-box.php` | Meta_Box |
| `name.acf-block.php` | ACF_Block |

> Admin_Page instances are cached by `$slug`; Meta_Box instances by `$id`. Both support `::get_instance()` lookup.

### Admin Page Properties

```php
protected $slug       = 'page-slug';
protected $title      = 'Page Title';
protected $menu_title = 'Menu Title';
protected $capability = 'manage_options';
protected $icon       = 'dashicons-admin-generic';
protected $position   = 30;
```

### Posts Table Column Methods

```php
public function columns(&$columns) { }           // Modify columns
public function sortable(&$columns) { }          // Mark sortable
public function column_{name}($model) { }        // Render cell
public function sort_column_{name}($qv) { }      // Sort handler
```

### Meta Box Properties

```php
protected $id       = 'box-id';
protected $title    = 'Box Title';
protected $screen   = 'post_type';  // or ['type1', 'type2']
protected $context  = 'normal';     // normal, side, advanced
protected $priority = 'default';    // high, core, default, low
```

### ACF Location Patterns

```php
// Post type
[['param' => 'post_type', 'operator' => '==', 'value' => 'project']]

// User role
[['param' => 'user_role', 'operator' => '==', 'value' => 'editor']]

// Options page
[['param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings']]

// Block
[['param' => 'block', 'operator' => '==', 'value' => 'acf/my-block']]

// Taxonomy term
[['param' => 'taxonomy', 'operator' => '==', 'value' => 'category']]
```
