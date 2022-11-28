<?php

namespace Digitalis;

interface Builder_Interface {

    public static function get_slug ();
    public static function get_name ();
    public static function is_loaded ();
    public static function is_backend ();
    public static function is_backend_iframe ();

}

abstract class Builder implements Builder_Interface {

    protected static $builders = ['Oxygen'];
    protected static $builder;

    public static function get_builder () {

        if (is_null(self::$builder)) {

            self::$builder = false;

            foreach (self::$builders as $builder) {
    
                $class = "\\Digitalis\\{$builder}";

                if (call_user_func("{$class}::is_loaded")) {

                    self::$builder = $class;
                    break;

                }
    
            }

        }

        return self::$builder;

    }

    public static function call ($method) {

        return self::get_builder() ? call_user_func(self::get_builder() . "::" . $method) : false;

    }

    public static function get_builder_slug () {

        return self::call('get_slug');

    }

}