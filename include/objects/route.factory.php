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

class Route extends Factory {

    use Dependency_Injection;

    protected static $cache_group    = self::class;
    protected static $cache_property = 'route';

    /**
     * @link /{$format}/{$namespace}/{$route}       JSON Response.
     * @var string       $namespace               The namespace and version.
     * @var string       $route                   The endpoint for this route.
     * @var bool         $wp_query                Whether to emulate a normal WordPress query. Note: $wp isn't reset so repeat `rest_do_request` calls may result in unexpected behaviour. 
     * @var bool|string  $view                    The view class to render at the endpoint (view args are inherited from `WP_REST_Request`). Set `false` to turn off and use the `callback` method.
     * @var bool         $require_nonce           Enforce nonce check.
     * @var array        $definition              Args passed to `register_rest_route`.
     * @var array        $args                    $args['args'] passed to `register_rest_route`.
     */

    protected $namespace     = 'digitalis/v1';
    protected $route         = 'route';
    protected $format        = 'json';
    protected $wp_query      = false;
    protected $view          = false; /* View::class */
    protected $require_nonce = false;
    protected $definition    = [];
    protected $args          = [];

    public function __construct () {

        add_action('rest_api_init', [$this, 'register_route']);

        if ($this->wp_query) add_filter('rest_request_before_callbacks', [$this, 'maybe_set_wp_query_vars'], 10, 3);

    }

    public function register_route () {

        register_rest_route($this->get_namespace(), $this->get_route(), $this->get_definition());

    }

    public function maybe_set_wp_query_vars ($response, $handler, $request) {

        if (!$this->is_this_route($request)) return $response;

        global $wp;

        $wp->query_posts();
        $wp->handle_404();
        $wp->register_globals();

        return $response;
        
    }

    public function get_namespace () {

        return $this->namespace;

    }

    public function get_route () {

        return $this->route;

    }

    public function get_view () {

        return $this->view;

    }

    public function get_require_nonce () {

        return $this->require_nonce;

    }

    protected $definition_cache;

    public function get_definition () {

        if (is_null($this->definition_cache)) $this->definition_cache = wp_parse_args($this->definition, [
            'args'                  => $this->get_args(),
            'methods'               => ['GET', 'POST'],
            'callback'              => [$this, 'callback_wrap'],
            'permission_callback'   => [$this, 'permission_wrap'],
        ]);

        return $this->definition_cache;        

    }

    public function get_args () {

        return $this->args;

        /* [
            'arg' => [
                'default'           => 1,
                'required'          => true,
                'type'              => 'integer',
                'validate_callback' => [$this, 'validate_arg'],
                'sanitize_callback' => [$this, 'sanitize_arg'],
            ],
            ...
        ]; */

    }

    public function permission (WP_REST_Request $request) {

        return true;

    }

    public function render_view ($view, $params) {

        return call_user_func("{$view}::render", $params, false);

    }

    public function callback (WP_REST_Request $request) {

        return $this->respond('Hello ' . static::class);

    }

    public function get_format () {

        if ($this->format) return $this->format;
        return $this->get_view() ? 'html' : 'json';

    }

    public function get_url ($query_params = [], $nonce = null, $format = null) {

        if (is_null($nonce))  $nonce  = $this->get_require_nonce();
        if (is_null($format)) $format = $this->get_format();

        return REST_URL_Builder::get_instance()->for_route($this, $query_params, $nonce, $format);

    }

    public function add_query_params ($url, $query_params = []) {

        return add_query_arg($query_params, $url);

    }

    public function nonce_url ($url) {
    
        $url = add_query_arg('_wpnonce', $this->get_nonce(), $url);
        $url = str_replace("%25post_id%25", "%post_id%", $url);

        return $url;
    
    }

    protected $nonce_cache;

    public function get_nonce () {
        
        if (is_null($this->nonce_cache)) $this->nonce_cache = wp_create_nonce('wp_rest');

        return $this->nonce_cache;

    }

    public function check_nonce (WP_REST_Request $request) {

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

    public function is_this_route (WP_REST_Request $request) {

        $route = '/' . trim($this->get_namespace(), '/') . '/' . ltrim($this->get_route(), '/');
        if ($request->get_route() !== $route) return false;

        return true;

    }

    //

    protected $permission_cache;

    public function permission_wrap (WP_REST_Request $request) {
    
        if (is_null($this->permission_cache)) $this->permission_cache = $this->request_inject($request, 'permission');

        return $this->permission_cache;
    
    }

    public function callback_wrap (WP_REST_Request $request) {

        if ($this->get_require_nonce()) {

            $nonce_check = $this->check_nonce($request);
            if ($nonce_check instanceof WP_Error) return $nonce_check;

        }

        if ($view = $this->get_view()) {

            if (!is_subclass_of($view, View::class)) return $this->respond(new WP_Error('view-error', "\$view must be a subclass of \Digitalis\View, '{$view}' provided."));

            $params     = [];
            $definition = $this->get_definition();

            if ($definition['args'] ?? []) foreach ($definition['args'] as $key => $arg) $params[$key] = $request->get_param($key);

            return $this->respond($this->render_view($view, $params));

        } else {

            return $this->request_inject($request, 'callback');

        }

    }
    
    //

    protected function respond ($response) {

        return rest_ensure_response($response);

    }

    protected function request_inject (WP_REST_Request $request, $method) {
    
        $params = $request->get_params();
        $values = [];

        if ($args = $this->get_args()) foreach ($args as $key => $arg) {
        
            if ((!$class = ($arg['class'] ?? false))) continue;
            if (!isset($params[$key]))                continue;

            if ($value = static::value_inject($class, $params[$key])) {

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

}