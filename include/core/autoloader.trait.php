<?php

namespace Lattice;

use Closure;
use ReflectionClass;

trait Autoloader {

    protected $path;

    public function autoload ($path = null, $recursive = true, $ext = 'php', &$objs = [], $instantiation = null) {

        if (is_null($path)) $path = $this->path;

        if (is_array($path))                  return $this->autoload_multiple($path, $objs);
        if (!is_dir($path = realpath($path))) return $objs;

        foreach ($this->sort_files($this->collect_files($path, $recursive, $ext), $ext) as $file) {

            $obj = $this->load_class($file, $instantiation);
            if (is_object($obj)) $objs[] = $obj;

        }

        return $objs;

    }

    public function autoload_order ($path, $recursive = true, $ext = 'php') {

        return $this->sort_files($this->collect_files(realpath($path), $recursive, $ext), $ext);

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

    // Sorts across the whole tree, so a child may live in a different directory to its parent.
    // Keyed by path, not class name: those collide across directories (db/table vs components/table).
    protected function sort_files ($files, $ext = 'php') {

        $pending = [];
        $by_name = [];

        foreach ($files as $path) {

            [$name, $parent] = $this->split_file_name(basename($path), $ext);

            $pending[$path]   = $parent;
            $by_name[$name][] = $path;

        }

        $sorted = [];

        while ($pending) {

            $progress = false;

            foreach ($pending as $path => $parent) {

                foreach ($by_name[$parent] ?? [] as $blocker) {
                    if (($blocker !== $path) && isset($pending[$blocker])) continue 2;
                }

                $sorted[] = $path;
                unset($pending[$path]);
                $progress = true;

            }

            if ($progress) continue;

            foreach ($pending as $path => $parent) $sorted[] = $path;
            break;

        }

        return $sorted;

    }

    protected function split_file_name ($file_name, $ext = 'php') {

        if ($pos = strpos($ext, '.')) $ext = substr($ext, $pos + 1);

        $parts = explode('.', substr($file_name, 0, -(strlen($ext) + 1)));

        return [$parts[0], count($parts) > 1 ? end($parts) : ''];

    }

    public function autoload_multiple ($autoloads, &$objs = []) {
    
        if ($autoloads) foreach ($autoloads as $directory => $instantiation) {

            $objs = array_merge($objs, $this->autoload($this->path . $directory, true, 'php', $objs, $instantiation));

        }

        return $objs;

    }

    public function load_class ($path, $instantiation = null) {
    
        if (!is_file($path)) return false;

        include_once $path;

        if (!$class_name = $this->extract_class_name($path)) return false;
        if (!class_exists($class_name))                      return false;

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

    protected function get_file_names ($path, $ext = 'php') {
    
        $files    = glob($path . '/*.' . $ext);
        $names    = $this->get_names($files);
        $inherits = $this->get_inherits($names);
        $inherits = $this->sort_inherits($inherits);
        $names    = $this->rebuild_names($inherits, $ext);

        return $names;
    
    }

    protected function get_names ($files) {
    
        $paths = [];

        if ($files) foreach ($files as $file) $paths[] = basename($file);

        return $paths;
    
    }

    protected function get_inherits ($file_names) {

        $inherits = [];

        if ($file_names) foreach ($file_names as $file_name) {
        
            $parts = explode('.', $file_name);

            if (count($parts) == 1) continue;

            if (count($parts) == 2) {

                $parts = [
                    $parts[0],
                    '',
                    $parts[1],
                ];

            } elseif (count($parts) > 3) {

                $parts = [
                    implode('.', array_slice($parts, 0, count($parts) - 2)),
                    $parts[count($parts) - 2],
                    $parts[count($parts) - 1],
                ];

            }

            $inherits[$parts[0]] = $parts[1];
        
        }
    
        return $inherits;
    
    }

    protected function sort_inherits ($inherits, $sorted = []) {

        if ($priority = array_intersect($inherits, ['', 'trait', 'interface'])) foreach ($priority as $child => $parent) {

            $sorted[$child] = $parent;
            unset($inherits[$child]);

        }

        foreach ($inherits as $child => $parent) {
        
            if (!array_key_exists($parent, $inherits) || ($child == $parent)) {

                $sorted[$child] = $parent;
                unset($inherits[$child]);

            }
        
        }

        return $inherits ? $this->sort_inherits($inherits, $sorted) : $sorted;
    
    }

    protected function rebuild_names ($inherits, $ext) {

        $file_names = [];
        if ($inherits) foreach ($inherits as $child => $parent) {

            if ($pos = strpos($ext, '.')) $ext = substr($ext, $pos + 1);

            $file_names[] = $parent ? "{$child}.{$parent}.{$ext}" : "{$child}.{$ext}";
            
        }

        return $file_names;
    
    }

    //

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