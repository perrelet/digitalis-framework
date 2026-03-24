<?php

namespace Digitalis;

abstract class Editor extends Singleton implements Editor_Interface {

    use Autoloader;

    protected $slug = 'editor';

    public static function instance_condition () : bool {
    
        return true;
    
    }

    public function __construct () {

        

    }

    public function get_slug () {

        return $this->slug;

    }

    public function get_generator_class () {

        // return Editor_Element_Generator::class;

    }

    public function get_control_mapper_class () {

        // return Control_Mapper::class;

    }

    public function register_elements ($path) {

        $path = realpath($path);
        $path = rtrim($path, '/') . '/';

        if (!is_dir($path)) return;

        foreach ($files = glob($path . '*.php') as $file) {

            $this->register_element($file);

        }


    }

    public function register_element ($file) {

        include $file;

        $class_name = basename($file, '.' . pathinfo($file)['extension']);

        if (doing_action('plugins_loaded')) {

            new $class_name();

        } else {

            add_action('plugins_loaded', function () use ($class_name) { new $class_name(); });

        }

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
    
        // class Editor_CSS_Selector
    
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

        // .. class Editor_Color

    }

    public function remove_colors ($colors, $args = []) {

        // ..

    }

    public function add_variables ($variables, $args = []) {

        // .. class Editor_CSS_Variable

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

    public function register_component ($name, $args = []) {

        // .. class Editor_Component
        //      - Editor_Controls / Editor_Fields
        //      - 

    }

    public function get_component ($name) {

        // ..

    }

    public function render_component ($name, $p = []) {

        // ..

    }

    //



}