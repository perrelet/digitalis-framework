<?php

namespace Digitalis;

class Builder_Manager extends Singleton {

    use Autoloader;

    protected $builders = null;

    public function __construct () {

        $this->load_builders();
        
    }

    public function __call ($method, $args) {

        $args = array_merge([$method], $args);
    
        return call_user_func_array([$this, 'call_builders'], $args);
    
    }

    public function load_builders () {

        $this->builders = [];

        $builders = $this->autoload(__DIR__, 'get_instance', true, 'builder.php');

        if ($builders) foreach ($builders as $builder) {
            
            if ($builder->is_active()) $this->builders[$builder->get_slug()] = $builder;
            
        }

        return $this->builders;

    }

    public function get_builder ($slug = null) {
    
        if (!$this->builders) return null;

        $key = is_null($slug) ? array_key_first($this->builders) : $slug;

        return $this->builders[$key] ?? null;
    
    }

    public function get_builders () {

        return $this->builders;

    }

    public function query_builders ($method) {

        if ($builders = $this->get_builders()) foreach ($builders as $builder) {

            if (call_user_func([$builder, $method])) return $builder;

        }

        return false;

    }

    public function call_builders ($method, $data = null, $args = []) {

        $result = [];

        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($builders = $this->get_builders()) foreach ($builders as $builder) {

            if (is_array($args['builders']) && !in_array($builder->get_slug(), $args['builders'])) continue;

            $result[$builder->get_slug()] = call_user_func([$builder, $method], $data, $args);

        }

        return $result;

    }

    // Builder States

    public function is_backend () {

        return $this->query_builders('is_backend');
        
    }

    public function is_backend_content () {
        
        return $this->query_builders('is_backend_content');
        
    }

    public function is_backend_ui () {
        
        return $this->query_builders('is_backend_ui');
        
    }

    // Deprecated: Will be removed in a future version in favor of a utlity class system.

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