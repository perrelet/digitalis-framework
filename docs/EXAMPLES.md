# Digitalis Framework Examples

Practical examples for common development patterns using the Digitalis Framework.

---

## Table of Contents

- [Getting Started](#getting-started)
- [Models](#models)
- [Custom Post Types](#custom-post-types)
- [Custom Taxonomies](#custom-taxonomies)
- [Views & Templates](#views--templates)
- [Form Fields](#form-fields)
- [Features & Hooks](#features--hooks)
- [Admin Pages](#admin-pages)
- [REST API Routes](#rest-api-routes)
- [Iterators (Batch Processing)](#iterators-batch-processing)
- [WooCommerce Integration](#woocommerce-integration)
- [ACF Integration](#acf-integration)

---

## Getting Started

### Plugin Bootstrap

Create your main plugin file:

```php
<?php
/**
 * Plugin Name: My Custom Plugin
 * Description: Example plugin using Digitalis Framework
 * Version: 1.0.0
 */

namespace My_Plugin;

use Digitalis\App;

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Load Digitalis Framework
require_once WP_PLUGIN_DIR . '/digitalis-co/framework/load.php';

/**
 * Main plugin class
 */
class Plugin extends App {

    public function __construct() {
        $this->path = plugin_dir_path(__FILE__);
        $this->url = plugin_dir_url(__FILE__);

        // Initialize
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void {
        // Autoload all classes in /includes
        $this->autoload($this->path . 'includes');

        // Autoload features with instantiation
        $this->autoload($this->path . 'features', function($class) {
            if (is_subclass_of($class, \Digitalis\Feature::class)) {
                return $class::create();
            }
        });
    }
}

// Boot the plugin
new Plugin();
```

---

## Models

### Basic Post Model

```php
<?php
namespace My_Plugin\Models;

use Digitalis\Post;

/**
 * Event model - represents event posts
 */
class Event extends Post {

    // Restrict to 'event' post type
    protected static $post_type = 'event';

    // Only published events
    protected static $post_status = 'publish';

    /**
     * Get event start date
     */
    public function get_start_date(): ?string {
        return $this->get_meta('_event_start_date');
    }

    /**
     * Get event end date
     */
    public function get_end_date(): ?string {
        return $this->get_meta('_event_end_date');
    }

    /**
     * Get event location
     */
    public function get_location(): string {
        return $this->get_meta('_event_location') ?: '';
    }

    /**
     * Check if event is upcoming
     */
    public function is_upcoming(): bool {
        $start = $this->get_start_date();
        return $start && strtotime($start) > time();
    }

    /**
     * Get upcoming events
     */
    public static function get_upcoming(int $limit = 10): array {
        return static::query([
            'meta_key'     => '_event_start_date',
            'meta_value'   => date('Y-m-d'),
            'meta_compare' => '>=',
            'orderby'      => 'meta_value',
            'order'        => 'ASC',
            'numberposts'  => $limit,
        ]);
    }
}
```

### Usage

```php
use My_Plugin\Models\Event;

// Get single event
$event = Event::get_instance(123);
echo $event->get_title();
echo $event->get_start_date();

// Query events
$upcoming = Event::get_upcoming(5);
foreach ($upcoming as $event) {
    echo $event->get_title() . ' - ' . $event->get_location();
}

// Create new event
$event = Event::create([
    'post_title'  => 'Annual Conference',
    'post_status' => 'publish',
]);
$event->set_meta('_event_start_date', '2024-06-15');
$event->save();
```

### User Model

```php
<?php
namespace My_Plugin\Models;

use Digitalis\User;

/**
 * Member model - represents premium members
 */
class Member extends User {

    // Only users with 'member' role
    protected static $role = 'member';

    /**
     * Get membership expiry date
     */
    public function get_expiry_date(): ?string {
        return $this->get_meta('membership_expiry');
    }

    /**
     * Check if membership is active
     */
    public function is_active(): bool {
        $expiry = $this->get_expiry_date();
        return $expiry && strtotime($expiry) > time();
    }

    /**
     * Get member's subscription level
     */
    public function get_level(): string {
        return $this->get_meta('membership_level') ?: 'basic';
    }

    /**
     * Extend membership
     */
    public function extend(int $days = 30): void {
        $current = $this->get_expiry_date();
        $base = $current && strtotime($current) > time()
            ? strtotime($current)
            : time();

        $new_expiry = date('Y-m-d', strtotime("+{$days} days", $base));
        $this->set_meta('membership_expiry', $new_expiry);
    }
}
```

---

## Custom Post Types

### Registering a Post Type

```php
<?php
namespace My_Plugin\Post_Types;

use Digitalis\Post_Type;

/**
 * Event post type registration
 */
class Event_Post_Type extends Post_Type {

    protected static $slug = 'event';
    protected static $singular = 'Event';
    protected static $plural = 'Events';
    protected static $icon = 'dashicons-calendar-alt';
    protected static $position = 25;
    protected static $archive = true;

    // Enable features
    protected static $supports = [
        'title',
        'editor',
        'thumbnail',
        'excerpt',
        'custom-fields',
    ];

    /**
     * Customize admin columns
     */
    public function columns(array $columns): array {
        // Insert after title
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['event_date'] = 'Event Date';
                $new['event_location'] = 'Location';
            }
        }
        return $new;
    }

    /**
     * Render column content
     */
    public function column(string $column, int $post_id): void {
        switch ($column) {
            case 'event_date':
                $date = get_post_meta($post_id, '_event_start_date', true);
                echo $date ? date('M j, Y', strtotime($date)) : '—';
                break;

            case 'event_location':
                echo esc_html(get_post_meta($post_id, '_event_location', true) ?: '—');
                break;
        }
    }

    /**
     * Custom query vars for filtering
     */
    public function get_query_vars(): array {
        return [
            'event_month' => '',
            'event_year'  => '',
        ];
    }

    /**
     * Modify archive query
     */
    public function pre_get_posts(\WP_Query $query): void {
        if (!$query->is_main_query() || !is_post_type_archive('event')) {
            return;
        }

        // Order by event date
        $query->set('meta_key', '_event_start_date');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');

        // Filter by month/year if provided
        if ($month = get_query_var('event_month')) {
            $query->set('meta_query', [[
                'key'     => '_event_start_date',
                'value'   => sprintf('%04d-%02d', get_query_var('event_year', date('Y')), $month),
                'compare' => 'LIKE',
            ]]);
        }
    }
}
```

---

## Custom Taxonomies

### Registering a Taxonomy

```php
<?php
namespace My_Plugin\Taxonomies;

use Digitalis\Taxonomy;

/**
 * Event Category taxonomy
 */
class Event_Category extends Taxonomy {

    protected static $slug = 'event_category';
    protected static $singular = 'Event Category';
    protected static $plural = 'Event Categories';
    protected static $post_types = ['event'];
    protected static $hierarchical = true;

    /**
     * Custom arguments
     */
    public function get_args(): array {
        return array_merge(parent::get_args(), [
            'show_admin_column' => true,
            'rewrite' => [
                'slug'       => 'events/category',
                'with_front' => false,
            ],
        ]);
    }
}
```

### Term Model

```php
<?php
namespace My_Plugin\Models;

use Digitalis\Term;

/**
 * Event Category term model
 */
class Event_Category extends Term {

    protected static $taxonomy = 'event_category';

    /**
     * Get category color
     */
    public function get_color(): string {
        return get_term_meta($this->get_id(), 'category_color', true) ?: '#333333';
    }

    /**
     * Get events in this category
     */
    public function get_events(int $limit = 10): array {
        return \My_Plugin\Models\Event::query([
            'tax_query' => [[
                'taxonomy' => static::$taxonomy,
                'terms'    => $this->get_id(),
            ]],
            'numberposts' => $limit,
        ]);
    }
}
```

---

## Views & Templates

### Basic View

```php
<?php
namespace My_Plugin\Views;

use Digitalis\View;

/**
 * Event card view
 */
class Event_Card extends View {

    protected static $template = 'event-card.php';
    protected static $template_path = MY_PLUGIN_PATH . 'templates/';

    protected static $defaults = [
        'show_date'     => true,
        'show_location' => true,
        'class'         => '',
    ];

    protected static $required = ['event'];
}
```

**Template file** (`templates/event-card.php`):

```php
<?php
/**
 * Event card template
 *
 * @var \My_Plugin\Models\Event $event
 * @var bool $show_date
 * @var bool $show_location
 * @var string $class
 */
?>
<article class="event-card <?php echo esc_attr($class); ?>">
    <?php if ($event->get_thumbnail_id()): ?>
        <div class="event-card__image">
            <?php echo get_the_post_thumbnail($event->get_id(), 'medium'); ?>
        </div>
    <?php endif; ?>

    <div class="event-card__content">
        <h3 class="event-card__title">
            <a href="<?php echo esc_url($event->get_permalink()); ?>">
                <?php echo esc_html($event->get_title()); ?>
            </a>
        </h3>

        <?php if ($show_date && $event->get_start_date()): ?>
            <time class="event-card__date">
                <?php echo esc_html(date('F j, Y', strtotime($event->get_start_date()))); ?>
            </time>
        <?php endif; ?>

        <?php if ($show_location && $event->get_location()): ?>
            <span class="event-card__location">
                <?php echo esc_html($event->get_location()); ?>
            </span>
        <?php endif; ?>
    </div>
</article>
```

**Usage**:

```php
use My_Plugin\Views\Event_Card;
use My_Plugin\Models\Event;

$event = Event::get_instance(123);

// Render directly
Event_Card::render([
    'event'         => $event,
    'show_location' => false,
    'class'         => 'featured',
]);

// Get HTML string
$html = Event_Card::render([
    'event' => $event,
], false);
```

### Component-Based View

```php
<?php
namespace My_Plugin\Views;

use Digitalis\Component;

/**
 * Alert component
 */
class Alert extends Component {

    protected static $defaults = [
        'tag'     => 'div',
        'type'    => 'info',    // info, success, warning, error
        'title'   => '',
        'message' => '',
        'dismissible' => false,
    ];

    public function print(bool $return = false): string {
        $this['class'] = array_merge(
            (array) ($this['class'] ?? []),
            ['alert', 'alert--' . $this['type']]
        );

        if ($this['dismissible']) {
            $this['class'][] = 'alert--dismissible';
        }

        // Build content
        $content = '';

        if ($this['title']) {
            $content .= '<strong class="alert__title">' . esc_html($this['title']) . '</strong>';
        }

        if ($this['message']) {
            $content .= '<p class="alert__message">' . esc_html($this['message']) . '</p>';
        }

        if ($this['dismissible']) {
            $content .= '<button type="button" class="alert__close">&times;</button>';
        }

        $this['content'] = $content;

        return parent::print($return);
    }
}
```

**Usage**:

```php
use My_Plugin\Views\Alert;

Alert::render([
    'type'    => 'success',
    'title'   => 'Success!',
    'message' => 'Your changes have been saved.',
    'dismissible' => true,
]);
```

---

## Form Fields

### Building a Form

```php
<?php
namespace My_Plugin\Views;

use Digitalis\View;
use Digitalis\Views\Fields\Input;
use Digitalis\Views\Fields\Textarea;
use Digitalis\Views\Fields\Select;
use Digitalis\Views\Fields\Checkbox;
use Digitalis\Views\Fields\Submit;

/**
 * Contact form view
 */
class Contact_Form extends View {

    protected static $defaults = [
        'action' => '',
        'subjects' => [],
    ];

    public function print(bool $return = false): string {
        ob_start();
        ?>
        <form method="post" action="<?php echo esc_url($this['action']); ?>" class="contact-form field-group">
            <?php wp_nonce_field('contact_form', 'contact_nonce'); ?>

            <?php
            Input::render([
                'name'        => 'name',
                'label'       => 'Your Name',
                'placeholder' => 'John Doe',
                'required'    => true,
            ]);

            Input::render([
                'name'        => 'email',
                'label'       => 'Email Address',
                'type'        => 'email',
                'placeholder' => 'john@example.com',
                'required'    => true,
            ]);

            Select::render([
                'name'    => 'subject',
                'label'   => 'Subject',
                'options' => $this['subjects'],
            ]);

            Textarea::render([
                'name'        => 'message',
                'label'       => 'Message',
                'placeholder' => 'How can we help?',
                'required'    => true,
            ]);

            Checkbox::render([
                'name'  => 'subscribe',
                'label' => 'Subscribe to our newsletter',
            ]);

            Submit::render([
                'label' => 'Send Message',
            ]);
            ?>
        </form>
        <?php
        $html = ob_get_clean();

        if ($return) return $html;
        echo $html;
        return '';
    }
}
```

**Usage**:

```php
use My_Plugin\Views\Contact_Form;

Contact_Form::render([
    'action' => admin_url('admin-post.php'),
    'subjects' => [
        ''         => 'Select a subject...',
        'general'  => 'General Inquiry',
        'support'  => 'Technical Support',
        'sales'    => 'Sales Question',
    ],
]);
```

---

## Features & Hooks

### Basic Feature

```php
<?php
namespace My_Plugin\Features;

use Digitalis\Feature;

/**
 * Email notifications feature
 */
class Email_Notifications extends Feature {

    /**
     * Register WordPress hooks
     */
    public function get_hooks(): array {
        return [
            'transition_post_status' => ['on_status_change', 10],
            'user_register'          => 'on_user_register',
            'woocommerce_order_status_completed' => 'on_order_complete',
        ];
    }

    /**
     * Called when feature is instantiated
     */
    public function run(): void {
        // Any initialization logic
    }

    /**
     * Handle post status changes
     */
    public function on_status_change(string $new, string $old, \WP_Post $post): void {
        if ($post->post_type !== 'event') return;
        if ($new !== 'publish' || $old === 'publish') return;

        $this->send_event_notification($post);
    }

    /**
     * Handle new user registration
     */
    public function on_user_register(int $user_id): void {
        $user = get_userdata($user_id);
        $this->send_welcome_email($user);
    }

    /**
     * Handle completed orders
     */
    public function on_order_complete(int $order_id): void {
        $order = wc_get_order($order_id);
        $this->send_order_confirmation($order);
    }

    // Private helper methods...
    private function send_event_notification(\WP_Post $post): void {
        wp_mail(
            get_option('admin_email'),
            'New Event Published: ' . $post->post_title,
            'A new event has been published on your site.'
        );
    }
}
```

### Integration Example

```php
<?php
namespace My_Plugin\Integrations;

use Digitalis\Integration;

/**
 * Mailchimp integration
 */
class Mailchimp extends Integration {

    private $api_key;
    private $list_id;

    public function init(): void {
        $this->api_key = get_option('mailchimp_api_key');
        $this->list_id = get_option('mailchimp_list_id');
    }

    public function get_hooks(): array {
        return [
            'my_plugin_user_subscribed' => 'add_subscriber',
        ];
    }

    /**
     * Add subscriber to Mailchimp
     */
    public function add_subscriber(string $email, array $data = []): bool {
        if (!$this->api_key) return false;

        $response = wp_remote_post($this->get_api_url('/lists/' . $this->list_id . '/members'), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('user:' . $this->api_key),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'email_address' => $email,
                'status'        => 'subscribed',
                'merge_fields'  => $data,
            ]),
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    private function get_api_url(string $endpoint): string {
        $dc = substr($this->api_key, strpos($this->api_key, '-') + 1);
        return "https://{$dc}.api.mailchimp.com/3.0{$endpoint}";
    }
}
```

---

## Admin Pages

### Settings Page

```php
<?php
namespace My_Plugin\Admin;

use Digitalis\Admin_Page;

/**
 * Plugin settings page
 */
class Settings_Page extends Admin_Page {

    protected static $slug = 'my-plugin-settings';
    protected static $title = 'My Plugin Settings';
    protected static $menu_title = 'My Plugin';
    protected static $capability = 'manage_options';
    protected static $icon = 'dashicons-admin-generic';
    protected static $position = 80;

    public function render(): void {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('my_plugin_settings')) {
            $this->save_settings();
        }

        $settings = get_option('my_plugin_settings', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post">
                <?php wp_nonce_field('my_plugin_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_key">API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="settings[api_key]"
                                   id="api_key"
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enable_feature">Enable Feature</label>
                        </th>
                        <td>
                            <input type="checkbox"
                                   name="settings[enable_feature]"
                                   id="enable_feature"
                                   value="1"
                                   <?php checked($settings['enable_feature'] ?? false); ?>>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function save_settings(): void {
        $settings = [
            'api_key'        => sanitize_text_field($_POST['settings']['api_key'] ?? ''),
            'enable_feature' => !empty($_POST['settings']['enable_feature']),
        ];

        update_option('my_plugin_settings', $settings);

        add_settings_error(
            'my_plugin_settings',
            'settings_saved',
            'Settings saved.',
            'success'
        );
    }
}
```

---

## REST API Routes

### Custom Endpoint

```php
<?php
namespace My_Plugin\Routes;

use Digitalis\Route;
use My_Plugin\Models\Event;

/**
 * Events API endpoint
 */
class Events_Route extends Route {

    protected static $namespace = 'my-plugin';
    protected static $version = 'v1';
    protected static $route = '/events';
    protected static $methods = 'GET';

    /**
     * Handle request
     */
    public function handle(\WP_REST_Request $request): \WP_REST_Response {
        $params = $request->get_params();

        $args = [
            'numberposts' => $params['per_page'] ?? 10,
            'offset'      => (($params['page'] ?? 1) - 1) * ($params['per_page'] ?? 10),
        ];

        if (!empty($params['category'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'event_category',
                'field'    => 'slug',
                'terms'    => $params['category'],
            ]];
        }

        $events = Event::query($args);

        $data = array_map(function($event) {
            return [
                'id'       => $event->get_id(),
                'title'    => $event->get_title(),
                'date'     => $event->get_start_date(),
                'location' => $event->get_location(),
                'link'     => $event->get_permalink(),
            ];
        }, $events);

        return new \WP_REST_Response([
            'events' => $data,
            'total'  => count($data),
        ], 200);
    }

    /**
     * Permission callback
     */
    public function permission_callback(): bool {
        return true; // Public endpoint
    }
}
```

### Authenticated Route

```php
<?php
namespace My_Plugin\Routes;

use Digitalis\Route;

/**
 * User settings endpoint
 */
class User_Settings_Route extends Route {

    protected static $namespace = 'my-plugin';
    protected static $route = '/user/settings';
    protected static $methods = ['GET', 'POST'];
    protected static $require_nonce = true;

    public function handle(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();

        if ($request->get_method() === 'POST') {
            return $this->update_settings($user_id, $request);
        }

        return $this->get_settings($user_id);
    }

    private function get_settings(int $user_id): \WP_REST_Response {
        return new \WP_REST_Response([
            'notifications' => get_user_meta($user_id, 'notifications_enabled', true),
            'timezone'      => get_user_meta($user_id, 'timezone', true) ?: 'UTC',
        ]);
    }

    private function update_settings(int $user_id, \WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();

        if (isset($data['notifications'])) {
            update_user_meta($user_id, 'notifications_enabled', (bool) $data['notifications']);
        }

        if (isset($data['timezone'])) {
            update_user_meta($user_id, 'timezone', sanitize_text_field($data['timezone']));
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function permission_callback(): bool {
        return is_user_logged_in();
    }
}
```

---

## Iterators (Batch Processing)

### Post Migration Iterator

```php
<?php
namespace My_Plugin\Iterators;

use Digitalis\Post_Iterator;
use My_Plugin\Models\Event;

/**
 * Migrate legacy events to new format
 */
class Event_Migration extends Post_Iterator {

    protected static $title = 'Migrate Events';
    protected static $key = 'event_migration';
    protected static $batch_size = 50;

    // Post type to iterate
    protected static $post_type = 'event';

    /**
     * Process single event
     */
    public function process_item($post): bool {
        $event = Event::get_instance($post->ID);

        // Migrate old date format to new
        $old_date = get_post_meta($post->ID, 'event_date', true);
        if ($old_date && !$event->get_start_date()) {
            $event->set_meta('_event_start_date', date('Y-m-d', strtotime($old_date)));
            delete_post_meta($post->ID, 'event_date');
        }

        // Migrate location
        $old_location = get_post_meta($post->ID, 'location', true);
        if ($old_location && !$event->get_location()) {
            $event->set_meta('_event_location', $old_location);
            delete_post_meta($post->ID, 'location');
        }

        return true;
    }
}
```

### CSV Import Iterator

```php
<?php
namespace My_Plugin\Iterators;

use Digitalis\CSV_Iterator;

/**
 * Import products from CSV
 */
class Product_Import extends CSV_Iterator {

    protected static $title = 'Import Products';
    protected static $key = 'product_import';
    protected static $batch_size = 25;

    /**
     * Process single row
     */
    public function process_item($row): bool {
        // Validate required fields
        if (empty($row['sku']) || empty($row['name'])) {
            return false;
        }

        // Check if product exists
        $existing = wc_get_product_id_by_sku($row['sku']);

        if ($existing) {
            $product = wc_get_product($existing);
        } else {
            $product = new \WC_Product_Simple();
            $product->set_sku($row['sku']);
        }

        $product->set_name(sanitize_text_field($row['name']));
        $product->set_regular_price(floatval($row['price']));
        $product->set_description(wp_kses_post($row['description'] ?? ''));
        $product->set_stock_quantity(intval($row['stock'] ?? 0));

        $product->save();

        return true;
    }
}
```

---

## WooCommerce Integration

### Custom Product Type

```php
<?php
namespace My_Plugin\WooCommerce;

use Digitalis\Product_Type;

/**
 * Subscription product type
 */
class Subscription_Product extends Product_Type {

    protected static $slug = 'subscription';

    /**
     * Get subscription period
     */
    public function get_period(): string {
        return $this->product->get_meta('_subscription_period') ?: 'month';
    }

    /**
     * Get subscription length
     */
    public function get_length(): int {
        return (int) $this->product->get_meta('_subscription_length') ?: 1;
    }

    /**
     * Calculate recurring price
     */
    public function get_recurring_price(): float {
        return (float) $this->product->get_price();
    }
}
```

### Order Extension

```php
<?php
namespace My_Plugin\Models;

use Digitalis\Order;

/**
 * Extended order model
 */
class Custom_Order extends Order {

    /**
     * Get gift message
     */
    public function get_gift_message(): string {
        return $this->get_wc_order()->get_meta('_gift_message') ?: '';
    }

    /**
     * Check if order is a gift
     */
    public function is_gift(): bool {
        return (bool) $this->get_gift_message();
    }

    /**
     * Get order tracking number
     */
    public function get_tracking_number(): string {
        return $this->get_wc_order()->get_meta('_tracking_number') ?: '';
    }

    /**
     * Set tracking number and notify customer
     */
    public function set_tracking(string $number, string $carrier = ''): void {
        $order = $this->get_wc_order();
        $order->update_meta_data('_tracking_number', $number);
        $order->update_meta_data('_tracking_carrier', $carrier);
        $order->save();

        // Trigger notification
        do_action('my_plugin_tracking_added', $this, $number, $carrier);
    }
}
```

---

## ACF Integration

### ACF Block

```php
<?php
namespace My_Plugin\Blocks;

use Digitalis\ACF_Block;

/**
 * Testimonial ACF block
 */
class Testimonial_Block extends ACF_Block {

    protected static $slug = 'testimonial';
    protected static $view = \My_Plugin\Views\Testimonial::class;

    protected static $block = [
        'title'       => 'Testimonial',
        'description' => 'Display a customer testimonial',
        'category'    => 'common',
        'icon'        => 'format-quote',
        'keywords'    => ['quote', 'review', 'testimonial'],
    ];

    protected static $defaults = [
        'quote'       => '',
        'author_name' => '',
        'author_role' => '',
        'author_image' => null,
        'rating'      => 5,
    ];

    /**
     * Register ACF fields
     */
    public function include_fields(): void {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key'      => 'group_testimonial_block',
            'title'    => 'Testimonial',
            'location' => [[['param' => 'block', 'operator' => '==', 'value' => 'acf/testimonial']]],
            'fields'   => [
                [
                    'key'   => 'field_quote',
                    'label' => 'Quote',
                    'name'  => 'quote',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_author_name',
                    'label' => 'Author Name',
                    'name'  => 'author_name',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_author_role',
                    'label' => 'Author Role',
                    'name'  => 'author_role',
                    'type'  => 'text',
                ],
                [
                    'key'          => 'field_author_image',
                    'label'        => 'Author Photo',
                    'name'         => 'author_image',
                    'type'         => 'image',
                    'return_format' => 'id',
                ],
                [
                    'key'   => 'field_rating',
                    'label' => 'Rating',
                    'name'  => 'rating',
                    'type'  => 'range',
                    'min'   => 1,
                    'max'   => 5,
                ],
            ],
        ]);
    }
}
```

### Model with ACF Fields

```php
<?php
namespace My_Plugin\Models;

use Digitalis\Post;
use Digitalis\Traits\Has_ACF_Fields;

/**
 * Team Member model with ACF support
 */
class Team_Member extends Post {

    use Has_ACF_Fields;

    protected static $post_type = 'team_member';

    /**
     * Get job title
     */
    public function get_job_title(): string {
        return $this->get_field('job_title') ?: '';
    }

    /**
     * Get bio
     */
    public function get_bio(): string {
        return $this->get_field('bio') ?: '';
    }

    /**
     * Get social links
     */
    public function get_social_links(): array {
        return $this->get_field('social_links') ?: [];
    }

    /**
     * Get headshot image URL
     */
    public function get_headshot_url(string $size = 'medium'): string {
        $image_id = $this->get_field('headshot');
        if (!$image_id) return '';

        $url = wp_get_attachment_image_url($image_id, $size);
        return $url ?: '';
    }
}
```

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

    protected static $namespace = 'digitalis';
    protected static $route     = 'estimate/(?P<id>\d+)/approve';
    protected static $methods   = 'POST';
    protected static $view      = Order_Status_Badge::class;

    public function permission_callback(): bool {
        $user = User::current();
        $order_id = $this->get_param('id');
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
