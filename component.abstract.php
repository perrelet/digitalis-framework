<?php

namespace Digitalis;

abstract class Component extends Base implements Component_Template {
    
    public static $count = 0;
    protected $instance;
    protected $params;
    protected $defaults = [];

    public function __construct ($params = []) {

        static::$count++;
        $this->instance = static::$count;

        $this->params = wp_parse_args($params, $this->defaults);

    }

    public function init () {

        static::$count = 0; // https://stackoverflow.com/questions/17632848/php-sub-class-static-inheritance-children-share-static-variables

    }

    public function render () {

        $this->render_component();

    }

    public function is_first () {

        return $this->instance == 1;

    }

    public function get_instance () {

        return $this->instance;

    }

}