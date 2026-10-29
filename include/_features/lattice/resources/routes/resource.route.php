<?php

namespace Lattice;

use WP_REST_Request;

abstract class Resource_Route extends Route {

    const RESOURCE = '(?P<resource>(?!(?:guide|journal|undo)(?:/|$))[a-z0-9_]+)';

    protected $namespace = 'lattice/v1';

    public function permission (WP_REST_Request $request) {

        return is_user_logged_in();

    }

    protected function get_input (WP_REST_Request $request) : array {

        return (array) ($request->get_json_params() ?: $request->get_body_params());

    }

}
