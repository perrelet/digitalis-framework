<?php

declare(strict_types=1);

namespace Lattice;

// Versions and journals orders written outside the layer: wp-admin, checkout, Subscriptions, WooCommerce's REST API. See docs/specs/RESOURCES_ORDERS.md.
class Lattice_Resources_Orders extends Feature {

    protected static $requires = ['lattice/resources'];

    // Journaled from their first real status, as a post's auto-draft is.
    const DRAFTS = ['checkout-draft', 'auto-draft'];

    // By order id until the next settle: resource, before, version, status, created, raised, via.
    protected array $touched = [];

    // New orders by object, between their first before and after save.
    protected array $creating = [];

    protected bool $in_action = false;

    public static function instance_condition ($data = []) {

        return Order_Model_Resource::is_supported();

    }

    public function get_hooks () {

        return [
            'load-woocommerce_page_wc-orders'           => ['claim_from_editor', 5],
            'woocommerce_order_actions_start'           => 'print_version',
            'woocommerce_before_order_object_save'      => 'take_save_before',
            'woocommerce_after_order_object_save'       => 'note_save',
            'woocommerce_before_order_item_object_save' => 'take_item_before',
            'woocommerce_before_delete_order_item'      => 'take_item_deletion_before',
            'woocommerce_before_trash_order'            => 'take_trash_before',
            'woocommerce_trash_order'                   => 'note_trash',
            'woocommerce_untrash_order'                 => 'take_untrash_before',
            'woocommerce_before_delete_order'           => 'note_deletion',
            'action_scheduler_before_execute'           => 'start_action',
            'action_scheduler_after_execute'            => 'settle_action',
            'action_scheduler_failed_execution'         => 'settle_action',
            'wc_subscriptions_object_data'              => 'exclude_version',
            'shutdown'                                  => 'settle',
        ];

    }

    protected function find ($id) : array {

        $resource = (is_numeric($id) && ((int) $id > 0)) ? Resources::inst()->get_resource_for_record((int) $id) : null;
        $model    = ($resource instanceof Order_Model_Resource) ? $resource->find((int) $id) : null;

        return ($model && !$this->stands_aside($resource, (int) $id)) ? [$resource, $model] : [null, null];

    }

    // A layer write journals itself.
    protected function stands_aside (Model_Resource $resource, int $id) : bool {

        return Resources::inst()->is_writing('new') || Resources::inst()->is_writing("{$resource->get_name()}:{$id}");

    }

    // Async runners arrive through admin-ajax, so cron is asked first.
    protected function get_via () : string {

        return ($this->in_action || wp_doing_cron()) ? 'cron' : (is_admin() ? 'admin' : 'other');

    }

    // A throw inside a save becomes an order note, and in a before hook it cancels the write.
    protected function guard (callable $run) : void {

        try {

            $run();

        } catch (\Throwable $e) {

            error_log('Lattice orders: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

        }

    }

    // From a fresh load past the order cache: the object being saved already holds the new values, and site code may still hold the model.
    protected function read (Model_Resource $resource, $model) : array {

        $fresh = clone $model;

        $fresh->clear_cache();
        $resource->forget_values($fresh);

        try {

            return $resource->read($fresh);

        } finally {

            $resource->forget_values($fresh);

        }

    }

    protected function take (Model_Resource $resource, $model, int $id, ?int $version = null) : void {

        if (isset($this->touched[$id])) return;

        $before = $this->read($resource, $model);
        $status = (string) ($before['status'] ?? '');

        $this->touched[$id] = [
            'resource' => $resource,
            'before'   => $before,
            'version'  => $version ?? $model->get_version(),
            'status'   => $status,
            'created'  => in_array($status, static::DRAFTS, true),
            'raised'   => false,
            'via'      => $this->get_via(),
        ];

    }

    protected function raise (int $id, $model) : void {

        if (!isset($this->touched[$id]) || $model->is_claimed()) return;

        $model->raise_version();

        $this->touched[$id]['raised'] = true;

    }

    // Version

    // Ahead of handle_order_update(), so a refused save has written nothing.
    public function claim_from_editor () {

        $stale = null;

        $this->guard(function () use (&$stale) {

            if ((($_POST['action'] ?? '') !== 'edit_order') || !isset($_POST[Lattice_Resources::VERSION_FIELD])) return;

            $id = (int) ($_GET['id'] ?? 0);

            [$resource, $model] = $this->find($id);

            if (!$model || !wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), "update-order_{$id}") || !current_user_can('edit_shop_order', $id)) return;

            $seen = (int) $_POST[Lattice_Resources::VERSION_FIELD];

            if (!$model->claim_version($seen)) return $stale = [$resource, $id, $seen];

            $this->take($resource, $model, $id, $seen);

        });

        if ($stale) Lattice_Resources::refuse_stale(...$stale);

    }

    public function print_version ($order_id) {

        $this->guard(function () use ($order_id) {

            [, $model] = $this->find($order_id);

            if ($model) Lattice_Resources::print_version_field($model);

        });

    }

    public function exclude_version ($data) {

        foreach (Resources::inst()->get_resources() as $resource) if ($resource instanceof Order_Model_Resource) unset($data[($resource->get_model_class())::get_version_key()]);

        unset($data[Resources::SET_KEY]);

        return $data;

    }

    // Saves

    public function take_save_before ($order) {

        $this->guard(function () use ($order) {

            if (!$order->get_id()) return $this->creating[spl_object_id($order)] = $this->get_via();

            [$resource, $model] = $this->find($order->get_id());

            if ($model) $this->take($resource, $model, (int) $order->get_id());

        });

    }

    public function note_save ($order) {

        $this->guard(function () use ($order) {

            $id  = (int) $order->get_id();
            $via = $this->creating[spl_object_id($order)] ?? null;

            unset($this->creating[spl_object_id($order)]);

            [$resource, $model] = $this->find($id);

            if (!$model) return;

            // Its items save between the create and this, and would have taken a before.
            if ($via) $this->touched[$id] = ['resource' => $resource, 'before' => null, 'version' => 0, 'status' => '', 'created' => true, 'raised' => false, 'via' => $via];

            $this->raise($id, $model);

        });

    }

    public function take_item_before ($item) {

        $this->guard(function () use ($item) {

            [$resource, $model] = $this->find($item->get_order_id());

            if ($model) $this->take($resource, $model, (int) $item->get_order_id());

        });

    }

    public function take_item_deletion_before ($item_id) {

        $this->guard(function () use ($item_id) {

            $id = (int) wc_get_order_id_by_order_item_id($item_id);

            [$resource, $model] = $this->find($id);

            if ($model) $this->take($resource, $model, $id);

        });

    }

    // An HPOS trash is a direct update, not a save.
    public function take_trash_before ($order_id) {

        $this->guard(function () use ($order_id) {

            [$resource, $model] = $this->find($order_id);

            if ($model) $this->take($resource, $model, (int) $order_id);

        });

    }

    public function note_trash ($order_id) {

        $this->guard(function () use ($order_id) {

            [, $model] = $this->find($order_id);

            if ($model && (($this->touched[(int) $order_id]['status'] ?? 'trash') !== 'trash')) $this->raise((int) $order_id, $model);

        });

    }

    // Fires before the restore, whose own save raises the version.
    public function take_untrash_before ($order_id) {

        $this->take_trash_before($order_id);

    }

    // A forced delete only.
    public function note_deletion ($order_id) {

        $this->guard(function () use ($order_id) {

            $id    = (int) $order_id;
            $state = $this->touched[$id] ?? null;

            unset($this->touched[$id]);

            [$resource, $model] = $this->find($id);

            if (!$model || !Resources::inst()->is_journaled($resource, $model)) return;

            $before = $state ? $state['before'] : $this->read($resource, $model);

            if (!$before || in_array((string) ($before['status'] ?? ''), static::DRAFTS, true)) return;

            Journal::inst()->write([
                'via'            => $state['via'] ?? $this->get_via(),
                'resource'       => $resource->get_name(),
                'record_id'      => $id,
                'action'         => 'deleted',
                'before'         => $before,
                'version_before' => $state['version'] ?? $model->get_version(),
                'version_after'  => $model->get_version(),
            ]);

        });

    }

    // Settling

    // Touches before an action are not the action's.
    public function start_action () {

        $this->settle();

        $this->in_action = true;

    }

    public function settle_action () {

        $this->settle();

        $this->in_action = false;

    }

    // Every order touched since the last settle: one row each, with the version it settles at.
    public function settle () {

        $touched = $this->touched;

        $this->touched = [];

        foreach ($touched as $id => $state) $this->guard(fn () => $this->settle_order((int) $id, $state));

    }

    protected function settle_order (int $id, array $state) : void {

        $resource = $state['resource'];

        if (!$model = $resource->find($id)) return;

        // Adding an item by AJAX saves no order.
        if ($model->is_claimed()) $model->finish_version();
        elseif (!$state['raised'])  $model->raise_version();

        $after  = $this->read($resource, $model);
        $status = (string) ($after['status'] ?? '');

        if ($state['created'] && in_array($status, static::DRAFTS, true)) return;

        if ((!$state['created'] && ($after === $state['before'])) || !Resources::inst()->is_journaled($resource, $model)) return;

        $was = $state['created'] ? '' : $state['status'];

        Journal::inst()->write([
            'via'            => $state['via'],
            'resource'       => $resource->get_name(),
            'record_id'      => $id,
            'action'         => $state['created'] ? 'create' : ((($was !== 'trash') && ($status === 'trash')) ? 'trash' : ((($was === 'trash') && ($status !== 'trash')) ? 'untrash' : 'update')),
            'before'         => $state['created'] ? null : $state['before'],
            'after'          => $after,
            'version_before' => $state['created'] ? 0 : $state['version'],
            'version_after'  => $model->get_version(),
        ]);

    }

}
