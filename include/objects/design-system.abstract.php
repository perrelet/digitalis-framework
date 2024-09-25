<?php

namespace Digitalis;

abstract class Design_System extends Singleton {

    protected $version          = DIGITALIS_FRAMEWORK_VERSION;

    protected $colors           = [];
    protected $classes          = [];
    protected $variables        = [];
    protected $variable_folders = [];

    //

    public function enqueue_style ($src = '', $handle = null, $args = []) {

        if (!$handle) $handle = __NAMESPACE__ . '-front';

        $this->maybe_enqueue_style(function () {
            return !Builder_Manager::get_instance()->is_backend_ui();
        }, $src, $handle, $args);
    
    }

    public function enqueue_builder_style ($src = '', $handle = null, $args = []) {

        if (!$handle) $handle = __NAMESPACE__ . '-builder';

        $this->maybe_enqueue_style(function () {
            return Builder_Manager::get_instance()->is_backend_ui();
        }, $src, $handle, $args);
    
    }

    protected function maybe_enqueue_style ($condition, $src = '', $handle = null, $args = []) {

        $args = wp_parse_args($args, [
            'deps'     => [],
            'media'    => 'all',
            'priority' => 10,
        ]);

        add_action('wp_enqueue_scripts', function () use ($condition, $src, $handle, $args) {

            if (!$condition()) return;

            wp_enqueue_style($handle, $src, $args['deps'], $this->version, $args['media']);

        }, $args['priority']);
    
    }

    //

    public function get_colors () {
    
        return $this->colors;
    
    }

    public function add_colors ($colors, $args = []) {

        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($colors) foreach ($colors as $value => &$color) {

            if (!is_array($color)) $color = [
                'name'   => $color,
                'value'  => $value,
                'folder' => 'Default',
            ];

            $this->colors[$color['name']] = $color; 

        }
    
        if ($args['builders']) Builder_Manager::get_instance()->add_colors($colors, $args);
    
    }

    public function remove_colors ($colors) {
    
        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($colors) foreach ($colors as $name) {
        
            if (isset($this->colors[$name])) unset($this->colors[$name]);
        
        }

        if ($args['builders']) Builder_Manager::get_instance()->remove_colors($colors);
    
    }

    public function get_classes () {
    
        return $this->classes;
    
    }

    public function add_classes ($classes, $args = []) {
    
        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($classes) foreach ($classes as $name => &$class) {

            if (!is_array($class)) $color = [
                'name' => $class,
            ];

            $this->classes[$class['name']] = $class; 

        }

        if ($args['builders']) Builder_Manager::get_instance()->add_classes($classes, $args);
    
    }

    public function remove_classes ($classes) {

        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($classes) foreach ($classes as $name) {
        
            if (isset($this->classes[$name])) unset($this->classes[$name]);
        
        }
    
        if ($args['builders']) Builder_Manager::get_instance()->remove_classes($classes);
    
    }

    public function get_variables () {
    
        return $this->variables;
    
    }

    public function add_variables ($variables, $args = []) {

        $args = wp_parse_args($args, [
            'builders' => true,
            'scss'     => true,
        ]);

        if ($variables) foreach ($variables as $var => &$variable) {

            if (!is_array($variable)) $variable = [
                'name'  => $var,
                'value' => $variable,
            ];

            $this->variables[$variable['name']] = $variable; 

        }

        if ($args['builders']) Builder_Manager::get_instance()->add_variables($variables, $args);

        if ($args['scss']) add_filter('sassy-variables', function ($scss_vars) use ($variables) {

            if ($variables) foreach ($variables as $variable) {

                $scss_vars[$variable['name']] = "var(--{$variable['name']})";

            }

            return $scss_vars;

        });

    }

    public function remove_variables ($variables) {

        $args = wp_parse_args($args, [
            'builders' => true,
            'scss'     => true,
        ]);

        if ($variables) foreach ($variables as $name) if (isset($this->variables[$name])) unset($this->variables[$name]);

        if ($args['builders']) Builder_Manager::get_instance()->remove_variables($variables);

        if ($args['scss']) add_filter('sassy-variables', function ($scss_vars) use ($variables) {

            if ($variables) foreach ($variables as $name) if (isset($scss_vars[$name])) unset($scss_vars[$name]);

            return $scss_vars;

        });

    }

    public function get_variable_folders () {
    
        return $this->variable_folders;
    
    }

    public function add_variable_folders ($folders, $args = []) {

        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($folders) foreach ($folders as $name => &$folder) {

            if (!is_array($folder)) $folder = [
                'name' => $folder,
            ];

            $this->variable_folders[$folder['name']] = $folder; 

        }

        if ($args['builders']) Builder_Manager::get_instance()->add_variable_folders($folders, $args);

    }

    public function remove_variable_folders ($folders, $args = []) {

        $args = wp_parse_args($args, [
            'builders' => true,
        ]);

        if ($folders) foreach ($folders as $name) {

            if (isset($this->variable_folders[$name])) unset($this->variable_folders[$name]);

        }
    
        if ($args['builders']) Builder_Manager::get_instance()->remove_variable_folders($folders, $args);
    
    }


}