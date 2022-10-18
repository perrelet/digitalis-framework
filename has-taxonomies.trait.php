<?php

namespace Digitalis;

trait Has_Taxonomies {

    protected $taxonomies = [];

    public function load_taxonomies ($path) {

        foreach (glob($path . '/*.taxonomy.php') as $taxonomy_path) {

            include $taxonomy_path;
            $class = end(get_declared_classes());
		    $this->taxonomies[$class] = new $class();

        }

    }

    public function get_taxonomy ($class_name) {

        return isset($this->taxonomies[$class_name]) ? $this->taxonomies[$class_name] : null;

    }

    public function get_taxonomies () {

        return $this->taxonomies;

    }

}