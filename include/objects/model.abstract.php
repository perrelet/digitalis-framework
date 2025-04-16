<?php

namespace Digitalis;

use Exception;

class Model extends Factory {

    public static function get_auto_instantiation () {
        
        return false;
    
    }

    protected static $instances = [];

    public static function prepare_data (&$data) {}

    public static function extract_id ($data = null) {

        if (is_numeric($data))     return (int) $data;
        if ($data instanceof self) return $data->get_id();
        if (!is_scalar($data))     return null;

        return $data;

    }

    public static function validate_data ($data) {

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

    public static function get_class_name ($id, $auto_resolve = null) {

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
        ]);

    }

    public static function get_instance ($data = null, $auto_resolve = null) {

        static::prepare_data($data);

        if (is_null($data) && method_exists(static::class, 'get_global_id')) {
            $id = static::get_global_id();
        } else {
            $id = static::extract_id($data);
        }

        if (is_null($id)) return null;

        $class_name = static::get_class_name($id, $auto_resolve);

        if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
        
        if (!isset(self::$instances[$class_name][$id])) {

            if (static::validate_data($data) && static::validate_id($id)) {

                $model = new $class_name($id);
                $model->init();

                self::$instances[$class_name][$id] = $model;

            } else {

                self::$instances[$class_name][$id] = null;

            }

        }

        return self::$instances[$class_name][$id];

    }

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

    public static function static_init () {

        $specificity = static::get_specificity();
        $parent      = static::class;

        while ($parent = get_parent_class($parent)) {

            if (!property_exists($parent, 'class_map')) break;
            if (!isset(static::$class_map[$parent]))    static::$class_map[$parent] = [];
            
            static::$class_map[$parent][static::class] = $specificity;

        }
    
    }

    //

    protected $id;
    protected $is_new;

    public function __construct ($data = null) {

        $this->is_new = is_int($data) ? $data < 0 : true;

        if (is_int($data)) {

            $this->id = $data;
            $this->hydrate_instance();

        } else {

            $this->id = $this->generate_uuid($data);

            $this->build_instance($data);

            if (!isset(self::$instances[static::class]))            self::$instances[static::class] = [];
            if (!isset(self::$instances[static::class][$this->id])) self::$instances[static::class][$this->id] = $this;

        }

    }

    protected function generate_uuid ($data) {

        return spl_object_id((object) $data);

    }

    protected function build_instance   ($data) {}
    protected function hydrate_instance ()      {}

    public function init () {

        // ...

    }

    //

    public function get_id () {

        return $this->id;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(self::$instances[static::get_class_name($this->id)]);

    }

    public function is_new () {
    
        return $this->is_new;
    
    }

    public function get_global_var () {

        return static::class;

    }

}