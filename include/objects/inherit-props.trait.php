<?php

namespace Digitalis;

Trait Inherits_Props {

    public static function inherit_static_props ($props) {
    
        if ($props) foreach ($props as $prop => $merge) {

            if (!is_array($merge)) $merge = [];
        
            static::inherit_static_prop($prop, $merge);
        
        }
    
    }
 
    public static function inherit_static_prop ($prop, $merge = []) {
    
        $class = static::class;
        $value = static::$$prop;

        while ($class = get_parent_class($class)) {

            if (!property_exists($class, $prop)) continue;

            if ($merge) foreach ($merge as $key) {

                if (isset($class::$$prop[$key]) && is_array($class::$$prop[$key]) && $class::$$prop[$key]) {

                    if (!isset($value[$key])) $value[$key] = [];

                    $value[$key] = wp_parse_args($value[$key], $class::$$prop[$key]);
                    
                    if (array_is_list($value[$key])) $value[$key] = array_unique($value[$key], SORT_REGULAR);

                }

            }

            $value = wp_parse_args($value, $class::$$prop);

        }

        static::class::$$prop = $value;
    
    }

}