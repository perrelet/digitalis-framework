<?php

namespace Digitalis;

use ReflectionClass;

abstract class App extends Singleton {

    use Autoloader;

    protected $path;
    protected $url;

    protected $autoload       = [];
    protected $admin_autoload = [];

    public function __construct () {

        $reflector = new ReflectionClass(static::class);
        $this->path = plugin_dir_path($reflector->getFileName());
        $this->url  = plugin_dir_url($this->path);

        add_action('plugins_loaded', [$this, 'load']);
        if (is_admin()) add_action('plugins_loaded', [$this, 'load_admin']);

    }

    protected function filter_autoloads (&$autoloads) {

        // ...

    }

    protected function filter_admin_autoloads (&$autoloads) {

        // ...

    }

    protected function &get_autoloads () {

        $autoloads = [
            'utils'                     => false,
            'post-types'                => 'get_instance',
            'post-statuses'             => 'get_instance',
            'taxonomies'                => 'get_instance',
            'user-roles'                => 'get_instance',
            'features'                  => 'get_instance',
            'integrations'              => 'get_instance',
            'models'                    => false,
            'views'                     => false,
            'design-systems'            => false,
            'acf-blocks'                => true,
            'shortcodes'                => true,
            'routes'                    => 'get_instance',
        ];

        if (defined('WC_PLUGIN_FILE')) $autoloads = array_merge($autoloads, [
            'woocommerce/models'        => false,
            'woocommerce/account-pages' => 'get_instance',
            'woocommerce/product-types' => 'get_instance',
        ]);

        $autoloads = wp_parse_args($this->autoload, $autoloads);

        $this->filter_autoloads($autoloads);

        return $autoloads;

    }

    protected function get_admin_autoloads () {

        $autoloads =  wp_parse_args($this->admin_autoload, [
            'admin/features'  => 'get_instance',
            'admin/pages'     => 'get_instance',
            'admin/tables'    => 'get_instance',
            'admin/metaboxes' => 'get_instance',
        ]);

        $this->filter_admin_autoloads($autoloads);

        return $autoloads;

    }

    public function load () {

        $this->autoload($this->get_autoloads());

        add_action('init', function () {

            if (!defined('BRICKS_VERSION')) return;

            $path  = $this->path . 'bricks-elements';
            $names = $this->get_file_names($path);
            if ($names) foreach ($names as $name) \Bricks\Elements::register_element("{$path}/{$name}");
        
        }, 11);
    
    }

    public function load_admin () {

        $this->autoload($this->path . 'admin', 'get_instance', false);
        $this->autoload($this->get_admin_autoloads());
    
    }

}