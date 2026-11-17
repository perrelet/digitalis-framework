<?php

declare(strict_types=1);

namespace Lattice;

use Exception;
use WC_Abstract_Order;
use WC_Data;

// A model over a WC_Data object, held in the property static::WC_DATA names.
trait Has_WC_Data {

    public function __call ($name, $args) {

        return call_user_func_array([$this->{static::WC_DATA}, $name], $args);

    }

    // WC_Abstract_Order::__clone() is empty, so a cloned WC_Order shares its meta and items with the original.
    public function __clone () {

        $object = $this->{static::WC_DATA};
        $class  = $object::class;

        $this->{static::WC_DATA} = $object->get_id() ? new $class($object->get_id()) : clone $object;

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
    private function isolate_wc_data () : void {

        if (!($object = $this->{static::WC_DATA}) instanceof WC_Data) return;

        (function () { if (is_array($this->meta_data)) foreach ($this->meta_data as $i => $meta) $this->meta_data[$i] = clone $meta; })->call($object);

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
