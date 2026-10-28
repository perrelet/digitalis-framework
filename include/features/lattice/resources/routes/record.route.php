<?php

namespace Digitalis;

use WP_REST_Request;

class Resources_Record_Route extends Resource_Route {

    protected $route      = self::RESOURCE . '/(?P<id>[\w-]+)';
    protected $definition = ['methods' => ['GET', 'PATCH']];

    public function callback (WP_REST_Request $request) {

        return ($request->get_method() === 'PATCH')
            ? Resources::inst()->update($request['resource'], $request['id'], $this->get_input($request), 'rest')
            : Resources::inst()->get($request['resource'], $request['id']);

    }

}
