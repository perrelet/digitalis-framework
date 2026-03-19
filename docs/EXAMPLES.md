# Digitalis Framework Examples

For standard copy-paste patterns (models, views, forms, routes, admin, etc.) see [CHEATSHEET.md](./CHEATSHEET.md).

The examples below show real-world composite patterns that go beyond single-class snippets.

---

## Real-World Examples (digitalis-co Plugin)

The following examples are based on actual patterns used in the digitalis-co plugin.

### WooCommerce Account Dashboard

Create a modern account dashboard with widget grid:

```php
<?php
namespace Digitalis;

/**
 * Custom dashboard account page
 */
class Dashboard_Account_Page extends Woo_Account_Page {

    protected static $slug     = 'dashboard';
    protected static $title    = 'Dashboard';
    protected static $icon     = 'home';
    protected static $position = 5;

    public function render(): void {
        $user = User::current();
        $account = $user->get_account();

        // Get summary data
        $active_projects = count($account->get_projects(['status' => 'active']));
        $pending_orders = count($account->get_orders(['status' => 'estimate']));
        ?>
        <div class="account-dashboard">
            <h2>Welcome back, <?= esc_html($user->get_display_name()) ?></h2>

            <div class="widget-grid" style="--columns: 12">
                <?php
                // Projects widget
                Box_Link_Widget::render([
                    'title'    => 'Projects',
                    'icon'     => 'folder',
                    'href'     => wc_get_account_endpoint_url('projects'),
                    'progress' => ['current' => $active_projects, 'total' => 10],
                    'columns'  => 4,
                ]);

                // Orders widget
                Box_Link_Widget::render([
                    'title'    => 'Orders',
                    'icon'     => 'shopping-bag',
                    'href'     => wc_get_account_endpoint_url('orders'),
                    'badge'    => $pending_orders ? "{$pending_orders} pending" : null,
                    'columns'  => 4,
                ]);

                // Invoices widget
                Box_Link_Widget::render([
                    'title'   => 'Invoices',
                    'icon'    => 'file-text',
                    'href'    => wc_get_account_endpoint_url('invoices'),
                    'columns' => 4,
                ]);
                ?>
            </div>
        </div>
        <?php
    }
}
```

### Custom Order Status Workflow

Implement custom order statuses for an estimate/approval workflow:

```php
<?php
namespace Digitalis;

/**
 * Estimate order status
 */
class Order_Status_Estimate extends Order_Status {

    protected static $slug  = 'wc-estimate';
    protected static $label = 'Estimate';
    protected static $color = '#f0ad4e';

    // Status can be reached from these statuses
    protected static $valid_from = ['pending', 'on-hold'];

    // Status can transition to these statuses
    protected static $valid_to = ['approved', 'cancelled'];

    // Don't reduce stock for estimates
    protected static $reduce_stock = false;
}

/**
 * Handle estimate approval
 */
class Estimate_Approval extends Feature {

    public function get_hooks(): array {
        return [
            'woocommerce_order_status_estimate_to_approved' => 'on_approved',
        ];
    }

    public function on_approved($order_id): void {
        $order = Order::get_instance($order_id);
        $account = $order->get_account();
        $user = User::current();

        // Log who approved
        $order->add_order_note(sprintf(
            'Estimate approved by %s',
            $user->get_display_name()
        ));

        // Create project from approved estimate
        $project = Project::create([
            'post_title'  => 'Project #' . $order_id,
            'post_status' => 'publish',
        ]);

        // Link project to order and account
        $project->set_field('project_order', $order_id);
        $project->set_field('project_account', $account->get_id());

        // Trigger notification
        do_action('digitalis_estimate_approved', $order, $project);
    }
}
```

### Permission System

Implement granular permissions with `$user->can()`:

```php
<?php
namespace Digitalis;

/**
 * Extended User model with custom permissions
 */
class User extends \Digitalis\User {

    /**
     * Check custom capabilities
     */
    public function can(string $capability, $context = null): bool {
        switch ($capability) {
            // Account-level permissions
            case 'view_projects':
            case 'view_invoices':
                return $this->has_account();

            // Order-specific permissions
            case 'approve_estimate':
                return $this->can_approve_estimate($context);

            case 'view_invoice':
                return $this->owns_order($context);

            // Project permissions
            case 'edit_project':
                return $this->can_edit_project($context);

            // Admin permissions
            case 'manage_account':
                return $this->is_account_admin();

            default:
                return parent::can($capability, $context);
        }
    }

    private function can_approve_estimate($order_id): bool {
        if (!$order_id) return false;

        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'estimate') {
            return false;
        }

        // Must own the order or be account admin
        return $this->owns_order($order_id) || $this->is_account_admin();
    }

    private function owns_order($order_id): bool {
        $order = wc_get_order($order_id);
        if (!$order) return false;

        // Check direct ownership
        if ($order->get_customer_id() === $this->get_id()) {
            return true;
        }

        // Check account ownership
        $account = $this->get_account();
        if ($account) {
            $order_account_id = $order->get_meta('_account_id');
            return $order_account_id == $account->get_id();
        }

        return false;
    }

    public function get_account(): ?Account {
        $account_id = $this->get_field('user_account');
        return $account_id ? Account::get_instance($account_id) : null;
    }

    public function is_account_admin(): bool {
        return (bool) $this->get_field('is_account_admin');
    }
}
```

### REST API with HTMX

Create REST routes that return HTML for HTMX swapping:

```php
<?php
namespace Digitalis;

/**
 * Estimate approval route (HTMX compatible)
 */
class Approve_Estimate_Route extends Route {

    protected $namespace  = 'digitalis/v1';
    protected $route      = 'estimate/(?P<id>\d+)/approve';
    protected $definition = ['methods' => 'POST'];
    protected $view       = Order_Status_Badge::class;

    public function permission(\WP_REST_Request $request): bool {
        $user = User::current();
        $order_id = $request->get_param('id');
        return $user && $user->can('approve_estimate', $order_id);
    }

    public function handle(\WP_REST_Request $request): mixed {
        $order = Order::get_instance($request->get_param('id'));

        if ($order->get_status() !== 'estimate') {
            return new \WP_REST_Response([
                'error' => 'Order is not an estimate',
            ], 400);
        }

        // Update status
        $order->get_wc_order()->update_status('approved');

        // Return HTML for HTMX swap
        return Order_Status_Badge::render([
            'status' => 'approved',
            'label'  => 'Approved',
        ], false);
    }
}

/**
 * View for HTMX responses
 */
class Order_Status_Badge extends View {

    protected static $defaults = [
        'status' => '',
        'label'  => '',
    ];

    public function print(bool $return = false): string {
        $status = $this['status'];
        $label = $this['label'];

        $html = sprintf(
            '<span class="order-status order-status--%s">%s</span>',
            esc_attr($status),
            esc_html($label)
        );

        if ($return) return $html;
        echo $html;
        return '';
    }
}
```

**Frontend usage with HTMX:**

```html
<button
    hx-post="/wp-json/digitalis/v1/estimate/<?= $order->get_id() ?>/approve"
    hx-target="#order-status-<?= $order->get_id() ?>"
    hx-swap="outerHTML"
    class="button button-primary"
>
    Approve Estimate
</button>

<span id="order-status-<?= $order->get_id() ?>">
    <?php Order_Status_Badge::render(['status' => 'estimate', 'label' => 'Estimate']); ?>
</span>
```

### ACF Bidirectional Relationships

Automatically sync relationships between models:

```php
<?php
namespace Digitalis;

/**
 * Set up bidirectional relationships
 */
class Setup_Relationships extends Feature {

    public function run(): void {
        // Account <-> Projects relationship
        Bidirectional_Relationship::create([
            'field_a' => 'account_projects',    // Field on Account CPT
            'field_b' => 'project_account',     // Field on Project CPT
        ]);

        // Account <-> Users relationship
        Bidirectional_Relationship::create([
            'field_a' => 'account_users',       // Field on Account CPT
            'field_b' => 'user_account',        // Field on User (user_*)
        ]);

        // Project <-> Team Members relationship
        Bidirectional_Relationship::create([
            'field_a' => 'project_team',        // Field on Project CPT
            'field_b' => 'user_projects',       // Field on User (user_*)
        ]);
    }
}
```

**Usage in models:**

```php
<?php
namespace Digitalis;

class Account extends Post {
    protected static $post_type = 'account';

    // When you call this, project_account is auto-synced
    public function add_project(Project $project): void {
        $projects = $this->get_field('account_projects') ?: [];
        $projects[] = $project->get_id();
        $this->update_field('account_projects', array_unique($projects));
    }

    public function get_projects(array $filters = []): array {
        $project_ids = $this->get_field('account_projects') ?: [];
        $projects = Project::get_instances($project_ids);

        // Apply filters
        if (!empty($filters['status'])) {
            $projects = array_filter($projects, function($p) use ($filters) {
                return $p->get_meta('_status') === $filters['status'];
            });
        }

        return $projects;
    }
}

class Project extends Post {
    protected static $post_type = 'project';

    // This is auto-populated via bidirectional sync
    public function get_account(): ?Account {
        $account_id = $this->get_field('project_account');
        return $account_id ? Account::get_instance($account_id) : null;
    }
}
```

### Dashboard Widget Grid System

Create flexible dashboard layouts:

```php
<?php
namespace Digitalis;

/**
 * Widget grid container
 */
class Widget_Grid extends View {

    protected static $defaults = [
        'columns' => 12,
        'gap'     => '1.25rem',
        'widgets' => [],
    ];

    public function print(bool $return = false): string {
        ob_start();
        ?>
        <div class="widget-grid" style="--columns: <?= $this['columns'] ?>; --gap: <?= $this['gap'] ?>">
            <?php foreach ($this['widgets'] as $widget): ?>
                <?php
                $class = $widget['class'] ?? Box_Link_Widget::class;
                $class::render($widget['params'] ?? []);
                ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Box link widget with progress indicator
 */
class Box_Link_Widget extends View {

    protected static $template = 'widgets/box-link.php';

    protected static $defaults = [
        'title'    => '',
        'subtitle' => '',
        'icon'     => 'arrow-right',
        'href'     => '#',
        'columns'  => 4,
        'progress' => null,  // ['current' => 3, 'total' => 10]
        'badge'    => null,
    ];

    protected static $required = ['title', 'href'];
}
```

**Template file** (`templates/widgets/box-link.php`):

```php
<?php
/**
 * Box link widget template
 *
 * @var string $title
 * @var string $subtitle
 * @var string $icon
 * @var string $href
 * @var int $columns
 * @var array|null $progress
 * @var string|null $badge
 */
?>
<a href="<?= esc_url($href) ?>"
   class="box-link-widget"
   style="--span: <?= $columns ?>">

    <div class="box-link-widget__icon">
        <?= Iconoir::render($icon) ?>
    </div>

    <div class="box-link-widget__content">
        <h3 class="box-link-widget__title"><?= esc_html($title) ?></h3>

        <?php if ($subtitle): ?>
            <p class="box-link-widget__subtitle"><?= esc_html($subtitle) ?></p>
        <?php endif; ?>

        <?php if ($badge): ?>
            <span class="box-link-widget__badge"><?= esc_html($badge) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($progress): ?>
        <div class="box-link-widget__progress">
            <div class="progress-bar">
                <div class="progress-bar__fill"
                     style="width: <?= ($progress['current'] / $progress['total']) * 100 ?>%">
                </div>
            </div>
            <span class="progress-bar__label">
                <?= $progress['current'] ?> / <?= $progress['total'] ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="box-link-widget__arrow">
        <?= Iconoir::render('arrow-right') ?>
    </div>
</a>
```

---

## Quick Reference

### Common Patterns

```php
// Get single model
$event = Event::get_instance(123);

// Query multiple
$events = Event::query(['numberposts' => 10]);

// Create and save
$event = Event::create(['post_title' => 'New Event']);
$event->set_meta('_event_date', '2024-06-15');
$event->save();

// Render view
Event_Card::render(['event' => $event]);

// Get HTML string
$html = Event_Card::render(['event' => $event], false);

// Check custom permission
$user = User::current();
if ($user->can('approve_estimate', $order_id)) { ... }

// Get option with default
$value = get_option('my_option', 'default');

// Transient caching
$data = get_transient('my_cache');
if (!$data) {
    $data = expensive_operation();
    set_transient('my_cache', $data, HOUR_IN_SECONDS);
}

// ACF bidirectional relationship
Bidirectional_Relationship::create([
    'field_a' => 'account_projects',
    'field_b' => 'project_account',
]);
```
