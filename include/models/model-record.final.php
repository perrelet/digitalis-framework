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

    // Each unbound insert in flight, by storage; a WooCommerce object matches by its own id, a WordPress row binds at its first hook.
    private static array $inserting = [];
    private static bool  $listening = false;

    public function __construct (public readonly string $storage, public readonly int $site) {}

    public static function find (string $storage, int|string $id) : ?self {

        if ($record = Registry::get('record', $storage . '@' . get_current_blog_id(), $id)) return $record;

        foreach (self::$inserting[$storage] ?? [] as $inserting) if (!$inserting->bound && ($match = $inserting->state['match'] ?? null) && $match($id)) {
            $inserting->bound = true;
            $inserting->keep($id);
            return $inserting;
        }

        return null;

    }

    public static function insert (self $record, callable $write) : mixed {

        self::listen();

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

    public static function bind_insert (string $storage, int|string $id) : void {

        $stack  = self::$inserting[$storage] ?? [];
        $record = $stack ? end($stack) : null;

        if (!$record || $record->bound || !$id || isset($record->state['match'])) return;

        $record->bound = true;
        $record->keep($id);

        if ($view = reset($record->views)) (function () use ($id) { $this->id = $id; $this->hydrate_instance(); })->call($view);

    }

    public static function mark_deleted (string $storage, int|string $id) : void {

        if (!$record = Registry::get('record', $storage . '@' . get_current_blog_id(), $id)) return;

        // Marked, not removed: the WooCommerce follow on the same hook still reaches the views to warn a dirty one.
        $record->state['deleted'] = true;
        $record->touch();

    }

    public function is_dirty () : bool {

        foreach ($this->views as $view) if (method_exists($view, 'is_dirty')) return (bool) $view->is_dirty();

        return false;

    }

    private static function listen () : void {

        if (self::$listening) return;
        self::$listening = true;

        foreach (['clean_post_cache' => 0, 'set_object_terms' => 0, 'added_post_meta' => 1] as $hook => $at) add_action($hook, fn (...$args) => self::bind_insert('post', (int) $args[$at]), PHP_INT_MIN, 4);
        add_action('transition_post_status', fn ($new, $old, $post) => self::bind_insert('post', (int) ($post->ID ?? 0)), PHP_INT_MIN, 3);
        foreach (['create_term', 'edit_terms'] as $hook) add_action($hook, fn ($term_id) => self::bind_insert('term', (int) $term_id), PHP_INT_MIN, 1);

    }

    public static function follow_deletes () : void {

        add_action('deleted_post',    fn ($id) => self::mark_deleted('post', (int) $id), PHP_INT_MAX, 1);
        add_action('deleted_user',    fn ($id) => self::mark_deleted('user', (int) $id), PHP_INT_MAX, 1);
        add_action('delete_term',     fn ($id) => self::mark_deleted('term', (int) $id), PHP_INT_MAX, 1);
        add_action('deleted_comment', fn ($id) => self::mark_deleted('comment', (int) $id), PHP_INT_MAX, 1);
        add_action('woocommerce_delete_order', fn ($id) => self::mark_deleted('wc_order', (int) $id), PHP_INT_MAX, 1);
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
