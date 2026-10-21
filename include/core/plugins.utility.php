<?php

namespace Lattice;

final class Plugins extends Utility {

    protected static $active = [];

    // Per blog and per recovery-mode state (a paused plugin is skipped only once it has initialised); never kept while installing.
    public static function active () : array {

        $key = get_current_blog_id() . ':' . (int) wp_recovery_mode()->is_initialized();

        if (isset(static::$active[$key])) return static::$active[$key];

        $plugins = wp_get_active_and_valid_plugins();
        if (is_multisite()) $plugins = array_merge($plugins, wp_get_active_network_plugins());

        $basenames = array_map('plugin_basename', $plugins);

        if (!wp_installing()) static::$active[$key] = $basenames;

        return $basenames;

    }

    public static function is_active (string $plugin) : bool {

        foreach (static::active() as $basename) {

            if ($plugin === $basename) return true;
            if ((($dir = dirname($basename)) !== '.') && ($plugin === $dir)) return true;   // 'hello.php' has no directory

        }

        return false;

    }

}
