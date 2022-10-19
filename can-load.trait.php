<?php

namespace Digitalis;

trait Can_Load {

    protected $instantiation_queue = [];
    protected $object_groups = [];

    public function autoload ($group_key, $dir_path, $ext = 'php') {

        foreach (glob($dir_path . '/*.' . $ext) as $path) {

            $this->load_class($group_key, $path);

        }

    }

    public function load_class ($group_key, $file_path) {

        if (!is_file($file_path)) return false;

        include $file_path;
        $this->instantiation_queue[] = $group_key;

        return true;

    }

    public function instantiate () {

        if (!$this->instantiation_queue) return false;

        $classes = get_declared_classes();

        foreach ($this->instantiation_queue as $i => $group_key) {

            if (!isset($this->object_groups[$group_key])) $this->object_groups[$group_key] = [];

            $class = $classes[count($classes) - $i - 1];
            $this->object_groups[$group_key][$class] = new $class();

        }

        $this->instantiation_queue = [];

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

}