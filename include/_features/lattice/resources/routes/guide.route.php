<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Guide_Route extends Resource_Route {

    protected $route      = 'guide';
    protected $definition = ['methods' => 'GET'];

    public function callback (WP_REST_Request $request) {

        return Resources::inst()->guide();

    }

}
