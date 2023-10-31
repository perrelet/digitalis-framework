<?php

namespace Digitalis;

use WP_REST_Request;
use WP_Error;

abstract class Route extends Singleton {

    protected $namespace        = 'digitalis/v1';
    protected $route            = 'route';
    protected $wp_query         = false;
    protected $namespace_prefix = 'html/';

    protected $rest_args = [];

    protected static $instances = 0;

    public function __construct () {

        add_action('rest_api_init', [$this, 'register_api_routes']);

        if ($this->wp_query) add_filter('rest_request_before_callbacks', [$this, 'set_wp_query_vars'], 10, 3);

        if (++static::$instances == 1) add_filter('rest_pre_serve_request', [$this, 'rest_pre_serve_request'], 10, 4);

    }

    public function set_wp_query_vars ($response, $handler, $request) {

        if ($this->is_this_route($request)) {

            global $wp, $wp_query;
            $wp_query->query_vars = $wp->query_vars;

        }

        return $response;
        
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
        if ($this->namespace_prefix) register_rest_route($this->namespace_prefix . $this->namespace, $this->route, $this->rest_args);
        
    }

    public function permission (WP_REST_Request $request) {
        
        return true;
        
    }

    public function callback (WP_REST_Request $request) {

        return $this->respond("Hello Route");

    }

    protected function respond ($response) {
        
        return rest_ensure_response($response);
        
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

    public static function get_url ($query_params = [], $html = true, $nonce = true) {

        $self = static::inst();

        $url = $html ? "{$self->namespace_prefix}{$self->namespace}/{$self->route}" : "{$self->namespace}/{$self->route}";
        $url = get_rest_url(null, $url);
        if ($query_params) $url = add_query_arg($query_params, $url);
        if ($nonce) $url = static::nonce_url($url);
    
        return $url;
    
    }

    public static function nonce_url ($url) {
    
        $url = add_query_arg('_wpnonce', static::get_nonce(), $url);
        $url = str_replace("%25post_id%25", "%post_id%", $url);

        return $url;
    
    }

    public static $nonce;

    public static function get_nonce () {
        
        if (!static::$nonce) static::$nonce = wp_create_nonce('wp_rest');

        return static::$nonce;
        
    }

    //

    protected function is_this_route (WP_REST_Request $request) {

        if (ltrim($request->get_route(), "/") == "{$this->namespace}/{$this->route}") return true;
        if (ltrim($request->get_route(), "/") == "{$this->namespace_prefix}{$this->namespace}/{$this->route}") return true;

        return false;

    }

}