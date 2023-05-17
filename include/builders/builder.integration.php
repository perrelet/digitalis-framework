<?php

namespace Digitalis;

class Builder extends Integration {

    protected $slug = 'builder';

    public function condition () {
        
        return true;
    
    }

    public function is_backend () {

        return false;

    }

    public function is_backend_content () {

        return $this->is_backend();

    }

    public function is_backend_ui () {

        return $this->is_backend();

    }

    public function add_classes ($classes, $args = []) {

        // ..

    }

    public function remove_classes ($classes, $args = []) {

        // ..

    }

    public function add_colors ($colors, $args = []) {

        // ..

    }

    public function remove_colors ($colors, $args = []) {

        // ..

    }

    //

    public function get_slug () {

        return $this->slug;

    }

}