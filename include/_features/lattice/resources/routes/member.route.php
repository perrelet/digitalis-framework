<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Member_Route extends Resource_Route {

    protected $route      = self::RESOURCE . '/(?P<id>[\w-]+)/(?P<name>[a-z0-9_]+)';
    protected $definition = ['methods' => ['GET', 'POST']];

    public function callback (WP_REST_Request $request) {

        if (($request->get_method() === 'POST') && ($request['name'] === 'untrash')) return Resources::get_instance()->untrash($request['resource'], $request['id'], $this->get_input($request), 'rest');

        return ($request->get_method() === 'POST')
            ? Resources::get_instance()->act($request['resource'], $request['id'], $request['name'], $this->get_input($request), 'rest')
            : Resources::get_instance()->view($request['resource'], $request['id'], $request['name']);

    }

}
