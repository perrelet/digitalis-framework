<?php

namespace Lattice;

use WP_REST_Request;

class Resources_Undo_Route extends Resource_Route {

    protected $route      = 'journal/(?P<row>\d+)/undo';
    protected $definition = ['methods' => 'POST'];

    public function callback (WP_REST_Request $request) {

        return Resources::inst()->undo((int) $request['row'], $this->get_input($request), 'rest');

    }

}
