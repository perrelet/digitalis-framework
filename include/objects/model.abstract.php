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

    public static function get_class_name () {

        return apply_filters('digitalis_class_' . static::class, static::class);

    }

    public static function get_instance ($id = null) {

        $id = static::extract_id($id);
        if (is_null($id)) return null;

        $class_name = static::get_class_name();

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

        if ($ids) foreach ($ids as $id) $instances[] = static::get_instance($id);

        return $instances;

    }

    //

    protected $id;

    public function __construct ($id) {

        $this->id = $id;
        $this->init();

    }

    public function init () {}  // Override me.

    public function get_id () {

        return $this->id;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(self::$instances[static::get_class_name()]);

    }

}