<?php

namespace Digitalis;

use ReflectionClass;

abstract class App extends Singleton {

    use Autoloader;

    protected $reflection;
    protected $path;
    protected $url;

    public function __construct () {

        $this->reflection = new ReflectionClass(static::class);
        $this->path       = plugin_dir_path($this->reflection->getFileName());
        $this->url        = plugin_dir_url($this->path);

        add_action('plugins_loaded', [$this, 'boot']);

    }

    public function get_path () {

        return $this->path;

    }

    public function get_url () {

        return $this->url;

    }

    public function boot () {
    
        $this->load();
        $this->ensure_schema();

        $this->boot_shared();

        if (defined('WP_CLI') && WP_CLI) {
            $this->load_cli();
            $this->boot_cli();
            return;
        }

        if (wp_doing_cron()) {
            $this->load_cron();
            $this->boot_cron();
            return;
        }

        if (wp_doing_ajax()) {
            $this->load_ajax();
            $this->boot_ajax();
            // Do not return (ajax may need REST-ish scripts)
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $this->load_rest();
            $this->boot_rest();
            return;
        }

        if (is_admin()) {
            $this->load_admin();
            $this->boot_admin();
            return;
        }

        $this->boot_front();
    
    }

    public function load () {

        $this->autoload();
        $this->load_bricks_elements();

    }

    public function load_admin () {
        $this->autoload($this->path . '_admin');
    }

    public function load_cli(): void {
        $this->autoload($this->path . '_cli');
    }

    public function load_cron(): void {
        $this->autoload($this->path . '_cron');
    }

    public function load_ajax(): void {
        $this->autoload($this->path . '_ajax');
    }

    public function load_rest(): void {
        $this->autoload($this->path . '_rest');
    }

    public function boot_shared () {}
    public function boot_cli    () {}
    public function boot_cron   () {}
    public function boot_ajax   () {}
    public function boot_rest   () {}
    public function boot_admin  () {}
    public function boot_front  () {}

    //

    public function ensure_schema () {

        // $tables = new Table_Registry();
        // $logger = new Option_Migration_Logger();
        //
        // (new Migration_Runner($tables, $logger))
        //     ->migrate_module(Custom_Schema::class);

    }

    //

    public function load_feature ($file, $instantiate = null) {

        if (is_null($instantiate)) $instantiate = 'get_instance';

        return $this->load_class(DIGITALIS_LIBRARY_PATH . $file, $instantiate);

    }

    //

    public function load_bricks_elements () {

        add_action('init', function () {

            if (!defined('BRICKS_VERSION')) return;

            $path  = $this->path . '_bricks-elements';
            $names = $this->get_file_names($path);
            if ($names) foreach ($names as $name) \Bricks\Elements::register_element("{$path}/{$name}");
        
        }, 11);

    }

}