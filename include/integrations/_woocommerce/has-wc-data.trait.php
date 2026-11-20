<?php

declare(strict_types=1);

namespace Lattice;

use Exception;
use WC_Abstract_Order;
use WC_Data;

// A model over a WC_Data object, held in the property static::WC_DATA names.
trait Has_WC_Data {

    public function __call ($name, $args) {

        // Views of one order share its edits; the notice names the view that made them.
        if (preg_match('/^(set|add|update|delete|remove)_/', (string) $name)) $this->get_record()->state['editor'] = static::class;

        return call_user_func_array([$this->{static::WC_DATA}, $name], $args);

    }

    // WC_Abstract_Order::__clone() is empty, so a cloned WC_Order shares its meta and items with the original.
    public function __clone () {

        parent::__clone();

        $object = $this->{static::WC_DATA};
        $class  = $object::class;

        try {

            $this->{static::WC_DATA} = $object->get_id() ? new $class($object->get_id()) : clone $object;

        } catch (Exception $e) {

            // A deleted object's read throws; the clone keeps the old object, as reload() does.

        }

    }

    // WC_Data::delete() zeroes the object's id even when it only trashes, a deleted item's read throws and an unsaved id reads as an empty item.
    public function reload () {

        if (static::is_uuid($this->id)) return;

        $held = $this->{static::WC_DATA};

        $this->clear_cache();

        try {

            $this->hydrate_instance();

        } catch (Exception $e) {

            $this->{static::WC_DATA} = $held;

        }

        if (!$this->{static::WC_DATA}) $this->{static::WC_DATA} = $held;

    }

    public function is_dirty () : bool {

        return ($object = $this->{static::WC_DATA}) instanceof WC_Data && self::has_pending_edits($object);

    }

    // Copies served from Woo's object cache share their WC_Meta_Data objects, so another copy's edit would show in this one.
    private function hold_wc_data () : void {

        if (!($object = $this->{static::WC_DATA}) instanceof WC_Data) return;

        (function () { if (is_array($this->meta_data)) foreach ($this->meta_data as $i => $meta) $this->meta_data[$i] = clone $meta; })->call($object);

        self::watch_wc_data($object);

    }

    // Others' saves

    private static $wc_data_watched       = [];
    private static $wc_data_trashing      = [];
    private static $wc_data_trash_watched = false;
    private static $wc_data_noticed       = [];

    // Once per object type, the first time a model wraps one; a request that builds no Woo model adds no callback.
    private static function watch_wc_data (WC_Data $object) : void {

        $type = self::get_wc_data_type($object);

        if (isset(self::$wc_data_watched[$type])) return;

        self::$wc_data_watched[$type] = true;

        add_action("woocommerce_after_{$type}_object_save", fn ($saved) => self::follow_wc_data([$type], (int) $saved->get_id(), $saved), PHP_INT_MIN);

        if (!in_array($type, ['order', 'subscription'], true) || self::$wc_data_trash_watched) return;

        self::$wc_data_trash_watched = true;

        // CPT storage trashes and deletes a subscription through the order hooks.
        foreach (['order', 'subscription'] as $hook_type) {

            // The trash hook passes only the id, and the trashing object still holds it with a pending status. Last, so Subscriptions' fix_trash_meta_status() has run.
            add_action("woocommerce_before_trash_{$hook_type}", function ($id, $trashing = null) { self::$wc_data_trashing[(int) $id] = $trashing; }, PHP_INT_MIN, 2);
            add_action("woocommerce_trash_{$hook_type}", function ($id) { $trashing = self::$wc_data_trashing[(int) $id] ?? null; unset(self::$wc_data_trashing[(int) $id]); self::follow_wc_data(['order', 'subscription'], (int) $id, $trashing); }, PHP_INT_MAX);

            // A forced delete zeroes the deleting object's id first, so the walk passes over the model that deleted.
            add_action("woocommerce_delete_{$hook_type}", function ($id) { unset(self::$wc_data_trashing[(int) $id]); self::follow_wc_data(['order', 'subscription'], (int) $id, null); }, PHP_INT_MAX);

        }

    }

    // An Error passes Woo's catch (Exception) around a save and would be fatal.
    private static function follow_wc_data (array $types, int $id, ?WC_Data $exclude) : void {

        if (!$id) return;

        $followed = [];

        foreach (Registry::get_kind(self::INSTANCE_KIND) as $class => $models) {

            if (!is_a($class, self::class, true) || !isset($models[$id])) continue;

            try {

                $model  = $models[$id];
                $object = $model->{$class::WC_DATA};

                if (!($object instanceof WC_Data) || ($object === $exclude) || ((int) $object->get_id() !== $id) || !in_array(self::get_wc_data_type($object), $types, true)) continue;

                if (isset($followed[$record = spl_object_id($model->get_record())])) continue;
                $followed[$record] = true;

                if ($model->is_dirty()) self::notice_held_wc_data($model->get_record()->state['editor'] ?? $class); else $model->reload();

            } catch (\Throwable $e) {

                error_log('Lattice ' . $class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            }

        }

    }

    // A notice, never a throw: Woo catches an exception inside a save and skips every later callback on the hook.
    private static function notice_held_wc_data (string $class) : void {

        if (isset(self::$wc_data_noticed[$class])) return;

        self::$wc_data_noticed[$class] = true;

        _doing_it_wrong(esc_html($class), esc_html("{$class} kept unsaved edits across a save of its id by another object, and its own save would write over that one: reload() before editing across such a save."), '');

    }

    private static function get_wc_data_type (WC_Data $object) : string {

        return (string) (fn () => $this->object_type)->call($object);

    }

    private static function has_pending_edits (WC_Data $object) : bool {

        if ($object->get_changes() || self::has_pending_meta($object)) return true;

        if (!$object instanceof WC_Abstract_Order) return false;

        [$queued, $items] = (fn () => [
            $this->items_to_delete || !empty($this->bulk_delete_all_items_pending) || !empty($this->item_types_to_bulk_delete), // both queues are absent from older Woo
            $this->items,
        ])->call($object);

        if ($queued) return true;

        foreach ($items as $group) foreach ($group as $item) if (($item instanceof WC_Data) && (!$item->get_id() || $item->get_changes() || self::has_pending_meta($item))) return true;

        return false;

    }

    // get_meta_data() would read unread meta and hides deletions; null means never read, so nothing is pending.
    private static function has_pending_meta (WC_Data $object) : bool {

        // An entry added and deleted before a save keeps a null id and value, and save_meta_data() leaves it.
        foreach ((array) (fn () => $this->meta_data)->call($object) as $meta) if ($meta->id ? (is_null($meta->value) || $meta->get_changes()) : !is_null($meta->value)) return true;

        return false;

    }

}
