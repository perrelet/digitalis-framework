<?php

namespace Digitalis;

use ReflectionClass;

trait Autoloader {

    public function autoload ($path, $instantiate = 'get_instance', $recursive = true, $ext = 'php', $objs = []) {

        if (!is_dir($path = realpath($path))) return $objs;

        $files    = glob($path . '/*.' . $ext);
        $names    = $this->get_names($files);
        $inherits = $this->get_inherits($names);
        $inherits = $this->sort_inherits($inherits);
        $names    = $this->rebuild_names($inherits, $ext);

        if ($names) foreach ($names as $name) {
        
            if ($obj = $this->load_class($path . '/' . $name, $instantiate)) $objs[] = $obj;
        
        }

        if ($recursive) foreach (glob($path . '/*', GLOB_ONLYDIR) as $dir) {

            $this->autoload($dir, $instantiate, $recursive, $ext, $objs);

        }

        return $objs;
    
    }

    public function load_class ($path, $instantiate = true) {
    
        if (!is_file($path)) return false;

        include $path;

        if (!$class_name = $this->extract_class_name($path)) return false;
        if (!class_exists($class_name))                      return false;

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
        
            $file_names[] = "{$child}.{$parent}.{$ext}";
        
        }

        return $file_names;
    
    }

    //

    protected function extract_class_name ($file) {

        // https://stackoverflow.com/questions/7153000/get-class-name-from-file
        // fix: $class = $tokens[$i+2][1]; -> if (is_array($tokens[$i+2])) $class = $tokens[$i+2][1]; to avoid 'Uninitialized string offset 1' when using 'static::class'

        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;
        while (!$class) {
            if (feof($fp)) break;

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) continue;

            for (;$i<count($tokens);$i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j=$i+1;$j<count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\'.$tokens[$j][1];
                        } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j=$i+1;$j<count($tokens);$j++) {
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