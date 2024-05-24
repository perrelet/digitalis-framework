<?php

namespace Digitalis;

use Exception;

class Model extends Factory {

    protected static $instances = [];

    public static function process_data (&$data) {}

    public static function extract_id ($data = null) {

        if ($data instanceof self) return $data->get_id();

        return $data;

    }

    public static function extract_uid ($id, $data = null) {

        return $id;

    }

    public static function validate ($data, $uid, $id) {

        return true;

    }

    public static function validate_id ($id) {

        return ($id > 0);

    }

    public static function get_class_name ($data, $uid, $id) {

        return Call::get_class_name(static::class, [
            'id'   => $id,
            'uid'  => $uid,
            'data' => $data,
        ]);

    }

    public static function get_instance ($data = null) {

        static::process_data($data);
        $id  = static::extract_id($data);
        $uid = static::extract_uid($id, $data);

        if (is_null($uid) || is_null($id)) return null;

        $class_name = static::get_class_name($data, $uid, $id);

        if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
        
        if (!isset(self::$instances[$class_name][$uid])) {
            
            if (static::validate($data, $uid, $id) && static::validate_id($id)) {

                $model = new $class_name($data, $uid, $id);
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

    public function __construct ($data = null, $uid = null, $id = null) {

        if (is_null($uid)) {

            static::process_data($data);
            $id  = static::extract_id($data);
            $uid = static::extract_uid($id, $data);

        }

        $this->data = $data;
        $this->uid  = $uid;
        $this->id   = $id;

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

        return $this->id == array_key_first(self::$instances[static::get_class_name($this->data, $this->uid, $this->id)]);

    }

    public function get_global_var () {

        return static::class;

    }

}