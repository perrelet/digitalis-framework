<?php

namespace Digitalis;

abstract class Integration extends Singleton {

    use Has_WP_Hooks;

    protected static function construct_instance ($instance) {

        parent::construct_instance($instance);

        $instance->add_hooks((array) $instance->get_hooks());
        $instance->run();

    }
    
    public function run () {}

    public function get_hooks () {

        return [];

    }

}