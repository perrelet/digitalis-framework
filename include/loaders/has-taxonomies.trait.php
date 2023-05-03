<?php

namespace Digitalis;

trait Has_Taxonomies {

    use Can_Load;

    public function load_taxonomies ($path) {

        $this->autoload('taxonomy', $path, 'taxonomy.php', 'get_instance');

    }

    public function get_taxonomy ($class_name) {

        return $this->get_object('taxonomy', $class_name);

    }

    public function get_taxonomies () {

        return $this->get_object_group('taxonomy');

    }

}