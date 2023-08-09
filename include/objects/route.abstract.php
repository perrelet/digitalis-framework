<?php

namespace Digitalis;

use WP_REST_Request;
use WP_Error;

abstract class Route {

    protected $namespace = 'digitalis/v1';
    protected $route     = 'route';

    protected $rest_args = [];

    public function __construct () {

        add_action('rest_api_init', [$this, 'register_api_routes']);

    }

    protected function get_params () {
        
        return [
            /* 'param' => [
                'default'           => 'Default',
                'required'          => true,
                'validate_callback' => [$this, 'validate_param'],
                'sanitize_callback' => [$this, 'sanitize_param'],
            ], */
        ];
        
    }

    protected function get_rest_args () {
        
        return wp_parse_args($this->rest_args, [
            'args'                  => $this->get_params(),
            'methods'               => ['GET', 'POST'],
            'callback'              => [$this, 'callback'],
            'permission_callback'   => [$this, 'permission'],
        ]);
        
    }

    public function register_api_routes () {
        
        $this->rest_args = $this->get_rest_args();

        register_rest_route($this->namespace, $this->route, $this->rest_args);
        
    }

    public function permission () {
        
        return true;
        
    }

    public function callback (WP_REST_Request $request) {

        return $this->respond("Hello Route");

    }

    protected function respond ($response) {
        
        return rest_ensure_response($response);
        
    }

}