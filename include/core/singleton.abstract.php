<?php

declare(strict_types=1);

namespace Lattice;

use Exception;

abstract class Singleton extends Creational {

    use Auto_Instantiate, Is_Registrant;

    const INSTANCE_KIND = 'registrant';

    // A family base (Post_Type, Taxonomy, ...) sets its own, so its members are audited for the names they claim.
    protected static $cache_group = self::class;

    public function __construct () {}
    public function __clone()        { throw new Exception("One for all and all for one."); }
    public function __wakeup()       { throw new Exception("You may not dream me into existence."); }

    public static function get_instance () {

        $class_name = Call::get_class_name(static::class);

        if ($class_name != static::class) return $class_name::get_instance();
        if ($held = Registry::get(self::INSTANCE_KIND, static::$cache_group, $class_name)) return $held;
        if (!$class_name::may_build())    return null;

        $instance = new $class_name();

        self::audit_slug($instance);

        Registry::set(self::INSTANCE_KIND, static::$cache_group, $class_name, $instance);
        static::construct_instance($instance);
        Batch::defer($instance, fn () => $instance->switch_on());

        return $instance;

    }

    // Two members of a family claiming one WordPress name: the later registration wins in WordPress.
    private static function audit_slug (self $instance) : void {

        $slug = fn (object $o) => property_exists($o, 'slug') ? (new \ReflectionProperty($o, 'slug'))->getValue($o) : null;

        if (is_null($claimed = $slug($instance))) return;

        foreach (Registry::get_group(self::INSTANCE_KIND, static::$cache_group) as $other) if ($slug($other) === $claimed) Strict::report($instance::class, "claims '{$claimed}', which " . $other::class . ' already registers', 'give one of them its own slug, or opt the other out');

    }

    public function run () {}

}