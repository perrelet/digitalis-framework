<?php

namespace Digitalis;

use ReflectionClass;

abstract class App extends Singleton {

    use Autoloader;

    protected $path;
    protected $url;

    public function __construct () {

        $reflector = new ReflectionClass(static::class);
        $this->path = plugin_dir_path($reflector->getFileName());
        $this->url  = plugin_dir_url($this->path);

        add_action('plugins_loaded', [$this, 'load']);
        if (is_admin()) add_action('plugins_loaded', [$this, 'load_admin']);

    }

    public function load () {

        $this->autoload();

        add_action('init', function () {

            if (!defined('BRICKS_VERSION')) return;

            $path  = $this->path . '_bricks-elements';
            $names = $this->get_file_names($path);
            if ($names) foreach ($names as $name) \Bricks\Elements::register_element("{$path}/{$name}");
        
        }, 11);
    
    }

    public function load_admin () {

        $this->autoload($this->path . '_admin');

    }

    public function load_feature ($file, $instantiate = null) {

        if (is_null($instantiate)) $instantiate = 'get_instance';

        return $this->load_class(DIGITALIS_LIBRARY_PATH . $file, $instantiate);

    }

}