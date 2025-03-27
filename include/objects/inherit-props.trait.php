<?php

namespace Digitalis;

Trait Inherits_Props {

    protected static $prop_storage = [];

    public static function get_inherited_props () {
    
        return property_exists(static::class, 'inherited_props') ? static::$inherited_props : [];
    
    }

    public static function get_inherited_prop ($prop, $merge = []) {

        if (!$inherited_props = static::get_inherited_props())  return;
        if (!in_array($prop, $inherited_props))                 return;

        if (!isset(self::$prop_storage[static::class]))         self::$prop_storage[static::class] = array_fill_keys($inherited_props, null);
        if (is_null(self::$prop_storage[static::class][$prop])) self::$prop_storage[static::class][$prop] = static::inherit_merge_array($prop, $merge);

        return self::$prop_storage[static::class][$prop];

    }

    protected static function inherit_merge_array ($prop, $merge = []) {
    
        $class = static::class;
        $value = $class::$$prop;

        while ($class = get_parent_class($class)) {

            if (!property_exists($class, $prop)) continue;

            foreach ($merge as $key) {

                if (isset($class::$$prop[$key]) && is_array($class::$$prop[$key]) && $class::$$prop[$key]) {

                    if (!isset($value[$key])) $value[$key] = [];

                    $value[$key] = array_merge((array) $class::$$prop[$key], (array) $value[$key]);
                    
                    static::make_list_unique($value[$key]);

                }

            }

            $value = array_merge((array) $class::$$prop, (array) $value);

        }

        static::make_list_unique($value);

        return $value;
    
    }

    protected static function deep_parse_args ($args, $defaults = [], $merge = []) {
    
        $args = wp_parse_args((array) $args, (array) $defaults);

        foreach ($merge as $key) {

            if (!isset($args[$key]) || !is_array($args[$key])) continue;

            $args[$key] = wp_parse_args((array) $args[$key], (array) ($defaults[$key] ?? []));
            static::make_list_unique($args[$key]);

        }

        return $args;
    
    }

    protected static function make_list_unique (&$array) {
    
        if (array_is_list($array)) $array = array_values(array_unique($array, SORT_REGULAR));
    
    }

}