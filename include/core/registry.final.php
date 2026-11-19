<?php

declare(strict_types=1);

namespace Lattice;

final class Registry {

    private static array $instances = [];

    public static function get (string $kind, string $group, int|string $key) : ?object {

        return self::$instances[$kind][$group][$key] ?? null;

    }

    public static function has (string $kind, string $group, int|string $key) : bool {

        return isset(self::$instances[$kind][$group][$key]);

    }

    public static function set (string $kind, string $group, int|string $key, object $instance) : void {

        self::$instances[$kind][$group][$key] = $instance;

    }

    public static function remove (string $kind, string $group, int|string $key) : void {

        unset(self::$instances[$kind][$group][$key]);

    }

    public static function get_group (string $kind, string $group) : array {

        return self::$instances[$kind][$group] ?? [];

    }

    public static function get_kind (string $kind) : array {

        return self::$instances[$kind] ?? [];

    }

    public static function forget_kind (string $kind) : void {

        unset(self::$instances[$kind]);

    }

}
