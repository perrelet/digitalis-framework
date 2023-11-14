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
    
        $this->autoload($this->path . 'utils', false);
        $this->autoload($this->path . 'post-types');
        $this->autoload($this->path . 'taxonomies');
        $this->autoload($this->path . 'features', 'load');
        $this->autoload($this->path . 'integrations');
        $this->autoload($this->path . 'models', false);
        $this->autoload($this->path . 'views', false);
        $this->autoload($this->path . 'acf-blocks', true);
        $this->autoload($this->path . 'shortcodes', true);
        $this->autoload($this->path . 'routes');

        if (defined('WC_PLUGIN_FILE')) {

            if (defined('WC_PLUGIN_FILE')) $this->autoload($this->path . 'woocommerce/account-pages');
            if (defined('WC_PLUGIN_FILE')) $this->autoload($this->path . 'woocommerce/product-types'); // product-type has an activation hoook... better soln?

        }
    
    }

    public function load_admin () {
    
        
    
    }

}