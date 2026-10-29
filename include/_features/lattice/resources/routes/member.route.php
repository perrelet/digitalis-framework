<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Member_Route extends Resource_Route {

    protected $route      = self::RESOURCE . '/(?P<id>[\w-]+)/(?P<name>[a-z0-9_]+)';
    protected $definition = ['methods' => ['GET', 'POST']];

    public function callback (WP_REST_Request $request) {

        return ($request->get_method() === 'POST')
            ? Resources::inst()->act($request['resource'], $request['id'], $request['name'], $this->get_input($request), 'rest')
            : Resources::inst()->view($request['resource'], $request['id'], $request['name']);

    }

}
