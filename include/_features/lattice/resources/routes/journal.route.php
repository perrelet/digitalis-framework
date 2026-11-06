<?php

declare(strict_types=1);

namespace Lattice;

use WP_REST_Request;

class Resources_Journal_Route extends Resource_Route {

    protected $route      = 'journal';
    protected $definition = ['methods' => 'GET'];

    public function callback (WP_REST_Request $request) {

        return Resources::inst()->journal([
            'resource'  => (string) $request->get_param('resource'),
            'record_id' => (string) $request->get_param('id'),
            'set'       => $request->get_param('set'),
            'proposer'  => $request->get_param('proposer'),
            'since'     => (int) $request->get_param('since'),
            'page'      => (int) $request->get_param('page'),
        ]);

    }

}
