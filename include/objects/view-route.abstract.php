<?php

namespace Digitalis;

use WP_REST_Request;
use WP_Error;

abstract class View_Route extends Route {

    protected $view = View::class;

    protected $namespace_prefix = 'html/';

    protected static $instances = 0;

    /* protected function get_params () {
        
        return [
            'param' => [
                'default'           => 'Default',
                'required'          => true,
                'validate_callback' => [$this, 'validate_param'],
                'sanitize_callback' => [$this, 'sanitize_param'],
            ],
        ];
        
    } */

    public function __construct () {
        
        parent::__construct();

        if (++static::$instances == 1) add_filter('rest_pre_serve_request', [$this, 'rest_pre_serve_request'], 10, 4);
        
    }

    public function register_api_routes () {

        parent::register_api_routes();

        register_rest_route($this->namespace_prefix . $this->namespace, $this->route, $this->rest_args);
        
    }

    public function callback (WP_REST_Request $request) {

        if (!is_subclass_of($this->view, View::class)) return $this->respond(new WP_Error('view-error', "\$view must be a subclass of \Digitalis\View, '{$this->view}' provided."));

        $params = [];
        if ($this->rest_args['args']) foreach ($this->rest_args['args'] as $key => $arg) $params[$key] = $request->get_param($key);

        return $this->respond(call_user_func("{$this->view}::render", $params, false));

    }

    public function rest_pre_serve_request ($served, $result, $request, $wp_rest_server) {

        if (substr($request->get_route(), 0, strlen($this->namespace_prefix) + 1) == '/' . $this->namespace_prefix) {
            
            $embed = isset($_GET['_embed']) ? rest_parse_embed_param($_GET['_embed']) : false;
            $result = $wp_rest_server->response_to_data($result, $embed);
    
            print_r($result);

            return true;
    
        }
    
        return $served;
        
    }

    //

    protected function is_this_route (WP_REST_Request $request) {

        if (parent::is_this_route($request)) return true;

        return (ltrim($request->get_route(), "/") == "{$this->namespace_prefix}{$this->namespace}/{$this->route}");

    }

}