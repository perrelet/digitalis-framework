<?php

namespace Digitalis;

class Model {

    protected static $instances = [];

    public static function extract_id ($data = null) {

        if ($data instanceof self) return $data->get_id();

        return $data;

    }

    public static function extract_uid ($id, $data = null) {

        return $id;

    }

    public static function validate ($data) {

        return true;

    }

    public static function validate_id ($id) {

        return ($id > 0);

    }

    public static function get_class_name () {

        return apply_filters('Digitalis/Class/' . static::class, static::class);

    }

    public static function get_instance ($data = null) {

        $id = static::extract_id($data);
        $uid = static::extract_uid($id, $data);

        if (is_null($uid) || is_null($id)) return null;

        $class_name = static::get_class_name();

        if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
        
        if (!isset(self::$instances[$class_name][$uid])) {
            
            if (static::validate($data) && static::validate_id($id)) {

                self::$instances[$class_name][$uid] = new $class_name($id);
                self::$instances[$class_name][$uid]->init($data);

            } else {

                self::$instances[$class_name][$uid] = null;

            }

        }

        return self::$instances[$class_name][$uid];

    }

    public static function inst ($data = null) {
        
        return self::get_instance($data);
        
    }

    public static function get_instances ($ids) {

        $instances = [];

        if ($ids) foreach ($ids as $id) if ($instance = static::get_instance($id)) $instances[] = $instance;

        return $instances;

    }

    public static function get_all_instances ($class_name = null) {

        if ($class_name) {

            return isset(self::$instances[$class_name]) ? self::$instances[$class_name] : [];

        } else {

            return self::$instances;

        }

    }

    //

    protected $id;

    public function __construct ($id) {

        $this->id = $id;

    }

    public function init ($data = null) {}  // Override me.

    public function get_id () {

        return $this->id;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(self::$instances[static::get_class_name()]);

    }

    public function get_global_var () {

        return static::class;

    }

}