<?php

namespace Digitalis;

trait Has_Post_Types {

    use Can_Load;

    public function load_post_types ($path) {

        $this->autoload('post-type', $path, 'post-type.php');

    }

    public function get_post_type ($class_name) {

        return $this->get_object('post-type', $class_name);

    }

    public function get_post_types () {

        return $this->get_object_group('post-type');

    }

}