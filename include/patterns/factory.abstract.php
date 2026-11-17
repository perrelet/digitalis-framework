<?php

namespace Digitalis;

use ReflectionClass;

abstract class Factory extends Creational {

    use Auto_Instantiate, Dependency_Injection;

    protected static $instances = [];
    protected static $cache_group = '__global__';
    protected static $cache_property = null;

    private static $refused = [];

    public static function get_group_instances ($group = null) {

        $group ??= static::get_cache_group();
        return self::$instances[$group] ?? [];

    }

    public static function get_instance_map () {

        $map = [];
        foreach (self::$instances as $group => $instances) {
            $map[$group] = [];
            foreach ($instances as $key => $instance) {
                $map[$group][$key] = $instance::class;
            }
        }
    
        return $map;
    
    }

    public static function instance_condition ($data = []) {
    
        return parent::instance_condition();
    
    }

    protected static function construct_instance ($instance, $data = []) {

        parent::construct_instance($instance);

    }

    protected static function apply_config ($instance, $data = []) {

        if ($data && is_array($data)) foreach ($data as $prop => $value) {

            if (property_exists($instance, $prop)) $instance->$prop = $value;

        }

    }

    // The config is on the instance before its constructor runs, so the constructor reads it and what it sets stands.
    public static function create ($data = []) {

        if (is_scalar($data)) return;

        static::prepare_data($data);

        $reflection = new ReflectionClass(static::class);
        $instance   = $reflection->newInstanceWithoutConstructor();

        static::apply_config($instance, $data);

        if ($constructor = $reflection->getConstructor()) $constructor->invokeArgs($instance, static::method_inject($constructor, array_slice(func_get_args(), 2)));

        static::construct_instance($instance, $data);

        return $instance;

    }

    public static function get_instance ($data = null) {

        $class_name = Call::get_class_name(static::class, $data);
        if ($class_name != static::class) return $class_name::get_instance($data);

        static::prepare_data($data);
        if (!static::instance_condition($data)) return null;

        $group = static::get_cache_group();
        $key   = static::get_data_key($data);

        self::$instances[$group] ??= [];

        if (!is_null($key) && ((self::$instances[$group][$key] ?? null) instanceof static)) return self::$instances[$group][$key]; // a name another class holds is theirs

        // An array config that names a held name: a name is taken once. The holder answers its own class and stays in place for another.
        $name = (is_array($data) && static::$cache_property && is_scalar($data[static::$cache_property] ?? null)) ? (string) $data[static::$cache_property] : null;
        $held = is_null($name) ? null : (self::$instances[$group][$name] ?? null);

        if ($held) {

            self::refuse('get_instance', "builds '{$name}' from a config, which " . $held::class . ' already holds', "find it with inst('{$name}'), name a new one with declare(), or build one nothing finds with create()");

            if ($held instanceof static) return $held;

        }

        if ($instance = static::create($data)) {

            if (($cache_key = $instance->get_cache_key()) && (!$held || ($cache_key !== $name))) self::$instances[$group][$cache_key] = $instance;
            if (!is_null($key) && ($key !== $cache_key) && !isset(self::$instances[$group][$key])) self::$instances[$group][$key] = $instance;

        }

        return $instance;

    }

    /**
     * Builds once and keeps the instance under $name: a name is taken once, and inst($name) finds it.
     *
     * @return ?static
     */
    public static function declare (string $name, array $config = []) {

        $class_name = Call::get_class_name(static::class, $config);
        if ($class_name != static::class) return $class_name::declare($name, $config);

        if ($name === '') return static::refuse_declare('declares an empty name', 'give it a name');

        $group = static::get_cache_group();

        if ($held = self::$instances[$group][$name] ?? null) {

            static::refuse_declare("declares '{$name}', which " . $held::class . ' already holds', "find it with inst('{$name}') or declare another name");

            return ($held instanceof static) ? $held : null;

        }

        if ($property = static::$cache_property) {

            if (isset($config[$property]) && (!is_scalar($config[$property]) || ((string) $config[$property] !== $name))) return static::refuse_declare("declares '{$name}' and gives another {$property}", "leave {$property} out: the name fills it");

            $config[$property] = $name;

        }

        if (!static::instance_condition($config)) return null;
        if (!$instance = static::create($config))  return null;

        self::$instances[$group][$name] = $instance;

        if (($key = $instance->get_cache_key()) && !isset(self::$instances[$group][$key])) self::$instances[$group][$key] = $instance;

        return $instance;

    }

    protected static function refuse_declare (string $problem, string $fix) : ?static {

        return self::refuse('declare', $problem, $fix);

    }

    // One notice per class and problem in a request, and null. Private, so a subclass may have a refuse() of its own.
    private static function refuse (string $call, string $problem, string $fix) : ?static {

        $refusal = static::class . " {$problem}";

        if (!isset(self::$refused[$refusal])) {

            self::$refused[$refusal] = true;
            _doing_it_wrong(static::class . "::{$call}", esc_html("{$refusal}: {$fix}."), '');

        }

        return null;

    }

    protected static function get_cache_group () {

        return static::$cache_group;

    }

    protected static function get_data_key ($data) {

        return (is_null($data) || ($data === [])) ? static::class : (is_scalar($data) ? (string) $data : null);

    }

    public static function prepare_data (&$data) {}

    protected function get_cache_key () {

        $property = static::$cache_property;

        return $property ? $this->$property : null;
    
    }

}
