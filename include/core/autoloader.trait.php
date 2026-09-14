<?php

namespace Lattice;

use Closure;
use ReflectionClass;

trait Autoloader {

    protected $path;
    protected $autoload_map          = [];
    protected $autoload_declarations = [];
    protected $autoloader_registered = false;
    protected $classmap              = null;

    public function autoload ($path = null, $recursive = true, $ext = 'php', &$objs = [], $instantiation = null) {

        if (is_null($path)) $path = $this->path;

        if (is_array($path))                  return $this->autoload_multiple($path, $objs);
        if (!is_dir($path = realpath($path))) return $objs;

        $files = $this->collect_files($path, $recursive, $ext);

        $this->map_files($files, $this->classmap?->key($path, $recursive, $ext));
        $this->register_autoloader();

        foreach ($files as $file) {

            $obj = $this->load_class($file, $instantiation);
            if (is_object($obj)) $objs[] = $obj;

        }

        return $objs;

    }

    public function use_classmap ($file) {

        $this->classmap = new Classmap($file);

        return $this;

    }

    protected function map_files ($files, $key = null) {

        if ($key && ($cached = $this->classmap->read($key, $files))) {

            foreach ($cached as $file => $names) $this->remember_declarations($file, $names);
            return;

        }

        foreach ($files as $file) {

            if (isset($this->autoload_declarations[$file])) continue;

            $this->remember_declarations($file, $this->extract_declarations($file));

        }

        if ($key) $this->classmap->record($key, array_intersect_key($this->autoload_declarations, array_flip($files)));

    }

    protected function remember_declarations ($file, $names) {

        $this->autoload_declarations[$file] = $names;

        foreach ($names as $name) $this->autoload_map[$name] = $file;

    }

    // Prepended: answers before the compat shim probes sub-namespaces, and before every other autoloader on the site. One lookup, nothing else.
    protected function register_autoloader () {

        if ($this->autoloader_registered) return;

        $this->autoloader_registered = true;

        spl_autoload_register(function ($name) {

            if (isset($this->autoload_map[$name])) include_once $this->autoload_map[$name];

        }, true, true);

    }

    protected function collect_files ($path, $recursive = true, $ext = 'php', $depth = 0) {

        $files = [];

        if ($depth && basename($path)[0] == '_') return $files;

        foreach (glob($path . '/*.' . $ext) as $file) $files[] = $file;

        if ($recursive) foreach (glob($path . '/*', GLOB_ONLYDIR) as $dir) {

            if ((basename($dir)[0] == '~') && !$this->plugin_is_active(substr(basename($dir), 1))) continue;

            $files = array_merge($files, $this->collect_files($dir, $recursive, $ext, $depth + 1));

        }

        return $files;

    }

    protected function plugin_is_active ($plugin_dir) {

        foreach (get_plugins() as $plugin_name => $plugin) {

            if ((dirname($plugin_name) == $plugin_dir) && is_plugin_active($plugin_name)) return true;

        }

        return false;

    }

    public function autoload_multiple ($autoloads, &$objs = []) {
    
        if ($autoloads) foreach ($autoloads as $directory => $instantiation) {

            $objs = array_merge($objs, $this->autoload($this->path . $directory, true, 'php', $objs, $instantiation));

        }

        return $objs;

    }

    public function load_class ($path, $instantiation = null) {

        if (!is_file($path)) return false;

        $this->map_files([$path]);

        include_once $path;

        if (!$class_name = $this->autoload_declarations[$path][0] ?? '') return false;
        if (!class_exists($class_name))                                  return false;

        if (method_exists($class_name, 'hello'))       call_user_func([$class_name, 'hello']);
        if (method_exists($class_name, 'static_init')) call_user_func([$class_name, 'static_init']);

        if (is_null($instantiation)) $instantiation = $this->resolve_auto_instantiation($class_name, $path);

        return $this->instantiate_class($class_name, $instantiation);

    }

    protected function resolve_auto_instantiation ($class_name, $path = '') {

        $instantiation = false;
        if (method_exists($class_name, 'get_auto_instantiation')) $instantiation = $class_name::get_auto_instantiation();

        $reflection = new ReflectionClass($class_name);
        if ($reflection->isAbstract())                                $instantiation = false;
        if ($path && strpos(basename($path), '.abstract.') !== false) $instantiation = false;

        $instantiation = Hook::filter('lattice.instantiate', $instantiation, $class_name, $path);
        $instantiation = Hook::filter(['lattice', 'instantiate', $class_name], $instantiation, $path);

        return $instantiation;

    }

    protected function instantiate_class ($class_name, $instantiation) {

        if ($instantiation === false) {

            return $class_name;

        } elseif ($instantiation === true) {

            return new $class_name();

        } elseif (!is_scalar($instantiation)) {

            $call = [$class_name, 'get_instance'];
            return is_callable($call) ? call_user_func($call, $instantiation) : false;

        } else {

            $call = [$class_name, $instantiation];
            return is_callable($call) ? call_user_func($call) : false;

        }

        return false;

    }

    protected function extract_class_name ($file) {

        return $this->extract_declarations($file)[0] ?? '';

    }

    // Reads the whole file: a second declaration can sit anywhere, e.g. Debug_Options at line 406 of debug.view.php.
    protected function extract_declarations ($file) {

        $tokens    = token_get_all(file_get_contents($file));
        $count     = count($tokens);
        $namespace = '';
        $names     = [];
        $keywords  = [T_CLASS, T_TRAIT, T_INTERFACE];

        if (defined('T_ENUM')) $keywords[] = T_ENUM;

        for ($i = 0; $i < $count; $i++) {

            if (!is_array($tokens[$i])) continue;

            if ($tokens[$i][0] === T_NAMESPACE) {

                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {

                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED])) $namespace = $tokens[$j][1];
                    if ($tokens[$j] === ';' || $tokens[$j] === '{') break;

                }

                continue;

            }

            if (!in_array($tokens[$i][0], $keywords))                 continue;
            if ($this->is_anonymous_or_constant_class($tokens, $i))    continue;
            if (!$name = $this->declared_name($tokens, $i))            continue;

            $names[] = $namespace ? "{$namespace}\\{$name}" : $name;

        }

        return $names;

    }

    protected function is_anonymous_or_constant_class ($tokens, $i) {

        $previous = $this->adjacent_token($tokens, $i, -1);

        return $previous && in_array($previous[0], [T_NEW, T_DOUBLE_COLON]);

    }

    protected function declared_name ($tokens, $i) {

        $next = $this->adjacent_token($tokens, $i, 1);

        return ($next && $next[0] === T_STRING) ? $next[1] : null;

    }

    protected function adjacent_token ($tokens, $i, $direction) {

        $count = count($tokens);

        for ($j = $i + $direction; $j >= 0 && $j < $count; $j += $direction) {

            if (!is_array($tokens[$j])) return null;
            if (!in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) return $tokens[$j];

        }

        return null;

    }

}
