<?php

declare(strict_types=1);

namespace Lattice;

// One stored entity in a request: the views of it (User, Customer, Account) bind their shared properties to $state by reference.
final class Model_Record {

    public array $state   = [];
    public array $views   = [];
    public int   $counter = 0;
    public array $memo    = [];

    public bool  $bound   = false;
    public bool  $kept    = true;
    public array $rows    = [];

    // Each insert in flight, by storage. A WooCommerce object matches by its own id; a WordPress row has no hook that names only it (any write inside the insert fires the same ones), so a find of that storage reads a fresh view outside the map until the insert returns.
    private static array $inserting = [];

    public function __construct (public readonly string $storage, public readonly int $site) {}

    public static function find (string $storage, int|string $id) : ?self {

        if ($record = Registry::get('record', $storage . '@' . get_current_blog_id(), $id)) return $record;

        foreach (self::$inserting[$storage] ?? [] as $inserting) if (!$inserting->bound && ($match = $inserting->state['match'] ?? null) && $match($id)) {
            $inserting->bound           = true;
            $inserting->state['id']     = $id;
            $inserting->state['is_new'] = false;
            $inserting->keep($id);
            return $inserting;
        }

        return null;

    }

    public static function insert (self $record, callable $write) : mixed {

        self::$inserting[$record->storage][] = $record;

        try {

            return $write();

        } finally {

            array_pop(self::$inserting[$record->storage]);

        }

    }

    // While an insert into a storage is unbound, a view loaded for it stays out of the map: it could be a second object for the row being written.
    public static function is_unbound (string $storage) : bool {

        foreach (self::$inserting[$storage] ?? [] as $inserting) if (!$inserting->bound) return true;

        return false;

    }

    public static function mark_deleted (string $storage, int|string $id) : void {

        if (!$record = Registry::get('record', $storage . '@' . get_current_blog_id(), $id)) return;

        // Marked, not removed: the WooCommerce follow on the same hook still reaches the views to warn a dirty one.
        $record->state['deleted'] = true;
        $record->touch();

    }

    public static function follow_rows (array $rows, self $saver) : void {

        if (!$rows) return;

        $written = array_map('serialize', $rows);

        foreach (Registry::get_kind('record') as $records) foreach ($records as $record) {

            if (($record === $saver) || !array_intersect(array_map('serialize', $record->rows), $written)) continue;

            $record->reload_guarded();

        }

    }

    // The one guard every reload goes through: a record mid-insert or holding edits is left alone, and a held edit is reported once per class.
    public function reload_guarded () : void {

        if (self::in_flight($this) || !empty($this->state['deleted'])) return;

        $view = reset($this->views);

        if ($this->is_dirty()) {

            static $noticed = [];

            if ($view && !isset($noticed[$view::class])) {
                $noticed[$view::class] = true;
                _doing_it_wrong(esc_html($view::class), esc_html($view::class . ' kept unsaved edits across a save of its row by another model, and its own save would write over that one: reload() before editing across such a save.'), '0.14.0');
            }

            return;

        }

        if ($view && method_exists($view, 'reload')) $view->reload();

    }

    public static function in_flight (self $record) : bool {

        return in_array($record, self::$inserting[$record->storage] ?? [], true);

    }

    public function is_dirty () : bool {

        foreach ($this->views as $view) if (method_exists($view, 'is_dirty')) return (bool) $view->is_dirty();

        return false;

    }

    public static function follow_deletes () : void {

        add_action('deleted_post',    fn ($id) => self::mark_deleted('post', (int) $id), PHP_INT_MAX, 1);
        add_action('deleted_user',    fn ($id) => self::mark_deleted('user', (int) $id), PHP_INT_MAX, 1);
        add_action('delete_term',     fn ($id) => self::mark_deleted('term', (int) $id), PHP_INT_MAX, 1);
        add_action('deleted_comment', fn ($id) => self::mark_deleted('comment', (int) $id), PHP_INT_MAX, 1);
        add_action('woocommerce_delete_order', fn ($id) => self::mark_deleted('wc_order', (int) $id), PHP_INT_MAX, 1);
        add_action('woocommerce_delete_subscription', fn ($id) => self::mark_deleted('wc_order', (int) $id), PHP_INT_MAX, 1);
        add_action('woocommerce_delete_order_item', fn ($id) => self::mark_deleted('wc_order_item', (int) $id), PHP_INT_MAX, 1);

    }

    // Every edit, save and reload moves the counter and drops what views derived from the old state.
    public function touch () : void {

        $this->counter++;
        $this->memo = [];

    }

    public function keep (int|string $id) : void {

        if (!$this->kept) return;

        Registry::set('record', $this->storage . '@' . $this->site, $id, $this);

    }

}
