<?php

namespace Digitalis;

use WP_REST_Request;
use WP_Error;

/**
 * They found themselves standing on the very edge of the Wild Wood. 
 * Rocks and brambles and tree-roots behind them, confusedly heaped and tangled; in front, a great space of quiet fields, hemmed by lines of hedges black on the snow, and, far ahead, a glint of the familiar old river, while the wintry sun hung red and low on the horizon.
 * @api
 * @author Digitalis Web Build Co. <jamie@digitalis.ca>
 * @copyright 2023 Digitalis Web Build Co.
 */

abstract class Route extends Singleton {

    use Dependency_Injection;

    /**
     * @link /wp-json/{$namespace}/{$route}       JSON Response.
     * @link /wp-json/html/{$namespace}/{$route}  HTML Response.
     * @var string       $namespace               The namespace and version.
     * @var string       $route                   The endpoint for this route.
     * @var bool         $wp_query                Whether to set `$wp_query->query_vars = $wp->query_vars;` and emulate a normal WordPress query.
     * @var bool|string  $html_prefix             Prefixed route for accessing endpoint without JSON processing. See https://htmx.org/essays/how-did-rest-come-to-mean-the-opposite-of-rest/. Set `false` this feature off.
     * @var bool|string  $view                    The view class to render at the endpoint (view args are inherited from `WP_REST_Request`). Set `false` to turn off and use the `callback` method.
     * @var array        $rest_args               Args passed when calling `register_rest_route`.
     */

    protected $namespace     = 'digitalis/v1';
    protected $route         = 'route';
    protected $wp_query      = false;
    protected $html_prefix   = 'html/';
    protected $view          = false; /* View::class */
    protected $require_nonce = false;
    protected $rest_args     = [];

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
            'callback'              => [$this, 'callback_wrap'],
            'permission_callback'   => [$this, 'permission_wrap'],
        ]);
        
    }

    public function register_api_routes () {

        $this->rest_args = $this->get_rest_args();

        register_rest_route($this->namespace, $this->route, $this->rest_args);
        if ($this->html_prefix) register_rest_route($this->html_prefix . $this->namespace, $this->route, $this->rest_args);

    }

    static $permission = null;

    public function permission_wrap (WP_REST_Request $request) {
    
        //if (is_null(static::$permission)) static::$permission = $this->permission($request);
        if (is_null(static::$permission)) static::$permission = $this->request_inject($request, 'permission');

        return static::$permission;
    
    }

    public function permission (WP_REST_Request $request) {
        
        return true;
        
    }

    protected function check_nonce (WP_REST_Request $request) {
    
        $nonce = $request->get_param('_wpnonce');
        if (!$nonce) $nonce = $request->get_header('Nonce');

        if (is_null($nonce)) return new WP_Error(
            __NAMESPACE__ . '_rest_missing_nonce',
            'Missing the `Nonce` header or `_wpnonce` parameter. This endpoint requires a valid nonce.',
            401,
        );

        if (!wp_verify_nonce($nonce, 'wp_rest')) return new WP_Error(
            __NAMESPACE__ . '_rest_invalid_nonce',
            'Nonce is invalid.',
            403,
        );

        return true;
    
    }

    public function get_view () {
    
        return $this->view;
    
    }

    public function callback_wrap (WP_REST_Request $request) {

        if ($this->require_nonce) {

            $nonce_check = $this->check_nonce($request);
            if ($nonce_check instanceof WP_Error) return $nonce_check;

        }

        if ($view = $this->get_view()) {

            if (!is_subclass_of($view, View::class)) return $this->respond(new WP_Error('view-error', "\$view must be a subclass of \Digitalis\View, '{$view}' provided."));

            $params = [
                'view' => $view,
            ];
            
            if ($this->rest_args['args']) foreach ($this->rest_args['args'] as $key => $arg) $params[$key] = $request->get_param($key);
    
            return $this->respond($this->render_view($params));

        } else {

            return $this->request_inject($request, 'callback');

            /* $request_params = $request->get_params();
            $values         = [];

            if ($params = $this->get_params()) foreach ($params as $key => $param) {
            
                if ((!$class = ($param['class'] ?? false))) continue;
                if (!isset($request_params[$key]))          continue;

                if ($value = static::value_inject($class, $request_params[$key])) {

                    $values[$class] = $value;

                } else {

                    return new \WP_Error(
                        "missing_resource",
                        "Unable to locate a '{$class}' with '$key' = " . print_r($request->get_params()[$key], true) . ".",
                        [
                            'status' => 404,
                        ]
                    );

                }
            
            }

            return static::inject([$this, 'callback'], [$request], $values); */

        }

    }

    public function render_view ($params) {
    
        return call_user_func("{$params['view']}::render", $params, false);
    
    }

    public function callback (WP_REST_Request $request) {

        return $this->respond("Hello Route");

    }

    protected function respond ($response) {
        
        return rest_ensure_response($response);
        
    }

    public function rest_pre_serve_request ($served, $result, $request, $wp_rest_server) {

        if (substr($request->get_route(), 0, strlen($this->html_prefix) + 1) == '/' . $this->html_prefix) {
            
            $embed = isset($_GET['_embed']) ? rest_parse_embed_param($_GET['_embed']) : false;
            $result = $wp_rest_server->response_to_data($result, $embed);
    
            print_r($result);

            return true;
    
        }
    
        return $served;
        
    }

    //

    protected function request_inject (WP_REST_Request $request, $method) {
    
        $request_params = $request->get_params();
        $values         = [];

        if ($params = $this->get_params()) foreach ($params as $key => $param) {
        
            if ((!$class = ($param['class'] ?? false))) continue;
            if (!isset($request_params[$key]))          continue;

            if ($value = static::value_inject($class, $request_params[$key])) {

                $values[$class] = $value;

            } else {

                return new \WP_Error(
                    "missing_resource",
                    "Unable to locate a '{$class}' with '$key' = " . print_r($request->get_params()[$key], true) . ".",
                    [
                        'status' => 404,
                    ]
                );

            }
        
        }

        return static::inject([$this, $method], [$request], $values);
    
    }

    public static function get_url ($query_params = [], $html = true, $nonce = true) {

        $self = static::inst();

        $url = $html ? "{$self->html_prefix}{$self->namespace}/{$self->route}" : "{$self->namespace}/{$self->route}";
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
        if (ltrim($request->get_route(), "/") == "{$this->html_prefix}{$this->namespace}/{$this->route}") return true;

        return false;

    }

}