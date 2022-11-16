<?php

namespace Digitalis;

abstract class Theme {

    protected $actions = [
        'wp_enqueue_scripts' => 'style',
    ];

    protected $url;
    protected $path;

    public function __construct () {

        foreach ($this->actions as $action => $method) if (is_callable([$this, $method])) add_action($action, [$this, $method]);

    }

    //

    public function set_location ($url, $path) {

        $this->url = $url;
        $this->path = $path;

    }

    //

    public function get_url () {

        return $this->url;

    }

    public function get_path () {

        return $this->path;

    }

}

