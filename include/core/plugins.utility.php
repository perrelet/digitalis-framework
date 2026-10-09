<?php

namespace Lattice;

final class Plugins extends Utility {

    protected static $active = [];

    // Basenames ('woocommerce/woocommerce.php'; 'hello.php' for a single-file plugin) of the plugins WordPress itself is loading
    // on this request, kept per blog and per recovery-mode state (a paused plugin is skipped only once recovery mode has
    // initialised, which an mu-plugin's first call precedes) and never while installing, when the list is empty.
    public static function active () : array {

        $key = get_current_blog_id() . ':' . (int) wp_recovery_mode()->is_initialized();

        if (isset(static::$active[$key])) return static::$active[$key];

        $plugins = wp_get_active_and_valid_plugins();
        if (is_multisite()) $plugins = array_merge($plugins, wp_get_active_network_plugins());

        $basenames = array_map('plugin_basename', $plugins);

        if (!wp_installing()) static::$active[$key] = $basenames;

        return $basenames;

    }

    // A plugin basename or its directory, forward slashes; a single-file plugin has no directory.
    public static function is_active (string $plugin) : bool {

        foreach (static::active() as $basename) {

            if ($plugin === $basename) return true;
            if ((($dir = dirname($basename)) !== '.') && ($plugin === $dir)) return true;

        }

        return false;

    }

}
