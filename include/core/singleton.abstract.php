<?php

declare(strict_types=1);

namespace Lattice;

use Exception;

abstract class Singleton extends Creational {

    use Auto_Instantiate, Is_Registrant;

    const INSTANCE_KIND = 'registrant';

    public function __construct () {}
    public function __clone()        { throw new Exception("One for all and all for one."); }
    public function __wakeup()       { throw new Exception("You may not dream me into existence."); }

    // init() is a plain Singleton's step, not run(): a singleton's run() may be a job (d-pace's seeders).
    protected function switch_on () : void {

        $this->init();
        $this->add_hooks((array) $this->get_hooks());

    }

    public static function get_instance () {

        $class_name = Call::get_class_name(static::class);
        
        if ($class_name != static::class)         return $class_name::get_instance();
        if (!$class_name::instance_condition())   return null;
        if ($held = Registry::get(self::INSTANCE_KIND, self::class, $class_name)) return $held;

        $instance = new $class_name();
        Registry::set(self::INSTANCE_KIND, self::class, $class_name, $instance);
        static::construct_instance($instance);
        $instance->switch_on();

        return $instance;

    }

    public function init () {}

}