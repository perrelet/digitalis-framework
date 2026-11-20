<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Record_Route extends Resource_Route {

    protected $route      = self::RESOURCE . '/(?P<id>[\w-]+)';
    protected $definition = ['methods' => ['GET', 'PATCH', 'DELETE']];

    public function callback (WP_REST_Request $request) {

        return match ($request->get_method()) {
            'PATCH'  => Resources::get_instance()->update($request['resource'], $request['id'], $this->get_input($request), 'rest'),
            'DELETE' => Resources::get_instance()->trash($request['resource'], $request['id'], $this->get_input($request), 'rest'),
            default  => Resources::get_instance()->get($request['resource'], $request['id']),
        };

    }

}
