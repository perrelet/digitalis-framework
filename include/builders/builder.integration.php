<?php

namespace Digitalis;

abstract class Builder extends Integration implements Builder_Interface {

    protected $slug = 'builder';

    public static function instance_condition () : bool {
    
        return true;
    
    }

    public function is_backend () : bool {

        return false;

    }

    public function is_backend_content () : bool {

        return $this->is_backend();

    }

    public function is_backend_ui () : bool {

        return $this->is_backend();

    }

    public function get_classes () : array {
    
        // ..
    
    }

    protected function get_add_classes_args ($args = []) : array {

        return wp_parse_args($args, [
            'save'      => false,
            'overwrite' => false,
            'lock'      => true,
            'folder'    => false,
        ]);

    }

    public function add_classes ($classes, $args = []) {

        // ..

    }

    public function remove_classes ($classes, $args = []) {

        // ..

    }

    public function get_variables () : array {

        // ..

    }

    protected function get_modify_colors_args ($args = []) : array {
    
        return wp_parse_args($args, [
            'add'       => [],
            'remove'    => [],
            'overwrite' => true,
            'save'      => false,
            'folder'    => 'Default',
        ]);
    
    }

    protected function get_modify_variables_args ($args = []) : array {

        return wp_parse_args($args, [
            'add'       => [],
            'remove'    => [],
            'overwrite' => true,
            'save'      => false,
            'folder'    => false,
        ]);

    }

    protected function get_modify_variable_folders_args ($args = []) : array {
    
        return wp_parse_args($args, [
            'add'       => [],
            'remove'    => [],
            'overwrite' => true,
            'save'      => false,
        ]);
    
    }

    public function add_colors ($colors, $args = []) {

        // ..

    }

    public function remove_colors ($colors, $args = []) {

        // ..

    }

    public function add_variables ($variables, $args = []) {

        // ..

    }

    public function remove_variables ($variables, $args = []) {

        // ..

    }

    public function get_variable_folders () : array {

        // ..

    }

    public function add_variable_folders ($name, $args = []) {

        // ..

    }

    public function remove_variable_folders ($name, $args = []) {

        // ..

    }

    //

    public function get_slug () {

        return $this->slug;

    }

}