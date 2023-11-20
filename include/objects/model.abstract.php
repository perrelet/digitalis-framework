<?php

namespace Digitalis;

use Exception;

class Model {

    protected static $instances = [];

    public static function process_data (&$data) {}

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

        return apply_filters('Digitalis/Class/' . str_replace('\\', '/', ltrim(static::class, '\\')), static::class);

    }

    public static function get_instance ($data = null) {

        static::process_data($data);
        $id  = static::extract_id($data);
        $uid = static::extract_uid($id, $data);

        if (is_null($uid) || is_null($id)) return null;

        $class_name = static::get_class_name();

        if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
        
        if (!isset(self::$instances[$class_name][$uid])) {
            
            if (static::validate($data) && static::validate_id($id)) {

                $model = new $class_name($data, true);
                $model->id  = $id;
                $model->uid = $uid;
                $model->init();

                self::$instances[$class_name][$uid] = $model;

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

    protected $data;
    protected $id;
    protected $uid;

    public function __construct ($data = null, $factory_instance = false) {

        if ($factory_instance) {

            $this->data = $data;

        } else {

            static::process_data($data);
            $this->data = $data;
            $this->id   = static::extract_id($this->data);
            $this->uid  = static::extract_uid($this->id, $this->data);

            if (!static::validate($this->data))  throw new Exception("Invalid \$data was provided for the instantiation of a '" . static::class . "' model:\n" . print_r($this->data, true));
            if (!static::validate_id($this->id)) throw new Exception("An invalid \$id ('{$this->id}') was provided for the instantiation of a '" . static::class . "' model.");

            $this->init();

        }

    }

    public function init () {

        // ...

    }

    //

    public function get_id () {

        return $this->id;

    }

    public function get_uid () {

        return $this->uid;

    }

    public function get_data () {

        return $this->data;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(self::$instances[static::get_class_name()]);

    }

    public function get_global_var () {

        return static::class;

    }

}