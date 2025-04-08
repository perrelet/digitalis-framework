<?php

namespace Digitalis;

abstract class Feature extends Factory {

    use Has_WP_Hooks;

    public static function load () {

        return call_user_func_array([static::class, 'get_instance'], func_get_args());

    }

    protected static function construct_instance ($instance, $data = []) {

        parent::construct_instance($instance, $data);

        $instance->add_hooks((array) $instance->get_hooks());
        $instance->run();

    }

    public function run () {}

    public function get_hooks () {

        return [];

    }

}