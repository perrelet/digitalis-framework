<?php

namespace Digitalis;

use Closure;
use ReflectionClass;

trait Autoloader {

    public function autoload ($path, $instantiate = 'get_instance', $recursive = true, $ext = 'php', &$objs = []) {

        if (is_array($path)) return $this->autoload_multiple($path, $objs);

        if ($instantiate instanceof Closure) $instantiate = $instantiate();

        if (is_array($instantiate)) {

            if (isset($instantiate[3])) $objs        = $instantiate[3];
            if (isset($instantiate[2])) $ext         = $instantiate[2];
            if (isset($instantiate[1])) $recursive   = $instantiate[1];
            if (isset($instantiate[0])) $instantiate = $instantiate[0];

        }

        if (!is_dir($path = realpath($path))) return $objs;

        $names = $this->get_file_names($path, $ext);

        if ($names) foreach ($names as $name) {
        
            if ($obj = $this->load_class($path . '/' . $name, $instantiate)) $objs[] = $obj;
        
        }

        if ($recursive) foreach (glob($path . '/*', GLOB_ONLYDIR) as $dir) {

            $this->autoload($dir, $instantiate, $recursive, $ext, $objs);

        }

        return $objs;
    
    }

    public function autoload_multiple ($autoloads, &$objs = []) {
    
        if ($autoloads) foreach ($autoloads as $directory => $instantiation) {

            if (is_null($instantiation)) $instantiation = 'get_instance';

            $objs = array_merge($objs, $this->autoload($this->path . $directory, $instantiation));

        }

        return $objs;

    }

    public function load_class ($path, $instantiate = true) {
    
        if (!is_file($path)) return false;

        include_once $path;

        if (!$class_name = $this->extract_class_name($path)) return false;
        if (!class_exists($class_name))                      return false;

        if (method_exists($class_name, 'hello')) call_user_func([$class_name, 'hello']);

        $reflection = new ReflectionClass($class_name);

        if ($reflection->isAbstract())                       $instantiate = false;
        if (strpos(basename($path), '.abstract.') !== false) $instantiate = false;

        $instantiate = apply_filters('Digitalis/Instantiate/' . str_replace('\\', '/', ltrim($class_name, '\\')), $instantiate, $path);

        if ($instantiate === false) {

            return false;

        } elseif ($instantiate === true) {

            return new $class_name();

        } else {

            $call = $class_name . "::" . $instantiate;
            return is_callable($call) ? call_user_func($call) : false;

        }
    
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

            if (count($parts) < 3) continue;

            if (count($parts) > 3) $parts = [
                implode('.', array_slice($parts, 0, count($parts) - 2)),
                $parts[count($parts) - 2],
                $parts[count($parts) - 1],
            ];

            $inherits[$parts[0]] = $parts[1];
        
        }
    
        return $inherits;
    
    }

    protected function sort_inherits ($inherits, $sorted = []) {

        if ($inherits) foreach ($inherits as $child => $parent) {
        
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
        
            $file_names[] = "{$child}.{$parent}.{$ext}";
        
        }

        return $file_names;
    
    }

    //

    protected function extract_class_name ($file, $buffer_bytes = 512) {

        // https://stackoverflow.com/questions/7153000/get-class-name-from-file
        // fix: $class = $tokens[$i+2][1];         ---> if (is_array($tokens[$i+2])) $class = $tokens[$i+2][1];                      Avoids 'Uninitialized string offset 1' when using 'static::class'
        // fix: if ($tokens[$j][0] === T_STRING) { ---> if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {    Handle files with sub-namespaces (T_NAME_QUALIFIED PHP 8.0.0+)
        // fix: add: if ($tokens[$j] === ';') break;                                                                                 Class declarations can't contain a semicolon. Bailing early speeds things up and prevents confusing lines containing '::class' with the class declaration. 

        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (!$class) {
            if (feof($fp)) break;

            $buffer .= fread($fp, $buffer_bytes);
            $tokens = token_get_all($buffer);

            // echo str_replace("php", "php-->", $buffer) . "\n";

            if (strpos($buffer, '{') === false) continue;

            for (;$i<count($tokens);$i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j=$i+1;$j<count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {
                            $namespace .= '\\'.$tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j=$i+1;$j<count($tokens);$j++) {
                        if ($tokens[$j] === ';') break;
                        if ($tokens[$j] === '{') {
                            if (is_array($tokens[$i+2])) $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return $namespace ? $namespace . "\\" . $class : $class;

    }

}