<?php

namespace Digitalis;

class Builder_Manager extends Singleton {

    use Can_Load;

    protected $builders = null;

    public function init () {
        
        if (did_action('plugins_loaded')) {
            $this->load_builders();
        } else {
            add_action('plugins_loaded', [$this, 'load_builders']);
        }
        
    }

    public function load_builders () {

        $this->builders = [];

        $builders = $this->autoload('builder', __DIR__, 'builder.php', 'get_instance');

        if ($builders) foreach ($builders as $builder) {
            
            if ($builder->is_active()) $this->builders[] = $builder;
            
        }

    }

    public function get_builders () {
        
        if (is_null($this->builders)) throw new \Exception("You cannot get the builders before the 'plugins_loaded' action has been called.");

        return $this->builders;
        
    }

    public function query_builders ($method) {

        if ($builders = $this->get_builders()) foreach ($builders as $builder) {

            if (call_user_func([$builder, $method])) return $builder;

        }

        return false;

    }

    public function loop_builders ($method, $data = null, $args = []) {

        if ($builders = $this->get_builders()) foreach ($builders as $builder) {

            call_user_func([$builder, $method], $data, $args);

        }

    }

    public function is_backend () {

        return $this->query_builders('is_backend');
        
    }

    public function is_backend_content () {
        
        return $this->query_builders('is_backend_content');
        
    }

    public function is_backend_ui () {
        
        return $this->query_builders('is_backend_ui');
        
    }

    public function add_classes ($classes, $args = []) {

        $this->loop_builders('add_classes', $classes, $args);

    }

    public function remove_classes ($classes, $args = []) {

        $this->loop_builders('remove_classes', $classes, $args);

    }

    public function add_colors ($colors, $args = []) {

        $this->loop_builders('add_colors', $colors, $args);

    }

    public function remove_colors ($colors, $args = []) {

        $this->loop_builders('remove_colors', $colors, $args);

    }

    //

    public function get_utility_classes () {

        return include DIGITALIS_FRAMEWORK_PATH . 'scss/utilities.php';

    }

    public function add_utility_classes () {

        $this->add_classes($this->get_utility_classes());

    }
    
    public function remove_utility_classes () {

        $this->remove_classes($this->get_utility_classes());

    }

}