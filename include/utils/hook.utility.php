<?php

namespace Lattice;

class Hook extends Utility {

    const DELIMITER = '.';

    public static function name (...$parts) {

        $flat = [];

        array_walk_recursive($parts, function ($part) use (&$flat) { $flat[] = (string) $part; });

        return static::sanitize(implode(self::DELIMITER, array_filter($flat, 'strlen')));

    }

    public static function sanitize ($name) {

        $d = preg_quote(self::DELIMITER, '/');

        $name = str_replace('\\', self::DELIMITER, $name);
        $name = preg_replace('/[^\w' . $d . ']+/', self::DELIMITER, $name);
        $name = strtolower(trim($name, self::DELIMITER));

        return preg_replace('/' . $d . '{2,}/', self::DELIMITER, $name);

    }

    public static function filter ($name, $value, ...$args) {

        $name = static::name($name);

        foreach (static::get_legacy_names($name) as $legacy) {
            $value = apply_filters_deprecated($legacy, [$value, ...$args], '1.0.0', $name);
        }

        return apply_filters($name, $value, ...$args);

    }

    public static function action ($name, ...$args) {

        $name = static::name($name);

        foreach (static::get_legacy_names($name) as $legacy) {
            do_action_deprecated($legacy, $args, '1.0.0', $name);
        }

        do_action($name, ...$args);

    }

    protected static function get_legacy_names ($name) {

        return class_exists(Deprecated_Hooks::class) ? Deprecated_Hooks::resolve($name) : [];

    }

}
