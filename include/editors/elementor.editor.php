<?php

namespace Digitalis;

class Elementor extends Editor {

    protected $slug = 'elementor';

    public static function instance_condition () : bool {

        return is_plugin_active('elementor/elementor.php');

    }

}