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

        return true;

    }

    public static function get_specificity () {
    
        return 0;
    
    }

    public static function get_auto_resolve () {
    
        return !static::get_specificity();
    
    }

    public static function get_uuid_prefix () {
    
        return 'new-';
    
    }

    public static function generate_uuid ($data) {

        return static::get_uuid_prefix() . wp_generate_uuid4();

    }

    public static function is_uuid ($data) {

        return is_string($data) && (substr($data, 0, strlen(static::get_uuid_prefix())) == static::get_uuid_prefix());

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

    protected static function resolve_data (&$data = [], $auto_resolve = null) {
    
        static::prepare_data($data);

        if (static::is_uuid($data)) {

            $id = $data;

        } else {

            $id = (is_null($data) && method_exists(static::class, 'get_global_id')) ?
                static::get_global_id() :
                static::extract_id($data);

        }

        return [$id, static::get_class_name($id, $auto_resolve)];
    
    }

    public static function create ($data = [], $auto_resolve = null) {

        [$id, $class_name] = static::resolve_data($data, $auto_resolve);

        if (is_null($id))                 return null;
        if ($class_name != static::class) return $class_name::create($data, false);

        $instance = new $class_name($data);
        $instance->init($data);

        return $instance;
    
    }

    public static function get_instance ($data = null, $auto_resolve = null) {

        [$id, $class_name] = static::resolve_data($data, $auto_resolve);

        if (is_null($id))                 return null;
        if ($class_name != static::class) return $class_name::get_instance($data, false);

        if (isset(self::$instances[$class_name][$id]))                  return self::$instances[$class_name][$id];
        if (!static::validate_data($data) || !static::validate_id($id)) return null;

        $instance = new $class_name($id);
        $instance->init($data);
        return $instance;

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

        if (is_scalar($data)) {

            $this->id     = $data;
            $this->is_new = false; //is_int($data) ? ($data < 0) : false;

            $this->hydrate_instance();

        } else {

            $this->id     = static::generate_uuid($data);
            $this->is_new = true;

            $this->build_instance($data);

        }

        $this->cache_instance();

    }

    protected function build_instance   ($data) {}
    protected function hydrate_instance ()      {}

    public function init ($data) {

        // ...

    }

    public function cache_instance () {
    
        if (!isset(self::$instances[static::class])) self::$instances[static::class] = [];
        self::$instances[static::class][$this->id] = $this;
        return $this;
    
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