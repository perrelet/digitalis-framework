<?php

namespace Lattice;

final class Custom_Fields {

    protected static $provider = null;
    protected static $logged   = false;

    public static function register (Field_Provider $provider) {

        static::$provider = $provider;

    }

    // The provider while it answers, else null. A read with no provider registered at all is a strict violation; available() never throws.
    public static function provider () {

        if (!static::$provider) {

            // Only possible when include/integrations/acf was not walked: ACF_Field_Provider registers itself from static_init.
            Strict::fail(self::class, 'has no field provider registered, so every field reads null.', 'Keep include/integrations/acf in the sweep, or Custom_Fields::register() a Field_Provider before reading fields.');

            return null;

        }

        if (static::$provider->available()) return static::$provider;

        // Plugins load alphabetically by path, so before plugins_loaded the provider's plugin may simply not be in yet; after it, unavailable means not installed.
        if (!static::$logged && !did_action('plugins_loaded')) {

            static::$logged = true;
            error_log(sprintf('Lattice: %s is registered but not available yet; a field was read before its plugin loaded, so the read returned null.', get_class(static::$provider)));

        }

        return null;

    }

    public static function available () {

        return static::$provider && static::$provider->available();

    }

}
