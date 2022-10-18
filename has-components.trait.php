<?php

namespace Digitalis;

trait Has_Components {

    protected $components = [];

    public function load_components ($path) {

        foreach (glob($path . '/*.component.php') as $component_path) {
            include $component_path;
        }

    }
    
    public function component ($class_name, $params = [], $render = true) {

        $class_name = 'Digitalis\\' . $class_name;

        if (!class_exists($class_name)) return;

        $component = new $class_name($params);
        $component->init();

        if ($render) $component->render();

        $key = $class_name . "-" . $component->get_instance();
        $this->components[$key] = $component;

        return $component;

    }

    public function get_component ($class_name, $instance = 1) {

        $class_name = 'Digitalis\\' . $class_name;
        $key = $class_name . "-" . $instance;

        return isset($this->components[$key]) ? $this->components[$key] : null;

    }

}