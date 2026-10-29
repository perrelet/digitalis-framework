<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Collection_Route extends Resource_Route {

    protected $route      = self::RESOURCE;
    protected $definition = ['methods' => ['GET', 'POST']];

    public function callback (WP_REST_Request $request) {

        return ($request->get_method() === 'POST')
            ? Resources::inst()->create_record($request['resource'], $this->get_input($request), 'rest')
            : Resources::inst()->list($request['resource'], $request->get_query_params());

    }

}
