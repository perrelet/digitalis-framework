<?php

namespace Digitalis;

class Model {

    protected static $instances = [];

    public static function extract_id ($id = null) {

        if ($id instanceof self) $id = $id->get_id();

        return $id;

    }

    public static function validate_id ($id) {

        return true;

    } 

    public static function get_instance ($id = null) {

        $id = static::extract_id($id);
        if (is_null($id)) return null;

        $class_name = apply_filters('digitalis_class_' . static::class, static::class);

        if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
        
        if (!isset(self::$instances[$class_name][$id])) {
            
            if (static::validate_id($id)) {

                self::$instances[$class_name][$id] = new $class_name($id);

            } else {

                self::$instances[$class_name][$id] = null;

            }

        }

        return self::$instances[$class_name][$id];

    }

    public static function get_instances ($ids) {

        $instances = [];

        if ($ids) foreach ($ids as $id) {
            $instances[] = static::get_instance($id);
        }
        return $instances;

    }

}