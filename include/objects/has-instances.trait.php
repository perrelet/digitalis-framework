<?php

namespace Digitalis;

trait Has_Instances {

    protected $id;

    protected static $instances = []; // ! Redeclare static $instances in child classes whose parent uses this trait. (Else the $instances member will be shared - see https://www.php.net/manual/en/language.oop5.late-static-bindings.php for more infomation)

    public static function get_instance () { // The first argument should be a uid for this class of object

        if (!func_num_args()) return null;

        $id = func_get_args()[0];

        if (!isset(static::$instances[$id])) {
            static::$instances[$id] = new static(...func_get_args());
            static::$instances[$id]->set_id($id);
        }

        return static::$instances[$id];

    }

    public static function get_instances () {

        return static::$instances;

    }

    //

    public function set_id ($id) {
		
		$this->id = $id;
		
	}

    public function get_id () {
		
		return $this->id;
		
	}

    public function is_first_instance () {

        return (bool) $this->id == array_key_first(static::$instances);

    }

}