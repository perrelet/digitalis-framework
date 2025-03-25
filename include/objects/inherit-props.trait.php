<?php

namespace Digitalis;

Trait Inherits_Props {

    protected static function inherit_static_props ($props) {
    
        if ($props) foreach ($props as $prop => $merge) {

            if (!is_array($merge)) $merge = [];
        
            static::inherit_static_prop($prop, $merge);
        
        }
    
    }
 
    protected static function inherit_static_prop ($prop, $merge = []) {

        static::class::$$prop = static::inherit_merge_array($prop, $merge);
    
    }

    protected static function inherit_merge_array ($prop, $merge = []) {
    
        $class = static::class;
        $value = $class::$$prop;

        while ($class = get_parent_class($class)) {

            if (!property_exists($class, $prop)) continue;

            if ($merge) foreach ($merge as $key) {

                if (isset($class::$$prop[$key]) && is_array($class::$$prop[$key]) && $class::$$prop[$key]) {

                    if (!isset($value[$key])) $value[$key] = [];

                    $value[$key] = wp_parse_args((array) $value[$key], (array) $class::$$prop[$key]);
                    
                    if (array_is_list($value[$key])) $value[$key] = array_unique($value[$key], SORT_REGULAR);

                }

            }

            $value = wp_parse_args($value, $class::$$prop);

        }

        return $value;
    
    }

    protected static function deep_parse_args ($args, $defaults = [], $merge = []) {
    
        $args = wp_parse_args((array) $args, (array) $defaults);

        foreach ($merge as $key) {

            if (!isset($args[$key]) || !is_array($args[$key])) continue;

            $args[$key] = wp_parse_args((array) $args[$key], (array) ($defaults[$key] ?? []));

        }

        return $args;
    
    }

}