<?php

namespace Digitalis;

trait Has_Post_Types {

    protected $post_types = [];

    public function load_post_types ($path) {

        foreach (glob($path . '/*.post-type.php') as $post_type_path) {

            include $post_type_path;
            $class = end(get_declared_classes());
		    $this->post_types[$class] = new $class();

        }

    }

    public function get_post_type ($class_name) {

        return isset($this->post_types[$class_name]) ? $this->post_types[$class_name] : null;

    }

    public function get_post_types () {

        return $this->post_types;

    }

}