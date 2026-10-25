<?php

declare(strict_types=1);

namespace Lattice;

abstract class Plugin_Integration extends Integration {

    protected static $plugin = '';

    public static function instance_condition () {

        if (!is_string(static::$plugin)) {

            $error = '$plugin must be a plugin basename or its directory, ' . get_debug_type(static::$plugin) . ' provided.';

            Strict::violation(static::class, $error, 'Set $plugin to the plugin basename (woocommerce/woocommerce.php) or its directory.');

            Strict::log_once(static::class, $error);

            return false;

        }

        return Plugins::is_active(static::$plugin);

    }

}