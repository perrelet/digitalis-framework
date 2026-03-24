<?php

namespace Digitalis;

abstract class Editor_Element_Generator implements Editor_Element_Generator_Interface {

    protected string         $editor_slug;
    protected Control_Mapper $control_mapper;
    protected string         $cache_dir;

    public function __construct (string $editor_slug, Control_Mapper $control_mapper, string $cache_dir) {

        $this->editor_slug   = $editor_slug;
        $this->control_mapper = $control_mapper;
        $this->cache_dir      = rtrim($cache_dir, '/') . '/';

    }

    public function get_editor_slug () {

        return $this->editor_slug;

    }

    public function register (string $view_class) {

        if (!$class_name = $this->generate($view_class)) return false;

        // .. Register class with page editor api

    }

    public function generate (string $view_class) {

        if (!is_a($view_class, View::class, true)) return false;

        $class_name = $this->generate_class_name($view_class);

        if (class_exists($class_name)) return false;

        $path = $this->cache_dir . $class_name . '.php';
        $code = $this->generate_php_code($class_name, $view_class);

        $this->create_cache_dir();
        file_put_contents($path, $code);

        return $class_name;

    }

    protected function create_cache_dir () {

        if (!file_exists($this->cache_dir)) {

            if (!mkdir($this->cache_dir, 0755, true) && !is_dir($this->cache_dir)) throw new RuntimeException("Failed to create directory: {$this->cache_dir}");

        }

        if (!is_writable($this->cache_dir)) {

            @chmod($this->cache_dir, 0755); // suppress warning, we'll check again

            if (!is_writable($this->cache_dir)) throw new RuntimeException("Directory exists but is not writable: {$this->cache_dir}");

        }

    }

    protected function generate_slug_name ($view_class) {

        return strtolower(str_replace(['\\', '_'], '-', 'lattice-' . $this->get_editor_slug() . '-' . $view_class));

    }

    protected function generate_class_name ($view_class) {

        return ucwords('Lattice_' . str_replace(['\\', '-'], '_', $this->get_editor_slug() . '_Element_' . $view_class), '_');

    }

    protected function generate_php_code (string $class_name, $view_class) : string {

        return <<<PHP
<?php
class $class_name {
}
PHP;
    }

    protected function get_controls ($view_class) {

        return $this->control_mapper->map_controls($view_class::get_controls());

    }

}

class PHP_Code {

    protected array  $lines  = [];
    protected string $indent = '    ';
    protected int    $level  = 0;

    public function __construct ($level = 2) {
    
        $this->level = $level;
    
    }

    public function __toString () {

        return $this->get_code();

    }

    public function get_code () : string {

        return implode("\n", $this->lines) . "\n";

    }

    public function get_lines () : array {

        return $this->lines;

    }

    public function indent (int $levels = 1) : self {

        $this->level += $levels;
        return $this;

    }

    public function outdent (int $levels = 1) : self {

        $this->level = max(0, $this->level - $levels);
        return $this;

    }

    public function append (string|self $code = '') : self {

        if ($code instanceof self) {

            $this->lines = array_merge($this->lines, $code->get_lines());

        } else {

            $this->lines[] = $code;

        }
    

        return $this;
    
    }

    public function line (string $code = '') : self {

        $this->lines[] = str_repeat($this->indent, $this->level) . $code;
        return $this;

    }

    public function block (string $header, callable $callback) : self {

        $this->line($header)->line('{')->indent();
        $callback($this);
        $this->outdent()->line('}');
        return $this;

    }

    public function export_var ($var) : string {

        $exported = var_export($var, true);
        return preg_replace('/^(?!\A)/m', str_repeat($this->indent, $this->level), $exported);

    }

}