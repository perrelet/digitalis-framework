<?php

namespace Digitalis;

use ReflectionClass;

abstract class App extends Factory {

    use Autoloader;

    protected static $cache_group = self::class;

    protected $reflection;
    protected $path;
    protected $url;

    public function __construct () {

        $this->reflection = new ReflectionClass(static::class);
        $this->path       = plugin_dir_path($this->reflection->getFileName());
        $this->url        = plugin_dir_url($this->path);

        add_action('plugins_loaded', [$this, 'boot'], $this->get_boot_priority());

    }

    // Boots after construction, once get_instance() has cached the app.
    protected function get_boot_priority () : int {

        return doing_action('plugins_loaded') ? $GLOBALS['wp_filter']['plugins_loaded']->current_priority() + 1 : 10;

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

    protected array $loading_features = [];
    protected array $loaded_features  = [];

    // Requirements load first, with their defaults.
    public function load_feature ($file, $options = null) {

        return $this->load_feature_as($file, $options, false);

    }

    protected function load_feature_as ($file, $options, bool $required) {

        if (!$feature = $this->find_feature($file))         return $this->refuse_feature("No feature {$file}.");
        if (isset($this->loading_features[$feature]))       return $this->refuse_feature("{$feature} requires itself through {$file}.");
        if (!$feature::instance_condition($options ?? []))  return $this->refuse_feature("{$feature} did not load: its instance_condition() refused.");

        if (!is_null($options) && (($this->loaded_features[$feature] ?? '') === 'requirement')) $this->refuse_feature("{$feature} already loaded as a requirement, so its options were ignored: load it before what requires it.");

        $this->loading_features[$feature] = true;

        $missing = is_subclass_of($feature, Feature::class) ? array_filter($feature::get_requirements(), fn ($each) => !$this->load_feature_as($each, null, true)) : [];

        unset($this->loading_features[$feature]);

        if ($missing)                          return $this->refuse_feature("{$feature} needs " . implode(', ', $missing) . ', which did not load.');
        if (!$feature::get_instance($options)) return $this->refuse_feature("{$feature} did not load.");

        $this->loaded_features[$feature] ??= $required ? 'requirement' : 'site';

        return $feature;

    }

    protected function refuse_feature (string $message) : null {

        _doing_it_wrong(static::class . '::load_feature', esc_html($message), '');

        return null;

    }

    protected function find_feature ($file) : ?string {

        $paths = apply_filters('lattice.feature.paths', [DIGITALIS_LIBRARY_PATH]);
        $file  = ltrim($file, '/');

        foreach ($paths as $base) {

            $path = realpath($base . $file);

            // Directory shorthand: `foo/bar` → `foo/bar/bar.feature.php`.
            if (is_dir($path)) {
                $candidate = $path . '/' . basename($path) . '.feature.php';
                if (is_file($candidate)) $path = $candidate;
            }

            // File shorthand: `foo/bar` → `foo/bar.feature.php`.
            if (!is_file($path)) {
                $candidate = realpath($base . $file . '.feature.php');
                if ($candidate && is_file($candidate)) $path = $candidate;
            }

            if (!is_file($path)) continue;

            $feature = $this->load_class($path, false);
            if (class_exists($feature)) return $feature;

        }

        return null;

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

    // Layout System

    public static function render () {

        $apps = static::get_apps();
        if (!$apps) return;

        foreach ($apps as $app) {
            $app->render_app();
        }

        $resolver     = Request_Resolver::get_instance();
        $layout_class = $resolver->resolve_layout() ?? Layout::class;
        $page         = $resolver->resolve_page();
        $overrides    = $page ? $page::get_layout_overrides() : [];

        echo new $layout_class(['body' => $page] + $overrides);

    }

    public function render_app () {}

    public static function get_apps () {

        return static::get_group_instances();

    }

}