<?php

namespace Digitalis;

trait Can_Load {

    protected $object_groups = [];

    public function autoload ($group_key, $dir_path, $ext = 'php', $instantiate = true) {

        foreach (glob($dir_path . '/*.' . $ext) as $path) {

            $this->load_class($group_key, $path, $instantiate);

        }

    }

    public function load_class ($group_key, $file_path, $instantiate = true) {

        if (!is_file($file_path)) return false;

        include $file_path;

        if ($instantiate !== false) {
         
            if (!$class_name = $this->get_class_name($file_path)) return false;

            if (!isset($this->object_groups[$group_key])) $this->object_groups[$group_key] = [];
            $this->object_groups[$group_key][$class_name] = new $class_name();

        }

        return true;

    }

    public function get_object_groups () {

        return $this->object_groups;

    }

    public function get_object_group ($group_key) {

        return isset($this->object_groups[$group_key]) ? $this->object_groups[$group_key] : null;

    }

    public function get_object ($group_key, $class_name) {

        if (!isset($this->object_groups[$group_key]) || !isset($this->object_groups[$group_key][$class_name])) return null;

        return $this->object_groups[$group_key][$class_name];

    }

    //

    protected function get_class_name ($file) {

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
                            $class = $tokens[$i+2][1];
                        }
                    }
                }
            }
        }

        return $namespace ? $namespace . "\\" . $class : $class;

    }

}