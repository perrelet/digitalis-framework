<?php

namespace Digitalis;

use RuntimeException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Editor_Manager extends Singleton {

    use Autoloader;

    protected array $editors   = [];
    protected array $generators = [];

    public function __construct () {

        $this->autoload_editors();
        //$this->autoload_elements();

    }

    public function __call ($method, $args) {

        $args = array_merge([$method], $args);
    
        return call_user_func_array([$this, 'call_editors'], $args);
    
    }

    public function autoload_editors () {

        $this->editors = [];

        if ($editors = $this->autoload(__DIR__, true, 'editor.php')) foreach ($editors as $editor) {

            $this->editors[$editor->get_slug()] = $editor;
            
        }

        return $this->editors;

    }

    public function get_editor ($slug = null) {
    
        if (!$this->editors) return null;

        $key = is_null($slug) ? array_key_first($this->editors) : $slug;

        return $this->editors[$key] ?? null;
    
    }

    public function get_editors () {

        return $this->editors;

    }

    public function query_editors ($method) {

        if ($editors = $this->get_editors()) foreach ($editors as $editor) {

            if (call_user_func([$editor, $method])) return $editor;

        }

        return false;

    }

    public function call_editors ($method, $data = null, $args = []) {

        $result = [];

        $args = wp_parse_args($args, [
            'editors' => true,
        ]);

        if ($editors = $this->get_editors()) foreach ($editors as $editor) {

            if (is_array($args['editors']) && !in_array($editor->get_slug(), $args['editors'])) continue;

            $result[$editor->get_slug()] = call_user_func([$editor, $method], $data, $args);

        }

        return $result;

    }

    // Builder States

    public function is_backend () {

        return $this->query_editors('is_backend');
        
    }

    public function is_backend_content () {
        
        return $this->query_editors('is_backend_content');
        
    }

    public function is_backend_ui () {
        
        return $this->query_editors('is_backend_ui');
        
    }

    //

    public function autoload_elements () {

        foreach ($this->get_editors() as $editor) {

            $editor->register_elements($this->get_cache_dir($editor));

        }

    }

    public function autoload_generators () {

        $this->autoload(__DIR__, true, 'control-mapper.php');
        $this->autoload(__DIR__, true, 'element-generator.php');

    }

    public function register_generators () : void {

        $this->autoload_generators();

        foreach ($this->get_editors() as $editor) {

            if ($generator_class = $editor->get_generator_class()) {

                $mapper_class = $editor->get_control_mapper_class();

                if (!class_exists($generator_class)) throw new RuntimeException("Generator class [{$generator_class}] not found.");
                if (!class_exists($mapper_class))    throw new RuntimeException("Control mapper class [{$mapper_class}] not found.");

                $generator = new $generator_class(
                    $editor->get_slug(),
                    new $mapper_class($editor->get_slug()),
                    $this->get_cache_dir($editor)
                );

                $this->register_generator($editor->get_slug(), $generator);

            }

        }

    }

    public function register_generator (string $editor_slug, /* Editor_Element_Generator */ $generator) : void {

        $this->generators[$editor_slug] = $generator;

    }

    public function get_generators () {

        return $this->generators;

    }

    public function get_generator (string|Editor $editor) {

        $slug = ($editor instanceof Editor) ? $editor->get_slug() : $editor;

        return $this->generators[$slug] ?? null;

    }

    public function generate_elements () {

        $this->reset_cache_dir();
        $this->register_generators();

        $class_names = [];

        foreach (View::get_loaded_views() as $view_class) {

            if (!$editors = $view_class::get_editors()) continue;

            if (in_array('all', $editors)) {

                $generators = $this->get_generators();

            } else {

                $generators = [];

                foreach ($editors as $editor) {

                    if ($generator = $this->get_generator($editor)) $generators[] = $generator;

                }

            }

            foreach ($generators as $generator) {

                if ($class_name = $generator->generate($view_class)) $class_names[] = $class_name;

            }

        }

        return $class_names;

    }

    //

    protected function get_cache_dir (?Editor $editor = null) : string {

        $dir = WP_CONTENT_DIR . '/lattice-wp/editors/';

        if ($editor instanceof Editor) $dir .= $editor->get_slug() . '/';

        return $dir;

    }

    protected function reset_cache_dir () : void {

        $cache_dir = $this->get_cache_dir();
        if (!is_dir($cache_dir)) return;

        if (strpos(realpath($cache_dir), realpath(WP_CONTENT_DIR)) !== 0) throw new RuntimeException("Refusing to wipe non-content directory: {$cache_dir}");

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) if ($item->isDir()) {

            rmdir($item->getPathname());

        } else {

            unlink($item->getPathname());

        }

    }

    //
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