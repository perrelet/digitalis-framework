<?php

namespace Digitalis;

use Exception;

class Model extends Factory {

    public static function auto_init () {
        
        return false;
    
    }

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

    public static function get_specificity () {
    
        return 0;
    
    }

    public static function get_auto_resolve () {
    
        return !static::get_specificity();
    
    }

    public static function get_class_name ($data, $uid, $id, $auto_resolve = null) {

        $class_name = static::class;

        if (is_null($auto_resolve)) $auto_resolve = static::get_auto_resolve();

        if ($auto_resolve) {

            $specificity = static::get_specificity();

            if (static::$class_map[static::class] ?? 0) foreach (static::$class_map[static::class] as $sub_class => $class_specificity) {

                if (($class_specificity >= $specificity) && $sub_class::validate_id($id)) {

                    $class_name  = $sub_class;
                    $specificity = $class_specificity;

                }

            }

        }

        return Call::get_class_name($class_name, [
            'id'   => $id,
            'uid'  => $uid,
            'data' => $data,
        ]);

    }

    public static function get_instance ($data = null, $auto_resolve = null) {

        static::process_data($data);
        $id  = static::extract_id($data);
        $uid = static::extract_uid($id, $data);

        if (is_null($uid) || is_null($id)) return null;

        $class_name = static::get_class_name($data, $uid, $id, $auto_resolve);

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

    /* public static function inst ($data = null) {
        
        return self::get_instance($data);
        
    } */

    public static function get_instances ($ids) {

        $instances = [];

        if ($ids) foreach ($ids as $id) if ($instance = static::get_instance($id)) $instances[] = $instance;

        return $instances;

    }

    public static function get_all_instances () {

        return self::$instances[static::class] ?? [];

    }

    //

    protected static $class_map = [];

    public static function hello () {

        $specificity = static::get_specificity();
        $parent      = static::class;

        while ($parent = get_parent_class($parent)) {

            if (!property_exists($parent, 'class_map')) break;
            if (!isset(static::$class_map[$parent]))    static::$class_map[$parent] = [];
            
            static::$class_map[$parent][static::class] = $specificity;

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