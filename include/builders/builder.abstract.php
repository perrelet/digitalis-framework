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

    protected static $utility_classes = [

        'inline',
        'xl', 'lg', 'sm',
        'xxxslow', 'xxslow', 'xslow', 'slow', 'reverse',
        'quick-slow', 'quick-slow-pseudo',
        'p-pad',
        'full', 'full-auto',
        'flex-center',

        'hide-lt-page', 'hide-lt-tablet', 'hide-lt-phone-landscape', 'hide-lt-phone-portrait', 
        'hide-gt-page', 'hide-gt-tablet', 'hide-gt-phone-landscape', 'hide-gt-phone-portrait',
        'hide-logged-in', 'hide-logged-out',

        'top-section', 'center-section', 'bottom-section', 'stretch-section', 'relative-section', 'merge', 'screen-height', 'explode',
        'cols-2', 'cols-center', 'cols-stretch', 'cols-2-page', 'phone-landscape', 'swap', 'remove-first', 'remove-last',

        'cover-pseudo', 'cover-before', 'cover-after', 'cover-both',

    ];

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

        return self::get_builder() ? call_user_func([self::get_builder(), $method]) : false;

    }

    public static function get_builder_slug () {

        return self::call('get_slug');

    }

    //

    public static function install () {

        static::install_classes();

    }

    public static function install_classes () {}

}