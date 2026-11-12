<?php

declare(strict_types=1);

namespace Lattice;

use Automattic\WooCommerce\Utilities\OrderUtil;

// HPOS with sync off only: ACF reads an order's meta table only through its placeholder post, and a sync can replay a write nobody made.
abstract class Order_Model_Resource extends Model_Resource {

    const ORDER_TYPE     = 'shop_order';
    const CREATE_STATUS  = 'pending';
    const DRAFT_STATUSES = ['pending'];

    protected $tally;

    public static function is_supported () : bool {

        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled() && !OrderUtil::custom_orders_table_data_sync_is_enabled();

    }

    public function init () {

        if (static::is_supported()) parent::init();

    }

    public function get_post_type () : string {

        return '';

    }

    public function get_order_type () : string {

        return static::ORDER_TYPE;

    }

    // Fields

    public function get_model_fields () : array {

        $choices = [];

        foreach (array_diff_key(wc_get_order_statuses(), ['wc-checkout-draft' => 0]) as $status => $label) $choices[OrderUtil::remove_status_prefix($status)] = $label;

        return [
            'status' => ['type' => 'select', 'label' => 'Status', 'choices' => $choices, 'get' => fn ($order) => $order->get_status(), 'set' => fn ($order, $status) => $order->set_status($status)],
        ];

    }

    public function get_post_fields () : array {

        return [];

    }

    // ACF saves each field on its own copy of the order, so the model's would save what was there before.
    public function stage_model_values ($model, array $values) : void {

        if (!$model->is_unsaved_copy()) $model->reload();

        parent::stage_model_values($model, $values);

    }

    // Records

    public function find ($id) : ?object {

        return $this->owns_record($id) ? (static::$model_class)::get_instance((int) $id) : null;

    }

    // A site migrated with sync off keeps full shop_order posts, for which ACF would read postmeta.
    public function owns_record ($id) : bool {

        return is_numeric($id) && ((int) $id > 0) && (OrderUtil::get_order_type((int) $id) === $this->get_order_type()) && (get_post_type((int) $id) === 'shop_order_placehold');

    }

    public function find_by_set_key (string $key) : ?object {

        global $wpdb;

        $table = OrderUtil::get_table_for_order_meta();

        foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT order_id FROM {$table} WHERE meta_key = %s AND meta_value = %s", Resources::SET_KEY, $key)) as $id) if ($model = $this->find((int) $id)) return $model;

        return null;

    }

    public function query (array $args) : array {

        return (static::$model_class)::query(apply_filters('lattice.resources.query', $this->get_query_args($args), $this));

    }

    // status, search, page, per_page, as WC_Order_Query arguments.
    public function get_query_args (array $args) : array {

        $statuses = array_keys(array_diff_key(wc_get_order_statuses(), ['wc-checkout-draft' => 0]));
        $status   = (string) ($args['status'] ?? '');
        $status   = ($status === 'trash') ? 'trash' : "wc-{$status}";

        return array_filter([
            'type'    => $this->get_order_type(),
            'status'  => (($status === 'trash') || in_array($status, $statuses, true)) ? [$status] : $statuses,
            's'       => (string) ($args['search'] ?? ''),
            'limit'   => $args['per_page'],
            'paged'   => $args['page'],
            'orderby' => 'modified',
            'order'   => 'DESC',
            'return'  => 'ids',
        ]);

    }

    public function make_model (array $values) : object {

        $model = (static::$model_class)::create([]);

        $model->set_status(static::CREATE_STATUS);

        return $model;

    }

    public function insert_model ($model, array $meta = []) : object {

        foreach ($meta as $key => $value) $model->update_meta_data($key, $value);

        $saved = $model->save();

        return is_wp_error($saved) ? $saved : (static::$model_class)::get_instance((int) $saved);

    }

    // Storage

    public function has_trash () : bool {

        return true;

    }

    // An untrash saves and replays the status hooks with only emails suppressed.
    public function move ($model, string $target) : bool {

        try {

            ($target === 'trash') ? $model->get_wc_order()->delete(false) : $model->get_wc_order()->untrash();

        } finally {

            $model->reload();

        }

        return $this->has_moved($model, $target);

    }

    public function has_moved ($model, string $target) : bool {

        $status = (string) (wc_get_order($model->get_id()) ?: null)?->get_status();

        return ($target === 'trash') ? ($status === 'trash') : ($status && ($status !== 'trash'));

    }

    // What untrash_order() restores to.
    public function get_previous_status ($model) : string {

        $status = (string) $model->get_meta('_wp_trash_meta_status');

        return array_key_exists($status, wc_get_order_statuses()) ? OrderUtil::remove_status_prefix($status) : 'pending';

    }

    public function get_required_status () : ?string {

        return null;

    }

    public function clear_record_cache ($id) : void {

        if ($this->owns_record($id)) (static::$model_class)::get_instance((int) $id)?->clear_cache();

    }

    public function get_acf_screen () : array {

        return ['post_type' => $this->get_order_type()];

    }

    public function forget_set_key ($model) : void {

        global $wpdb;

        $wpdb->delete(OrderUtil::get_table_for_order_meta(), ['order_id' => $model->get_id(), 'meta_key' => Resources::SET_KEY]);

        $model->clear_cache();

    }

    // Display

    public function summarise ($model) : array {

        $modified = $model->get_date_modified();

        return [
            'title'    => sprintf('Order #%s', $model->get_order_number()),
            'status'   => $model->get_status(),
            'modified' => $modified ? gmdate('c', $modified->getTimestamp()) : '',
        ];

    }

    public function get_edit_url ($model) : string {

        return OrderUtil::get_order_admin_edit_url((int) $model->get_id());

    }

    // WooCommerce maps the generic meta capabilities through the order's placeholder post.
    public function get_capability (string $operation, $model = null) : string {

        return match (true) {
            $model && ($operation === 'read')   => 'read_post',
            $model && ($operation === 'update') => 'edit_post',
            $model && in_array($operation, ['trash', 'untrash'], true) => 'delete_post',
            default                             => 'edit_shop_orders',
        };

    }

    // Credentials

    // 'draft' for DRAFT_STATUSES, and each status by its name. A trashed order is judged by the status it had.
    public function get_conditions () : array {

        $status = function ($model, array $values) : string {

            $status = (string) ($values['status'] ?? ($model ? $this->get_status($model) : static::CREATE_STATUS));

            return (($status === 'trash') && $model) ? OrderUtil::remove_status_prefix((string) $model->get_meta('_wp_trash_meta_status')) : $status;

        };

        $conditions = ['draft' => fn ($model, array $values) => in_array($status($model, $values), static::DRAFT_STATUSES, true)];

        foreach (array_keys($this->get_field('status')?->get_choices() ?? []) as $name) $conditions[$name] ??= fn ($model, array $values) => $status($model, $values) === $name;

        return $conditions;

    }

    // The placeholder post's author is whoever made the order, as WooCommerce's capability mapping reads it.
    public function get_facts ($model, array $values = [], ?int $user_id = null) : array {

        $facts = parent::get_facts($model, $values, $user_id);

        if ($model) $facts['own'] = (int) get_post_field('post_author', $model->get_id()) === ($user_id ?? get_current_user_id());

        return $facts;

    }

    // Writing

    // WC_Order::save() answers the id even when it threw, and each ACF field is a save of its own: only a save that started and never ended tells. First, so a listener that throws ahead still counts. Subclasses call parent.
    public function before_write ($model) : mixed {

        $this->stop_counting();

        $id    = (int) $model->get_id();
        $tally = (object) ['started' => 0, 'ended' => 0];

        $tally->start = function ($order) use ($id, $tally) { if ((int) $order->get_id() === $id) $tally->started++; };
        $tally->end   = function ($order) use ($id, $tally) { if ((int) $order->get_id() === $id) $tally->ended++; };

        add_action('woocommerce_before_order_object_save', $tally->start, PHP_INT_MIN);
        add_action('woocommerce_after_order_object_save', $tally->end, PHP_INT_MIN);

        return $this->tally = $tally;

    }

    public function after_write ($model, mixed $before, string $why) : void {

        $this->stop_counting();

        if ($before && ($before->started !== $before->ended)) throw new \RuntimeException(sprintf('%d of its %d saves failed; the order\'s notes and the WooCommerce log say why.', $before->started - $before->ended, $before->started));

    }

    protected function stop_counting () : void {

        if (!$this->tally) return;

        remove_action('woocommerce_before_order_object_save', $this->tally->start, PHP_INT_MIN);
        remove_action('woocommerce_after_order_object_save', $this->tally->end, PHP_INT_MIN);

        $this->tally = null;

    }

}
