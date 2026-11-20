<?php

declare(strict_types=1);

namespace Lattice;

use ReflectionClass;

abstract class Factory extends Creational {

    use Auto_Instantiate, Dependency_Injection;

    const INSTANCE_KIND = 'registrant';

    protected static $cache_group = '__global__';
    protected static $cache_property = null;

    public static function get_group_instances (?string $group = null) {

        $group ??= static::get_cache_group();
        return Registry::get_group(static::INSTANCE_KIND, $group);

    }

    public static function get_instance_map () {

        $map = [];
        foreach (['registrant', 'service', 'app'] as $kind) foreach (Registry::get_kind($kind) as $group => $instances) {
            $map[$group] ??= [];
            foreach ($instances as $key => $instance) {
                $map[$group][$key] = $instance::class;
            }
        }
    
        return $map;
    
    }

    public static function instance_condition (mixed $data = []) {
    
        return parent::instance_condition();
    
    }

    protected static function construct_instance (object $instance, mixed $data = []) {

        parent::construct_instance($instance);

    }

    protected static function apply_config (object $instance, mixed $data = []) : void {

        if ($data && is_array($data)) foreach ($data as $prop => $value) {

            if (property_exists($instance, (string) $prop)) $instance->$prop = $value;

        }

    }

    // The config is on the instance before its constructor runs, so the constructor reads it and what it sets stands.
    public static function create (mixed $data = []) {

        if (is_scalar($data)) return;

        static::prepare_data($data);

        $reflection = new ReflectionClass(static::class);
        $instance   = $reflection->newInstanceWithoutConstructor();

        static::apply_config($instance, $data);

        if ($constructor = $reflection->getConstructor()) $constructor->invokeArgs($instance, static::method_inject($constructor, array_slice(func_get_args(), 1)));

        static::construct_instance($instance, $data);

        return $instance;

    }

    // A single class is found bare and built on first find; a named one is built only by declare($name, ...); a service on any find.
    protected static $named = false;

    public static function get_instance (mixed $data = null) {

        $class_name = Call::get_class_name(static::class, $data);
        if ($class_name != static::class) return $class_name::get_instance($data);

        if (is_array($data) && ($data !== [])) return static::refuse_declare('passes a config to get_instance()', 'make it live with declare($config), or build one nothing finds with create($config)');

        static::prepare_data($data);

        if (is_null($key = static::get_data_key($data))) return null;

        if ($found = Registry::get(static::INSTANCE_KIND, static::get_cache_group(), $key)) {

            if ($found instanceof static) return $found;

            return (is_null($data) || ($data === [])) ? static::refuse_declare("claims '{$key}', which " . $found::class . ' already holds', 'give one of them its own name') : null;

        }

        $bare = is_null($data) || ($data === []);

        if ((static::INSTANCE_KIND !== 'service') && (!$bare || static::$named)) return null;

        return self::build($key, $data);

    }

    /**
     * Makes a registrant live once: declare($config) under its name, declare($name, $config) for a named class.
     *
     * @return ?static
     */
    public static function declare (string|array $name = [], array $config = []) {

        if (is_array($name)) {
            $config = $name;
            $name   = null;
        }

        $class_name = Call::get_class_name(static::class, $config);
        if ($class_name != static::class) return is_null($name) ? $class_name::declare($config) : $class_name::declare($name, $config);

        $property = static::$cache_property;

        if (is_null($name) && is_string($property) && is_scalar($config[$property] ?? null)) $name = (string) $config[$property];

        $default = (string) static::get_data_key(null);

        if (is_null($name)) {

            if (static::$named) return static::refuse_declare('declares without a name', "it is a named class: declare('name', \$config)");

            $name = $default;

        } elseif (!static::$named && ($name !== $default) && (static::INSTANCE_KIND !== 'service')) {

            return static::refuse_declare("declares '{$name}'", "it is a single class, live under '{$default}': declare(\$config), or set \$named for one built under several names");

        }

        if ($name === '') return static::refuse_declare('declares an empty name', 'give it a name');

        if ($held = Registry::get(static::INSTANCE_KIND, static::get_cache_group(), $name)) {

            $builder = Registry::get_builder(static::INSTANCE_KIND, static::get_cache_group(), $name) ?? 'no app';

            static::refuse_declare("declares '{$name}', which " . $held::class . " already holds (built by {$builder})", "find it with get_instance('{$name}'), or make one owner declare it");

            return ($held instanceof static) ? $held : null;

        }

        if ($property) {

            if (isset($config[$property]) && (!is_scalar($config[$property]) || ((string) $config[$property] !== $name))) return static::refuse_declare("declares '{$name}' and gives another {$property}", "leave {$property} out: the name fills it");

            $config[$property] = $name;

        }

        return self::build($name, $config);

    }

    private static function build (string $key, mixed $data) : ?static {

        if (!static::may_build($data))       return null;
        if (!$instance = static::create($data)) return null;

        $kind  = static::INSTANCE_KIND;
        $group = static::get_cache_group();

        Registry::set($kind, $group, $key, $instance);

        if (($cache_key = $instance->get_cache_key()) && ((string) $cache_key !== $key) && !Registry::has($kind, $group, $cache_key)) Registry::set($kind, $group, $cache_key, $instance);

        Batch::defer(fn () => $instance->switch_on());

        return $instance;

    }

    // Throws under strict; outside it, one log line and null.
    protected static function refuse_declare (string $problem, string $fix) : ?static {

        Strict::report(static::class, $problem, $fix);

        return null;

    }

    protected static function get_cache_group () {

        return static::$cache_group;

    }

    protected static function get_data_key ($data) {

        return (is_null($data) || ($data === [])) ? static::class : (is_scalar($data) ? (string) $data : null);

    }

    // Kept first, then switched on: a find inside switch-on answers this instance.
    protected function switch_on () : void {}

    public static function prepare_data (mixed &$data) {}

    protected function get_cache_key () {

        $property = static::$cache_property;

        return $property ? $this->$property : null;
    
    }

}
