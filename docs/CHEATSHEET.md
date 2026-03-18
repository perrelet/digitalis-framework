# Digitalis Framework Cheatsheet

Copy-paste patterns for common tasks.

---

## Table of Contents

- [Models](#models)
- [Views](#views)
- [Components](#components)
- [Forms](#forms)
- [Tables](#tables)
- [Admin](#admin)
- [REST Routes](#rest-routes)
- [WooCommerce](#woocommerce)
- [Queries](#queries)
- [Hooks](#hooks)

---

## Models

### Custom Post Type Model

**File:** `include/models/project.post.php`

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
}
```

### User Model Extension

**File:** `include/models/account.user.php`

```php
namespace Digitalis;

class Account extends User {

    protected static $role = 'account';

    public function get_company(): string {
        return $this->get_meta('company_name') ?: '';
    }

    public function get_projects(): array {
        return Project::query()
            ->where_meta('project_account', $this->get_id())
            ->get();
    }

    public function can(string $cap, $object_id = null): bool {
        switch ($cap) {
            case 'view_project':
                $project = Project::get_instance($object_id);
                return $project && $project->get_account()?->get_id() === $this->get_id();
            default:
                return parent::can($cap, $object_id);
        }
    }
}
```

### Term Model

**File:** `include/models/project-category.term.php`

```php
namespace Digitalis;

class Project_Category extends Term {

    protected static $taxonomy = 'project_category';

    public function get_projects(): array {
        return Project::query()
            ->where_tax($this->taxonomy, $this->get_id())
            ->get();
    }
}
```

### Get Model Instances

```php
// Single instance (auto-resolves to correct subclass)
$project = Post::get_instance($id);
$user = User::get_instance($id);
$term = Term::get_instance($id);

// Multiple instances
$projects = Project::get_instances([1, 2, 3]);

// Current user
$user = User::current();
$user = User::inst();  // Alias

// Force specific class (skip resolution)
$post = Post::get_instance($id, false);
```

---

## Views

### Basic View with DI

**File:** `include/views/invoice.view.php`

```php
namespace Digitalis;

class Invoice_View extends View {

    protected static $defaults = [
        'order' => Order::class,
        'user'  => User::class,
        'title' => 'Invoice',
    ];

    protected static $required = ['order'];

    public function condition() {
        return $this['order'] instanceof Order
            && $this['user']->can('view_invoice', $this['order']->get_id());
    }

    public function view() {
        ?>
        <div class="invoice">
            <h1><?= esc_html($this['title']) ?></h1>
            <p>Order #<?= $this['order']->get_id() ?></p>
            <p>Customer: <?= esc_html($this['user']->get_name()) ?></p>
        </div>
        <?php
    }
}
```

**Usage:**
```php
Invoice_View::render(['order' => 721, 'user' => 1]);

// Or
echo new Invoice_View(['order' => $order_id]);
```

### View with Template

**File:** `include/views/project-card.view.php`

```php
namespace Digitalis;

class Project_Card extends View {

    protected static $template = 'cards/project';
    protected static $template_path = THEME_PATH . 'templates/';

    protected static $defaults = [
        'project' => Project::class,
    ];

    protected static $required = ['project'];

    public function params(&$p) {
        $p['account'] = $p['project']->get_account();
        $p['status_class'] = 'status-' . $p['project']->get_status();
    }
}
```

**Template:** `templates/cards/project.php`

```php
<article class="project-card <?= $status_class ?>">
    <h3><?= esc_html($project->get_title()) ?></h3>
    <?php if ($account): ?>
        <p class="account"><?= esc_html($account->get_name()) ?></p>
    <?php endif; ?>
</article>
```

### View with Lifecycle Hooks

```php
class Widget extends View {

    protected static $defaults = [
        'wrapper' => true,
        'classes' => ['widget'],
    ];

    public function before_first() {
        wp_enqueue_style('widget-styles');
        wp_enqueue_script('widget-scripts');
    }

    public function before() {
        if ($this['wrapper']) {
            $classes = implode(' ', (array) $this['classes']);
            echo "<div class='{$classes}'>";
        }
    }

    public function after() {
        if ($this['wrapper']) {
            echo "</div>";
        }
    }
}
```

### Inheriting Views

```php
// Parent
class Card extends View {
    protected static $defaults = [
        'classes' => ['card'],
        'wrapper' => true,
    ];
    protected static $merge = ['classes'];
}

// Child - classes merge: ['card', 'project-card']
class Project_Card extends Card {
    protected static $defaults = [
        'project' => Project::class,
        'classes' => ['project-card'],
    ];
}
```

---

## Components

### Basic Component

```php
namespace Digitalis;

class Alert extends Component {

    protected static $defaults = [
        'tag'     => 'div',
        'classes' => ['alert'],
        'type'    => 'info',
        'message' => '',
    ];

    public function params(&$p) {
        parent::params($p);
        $this['element']->add_class("alert-{$p['type']}");
    }

    public function view() {
        echo $this['element']->open();
        echo esc_html($this['message']);
        echo $this['element']->close();
    }
}
```

**Usage:**
```php
echo new Alert(['type' => 'error', 'message' => 'Something went wrong']);
```

### Component with Sub-Elements

```php
class Card extends Component {

    protected static $elements = ['header', 'body', 'footer'];

    protected static $defaults = [
        'tag'            => 'article',
        'classes'        => ['card'],
        'title'          => '',
        'content'        => '',
        'footer_content' => '',
        'header_tag'     => 'header',
        'header_classes' => ['card-header'],
        'body_tag'       => 'div',
        'body_classes'   => ['card-body'],
        'footer_tag'     => 'footer',
        'footer_classes' => ['card-footer'],
    ];

    public function view() {
        echo $this['element']->open();

        if ($this['title']) {
            echo $this['header']->open();
            echo "<h3>" . esc_html($this['title']) . "</h3>";
            echo $this['header']->close();
        }

        echo $this['body']->open();
        echo $this['content'];
        echo $this['body']->close();

        if ($this['footer_content']) {
            echo $this['footer']->open();
            echo $this['footer_content'];
            echo $this['footer']->close();
        }

        echo $this['element']->close();
    }
}
```

### Link Component

```php
use Digitalis\Component\Link;

echo new Link([
    'href'    => '/dashboard',
    'content' => 'Go to Dashboard',
    'classes' => ['btn', 'btn-primary'],
    'attr'    => ['data-action' => 'navigate'],
]);
```

### HTMX Component

```php
use Digitalis\Component\HTMX;

echo new HTMX([
    'tag'         => 'button',
    'content'     => 'Load More',
    'hx-get'      => '/api/items?page=2',
    'hx-target'   => '#items-list',
    'hx-swap'     => 'beforeend',
    'hx-indicator'=> '.spinner',
]);
```

---

## Forms

### Complete Form

```php
use Digitalis\Component\Form;
use Digitalis\Field\Input;
use Digitalis\Field\Select;
use Digitalis\Field\Textarea;
use Digitalis\Field\Checkbox;
use Digitalis\Field\Submit;

echo new Form([
    'action' => admin_url('admin-post.php'),
    'method' => 'post',
    'fields' => [
        new Input([
            'name'        => 'email',
            'label'       => 'Email',
            'type'        => 'email',
            'required'    => true,
            'placeholder' => 'you@example.com',
        ]),
        new Input([
            'name'  => 'name',
            'label' => 'Full Name',
        ]),
        new Select([
            'name'    => 'country',
            'label'   => 'Country',
            'options' => [
                ''   => 'Select...',
                'us' => 'United States',
                'uk' => 'United Kingdom',
                'ca' => 'Canada',
            ],
        ]),
        new Textarea([
            'name'  => 'message',
            'label' => 'Message',
            'rows'  => 5,
        ]),
        new Checkbox([
            'name'  => 'subscribe',
            'label' => 'Subscribe to newsletter',
        ]),
        new Submit([
            'value' => 'Send Message',
        ]),
    ],
]);
```

### Individual Fields

```php
// Text input
echo new Input([
    'name'        => 'username',
    'label'       => 'Username',
    'value'       => $current_value,
    'placeholder' => 'Enter username',
    'required'    => true,
    'classes'     => ['custom-input'],
]);

// Select dropdown
echo new Select([
    'name'    => 'status',
    'label'   => 'Status',
    'options' => ['draft' => 'Draft', 'publish' => 'Published'],
    'value'   => 'draft',
]);

// Enhanced select with search
use Digitalis\Field\Select_Nice;
echo new Select_Nice([
    'name'    => 'user',
    'label'   => 'Assign User',
    'options' => $users_array,
]);

// Checkbox group
use Digitalis\Field\Checkbox_Group;
echo new Checkbox_Group([
    'name'    => 'features',
    'label'   => 'Features',
    'options' => ['a' => 'Feature A', 'b' => 'Feature B'],
    'value'   => ['a'],  // Pre-checked
]);

// Radio buttons
use Digitalis\Field\Radio;
echo new Radio([
    'name'    => 'priority',
    'label'   => 'Priority',
    'options' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
    'value'   => 'medium',
]);

// Date picker
use Digitalis\Field\Date_Picker;
echo new Date_Picker([
    'name'  => 'due_date',
    'label' => 'Due Date',
    'value' => date('Y-m-d'),
]);

// Hidden field
use Digitalis\Field\Hidden;
echo new Hidden([
    'name'  => 'action',
    'value' => 'process_form',
]);

// Number with min/max
use Digitalis\Field\Number;
echo new Number([
    'name'  => 'quantity',
    'label' => 'Quantity',
    'min'   => 1,
    'max'   => 100,
    'value' => 1,
]);
```

### Field Without Row Wrapper

```php
echo new Input([
    'name' => 'search',
    'row'  => false,  // No label/wrapper
]);
```

---

## Tables

### Basic Table

```php
use Digitalis\Component\Table;

echo new Table([
    'columns' => [
        'name'   => 'Name',
        'email'  => 'Email',
        'status' => 'Status',
    ],
    'data' => [
        ['name' => 'John', 'email' => 'john@example.com', 'status' => 'Active'],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'Pending'],
    ],
]);
```

### Table with Custom Cell Rendering

```php
echo new Table([
    'columns' => [
        'name'    => 'Project',
        'account' => 'Account',
        'status'  => 'Status',
    ],
    'data'    => $projects,
    'cells'   => [
        'name' => function($project) {
            return "<a href='{$project->get_edit_url()}'>{$project->get_title()}</a>";
        },
        'account' => function($project) {
            $account = $project->get_account();
            return $account ? $account->get_name() : '—';
        },
        'status' => function($project) {
            $status = $project->get_status();
            return "<span class='badge badge-{$status}'>{$status}</span>";
        },
    ],
]);
```

---

## Admin

### Admin Page

**File:** `include/admin/settings.admin-page.php`

```php
namespace Digitalis;

class Settings_Page extends Admin_Page {

    protected static $slug       = 'my-settings';
    protected static $page_title = 'Settings';
    protected static $menu_title = 'Settings';
    protected static $capability = 'manage_options';
    protected static $icon       = 'dashicons-admin-settings';
    protected static $position   = 30;

    public function render() {
        ?>
        <div class="wrap">
            <h1><?= esc_html(static::$page_title) ?></h1>
            <!-- Page content -->
        </div>
        <?php
    }
}
```

### Admin Sub Page

**File:** `include/admin/advanced-settings.admin-sub-page.php`

```php
namespace Digitalis;

class Advanced_Settings extends Admin_Sub_Page {

    protected static $parent     = Settings_Page::class;
    protected static $slug       = 'advanced-settings';
    protected static $page_title = 'Advanced Settings';
    protected static $menu_title = 'Advanced';

    public function render() {
        // ...
    }
}
```

### Posts Table with Custom Columns

**File:** `include/admin/projects-table.posts-table.php`

```php
namespace Digitalis;

class Projects_Table extends Posts_Table {

    protected $post_type = 'project';

    protected function get_columns() {
        return [
            'account' => 'Account',
            'status'  => 'Status',
        ];
    }

    public function column_account(Project $project) {
        $account = $project->get_account();
        if ($account) {
            echo "<a href='{$account->get_edit_url()}'>{$account->get_name()}</a>";
        } else {
            echo '—';
        }
    }

    public function column_status(Project $project) {
        echo "<span class='status-badge'>{$project->get_status()}</span>";
    }
}
```

---

## REST Routes

### Basic Route

**File:** `include/rest/projects.route.php`

```php
namespace Digitalis;

class Projects_Route extends Route {

    protected static $route  = 'projects';
    protected static $method = 'GET';

    public function permission(): bool {
        return User::inst()->can('view_projects');
    }

    public function callback(): array {
        $projects = Project::query()->limit(20)->get();

        return array_map(fn($p) => [
            'id'    => $p->get_id(),
            'title' => $p->get_title(),
        ], $projects);
    }
}
```

### Route with Parameters

**File:** `include/rest/project.route.php`

```php
namespace Digitalis;

class Project_Route extends Route {

    protected static $route  = 'projects/(?P<id>\d+)';
    protected static $method = 'GET';

    protected function get_params() {
        return [
            'id' => [
                'required' => true,
                'type'     => 'integer',
                'class'    => Project::class,  // Enable DI
            ],
        ];
    }

    // $project injected from route param 'id'
    public function permission(Project $project = null): bool {
        return $project && User::inst()->can('view_project', $project->get_id());
    }

    public function callback(Project $project = null): array {
        return $project->to_array();
    }
}
```

### POST Route

```php
class Create_Project_Route extends Route {

    protected static $route  = 'projects';
    protected static $method = 'POST';

    protected function get_params() {
        return [
            'title'   => ['required' => true, 'type' => 'string'],
            'account' => ['required' => true, 'type' => 'integer'],
        ];
    }

    public function permission(): bool {
        return User::inst()->can('create_projects');
    }

    public function callback(\WP_REST_Request $request): array {
        $project_id = wp_insert_post([
            'post_type'   => 'project',
            'post_title'  => $request->get_param('title'),
            'post_status' => 'publish',
        ]);

        update_field('project_account', $request->get_param('account'), $project_id);

        return ['id' => $project_id, 'success' => true];
    }
}
```

---

## WooCommerce

### Custom Account Page

**File:** `include/woocommerce/account-pages/projects.woo-account-page.php`

```php
namespace Digitalis;

class Projects_Account_Page extends Woo_Account_Page {

    protected static $slug     = 'projects';
    protected static $title    = 'Projects';
    protected static $icon     = 'folder';
    protected static $position = 15;

    public function can_access(): bool {
        return User::inst()->has_account();
    }

    public function render(): void {
        $projects = User::inst()->get_account()->get_projects();

        foreach ($projects as $project) {
            echo new Project_Card(['project' => $project]);
        }
    }
}
```

### Custom Order Status

**File:** `include/woocommerce/order-statuses/estimate.order-status.php`

```php
namespace Digitalis;

class Order_Status_Estimate extends Order_Status {

    protected static $slug       = 'wc-estimate';
    protected static $label      = 'Estimate';
    protected static $color      = '#f0ad4e';
    protected static $valid_from = ['pending', 'on-hold'];
    protected static $valid_to   = ['processing', 'cancelled'];
}
```

### Order Model Extension

**File:** `include/woocommerce/invoice.order.php`

```php
namespace Digitalis;

class Invoice extends Order {

    protected static $order_status = 'wc-invoice';

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
}
```

---

## Queries

### Post Queries

```php
// Basic query
$projects = Project::query()
    ->where_status('publish')
    ->limit(10)
    ->get();

// With meta
$projects = Project::query()
    ->where_meta('project_account', $account_id)
    ->where_status('publish')
    ->order_by('date', 'DESC')
    ->get();

// With taxonomy
$projects = Project::query()
    ->where_tax('project_category', $term_id)
    ->get();

// Complex query
$projects = Project::query()
    ->where_status(['publish', 'draft'])
    ->where_meta_query([
        'relation' => 'AND',
        ['key' => 'project_account', 'value' => $account_id],
        ['key' => 'project_priority', 'value' => 'high'],
    ])
    ->limit(20)
    ->offset(0)
    ->get();
```

### User Queries

```php
$accounts = Account::query()
    ->where_role('account')
    ->where_meta('company_name', 'Acme', 'LIKE')
    ->get();
```

### Direct WP_Query

```php
$query = new Digitalis_Query([
    'post_type'      => 'project',
    'posts_per_page' => 10,
    'meta_key'       => 'project_priority',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
]);

$projects = $query->get_posts();
```

---

## Hooks

### Feature with Hooks

**File:** `include/features/custom-emails.feature.php`

```php
namespace Digitalis;

class Custom_Emails extends Feature {

    public function get_hooks(): array {
        return [
            'woocommerce_order_status_changed' => 'on_status_change',
            'woocommerce_email_classes'        => 'register_emails',
        ];
    }

    public function on_status_change($order_id, $old_status, $new_status) {
        if ($new_status === 'estimate-approved') {
            $this->send_approval_email($order_id);
        }
    }

    public function register_emails($emails) {
        $emails['Estimate_Approved'] = new Estimate_Approved_Email();
        return $emails;
    }
}
```

### Integration (Singleton with Hooks)

**File:** `include/integrations/slack.integration.php`

```php
namespace Digitalis;

class Slack_Integration extends Integration {

    public function get_hooks(): array {
        return [
            'digitalis/project/created' => 'notify_project_created',
            'woocommerce_order_status_completed' => 'notify_order_completed',
        ];
    }

    public function notify_project_created(Project $project) {
        $this->send_message("New project: {$project->get_title()}");
    }
}
```

### Adding Custom Hooks

```php
// In your model
class Project extends Post {

    public function approve() {
        $this->set_status('approved');

        // Fire custom hook
        do_action('digitalis/project/approved', $this);
    }
}

// Listen elsewhere
add_action('digitalis/project/approved', function(Project $project) {
    // Handle approval
});
```

---

## File Naming Quick Reference

| Pattern | Meaning | Example |
|---------|---------|---------|
| `name.post.php` | Extends Post | `project.post.php` |
| `name.user.php` | Extends User | `account.user.php` |
| `name.term.php` | Extends Term | `category.term.php` |
| `name.view.php` | Extends View | `invoice.view.php` |
| `name.component.php` | Extends Component | `card.component.php` |
| `name.field.php` | Extends Field | `price.field.php` |
| `name.route.php` | Extends Route | `projects.route.php` |
| `name.feature.php` | Extends Feature | `emails.feature.php` |
| `name.singleton.php` | Extends Singleton | `settings.singleton.php` |
| `name.admin-page.php` | Extends Admin_Page | `dashboard.admin-page.php` |
| `name.woo-account-page.php` | Extends Woo_Account_Page | `projects.woo-account-page.php` |
