<?php

namespace Lattice;

use WP_REST_Request;
use WP_Error;

/**
 * They found themselves standing on the very edge of the Wild Wood. 
 * Rocks and brambles and tree-roots behind them, confusedly heaped and tangled; in front, a great space of quiet fields, hemmed by lines of hedges black on the snow, and, far ahead, a glint of the familiar old river, while the wintry sun hung red and low on the horizon.
 * @api
 * @author Digitalis Web Corp <jamie@digitalis.ca>
 * @copyright 2023 Digitalis Web Corp
 */

class Route extends Factory {

    use Dependency_Injection;

    protected static $cache_group    = self::class;
    protected static $cache_property = 'route';

    /**
     * @link /{$format}/{$namespace}/{$route}       JSON Response.
     * @var string       $namespace               The namespace and version.
     * @var ?string      $route                   The endpoint for this route. Null registers nothing, for base classes.
     * @var bool         $wp_query                Whether to emulate a normal WordPress query. Note: $wp isn't reset so repeat `rest_do_request` calls may result in unexpected behaviour. 
     * @var string       $handler                 The handler method name responsible for executing the route's primary application logic.
     * @var bool|string  $view                    The view class to render at the endpoint (view args are inherited from `WP_REST_Request`). Set `false` to turn off and use the `$handler`.
     * @var bool         $require_nonce           Enforce nonce check.
     * @var array        $definition              Args passed to `register_rest_route`.
     * @var array        $args                    $args['args'] passed to `register_rest_route`.
     */

    protected $namespace     = 'digitalis/v1';
    protected $route         = null;
    protected $format        = null;
    protected $wp_query      = false;
    protected $handler       = 'handle';
    protected $view          = false; /* View::class */
    protected $require_nonce = false;
    protected $definition    = [];
    protected $args          = [];

    public function __construct () {

        add_action('rest_api_init', [$this, 'register_route']);

        if ($this->wp_query) add_filter('rest_request_before_callbacks', [$this, 'maybe_set_wp_query_vars'], 10, 3);

    }

    public function register_route () {

        if (is_null($this->get_route())) return;

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

    public function get_rest_path () {

        return '/' . trim($this->get_namespace(), '/') . '/' . ltrim($this->get_route(), '/');

    }

    public function get_view () {

        return $this->view;

    }

    public function get_handler () {

        return $this->handler;

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

    public function handle (WP_REST_Request $request) {

        return $this->request_inject($request, 'callback');

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

    // `$_REQUEST` and `WP_REST_Request::get_param()` both prefer the body, so a `wp_nonce_field()`
    // in a surrounding form shadows the nonce `nonce_url()` puts in the query string.
    public static function collect_nonce_candidates ($request = null) {

        $candidates = [
            $_SERVER['HTTP_X_WP_NONCE'] ?? null,
            $_SERVER['HTTP_NONCE']      ?? null,
            $_GET['_wpnonce']           ?? null,
            $_POST['_wpnonce']          ?? null,
        ];

        if ($request instanceof WP_REST_Request) $candidates[] = $request->get_param('_wpnonce');

        $candidates = array_filter($candidates, fn ($nonce) => is_string($nonce) && ($nonce !== ''));

        return array_values(array_unique($candidates));

    }

    // Separate from collection: verification depends on the current user, so callers that
    // authenticate manually must verify after `wp_set_current_user()`.
    public static function find_valid_nonce (array $candidates, $action = 'wp_rest') {

        foreach ($candidates as $nonce) if (wp_verify_nonce($nonce, $action)) return $nonce;

        return null;

    }

    public function check_nonce (WP_REST_Request $request) {

        $candidates = static::collect_nonce_candidates($request);

        if (!$candidates) return new WP_Error(
            __NAMESPACE__ . '_rest_missing_nonce',
            'Missing the `Nonce` header or `_wpnonce` parameter. This endpoint requires a valid nonce.',
            ['status' => 401],
        );

        if (!static::find_valid_nonce($candidates)) return new WP_Error(
            __NAMESPACE__ . '_rest_invalid_nonce',
            'Nonce is invalid.',
            ['status' => 403],
        );

        return true;

    }

    public function is_this_route (WP_REST_Request $request) {

        if ($request->get_route() !== $this->get_rest_path()) return false;

        return true;

    }

    //

    protected $permission_request;
    protected $permission_cache;

    // Cached by request identity: rest_send_allow_header() asks again per allowed method with the same request. Redispatching a mutated object reuses the verdict.
    public function permission_wrap (WP_REST_Request $request) {

        if ($this->permission_request !== $request) {

            $this->permission_request = $request;
            $this->permission_cache   = $this->request_inject($request, 'permission');

        }

        return $this->permission_cache;
    
    }

    // The 0.5 discovery instrument for the 1.0 default of GET-only routes; nothing throws.
    protected function notice_defaults (WP_REST_Request $request) {

        $method = $request->get_method();

        if (in_array($method, ['GET', 'HEAD'], true)) return;

        $clauses = [];

        if (!isset($this->definition['methods']))                                                         $clauses[] = "handles {$method} through the default methods ['GET', 'POST']; declare \$definition['methods'] (1.0 defaults to GET)";
        if ((new \ReflectionMethod($this, 'permission'))->getDeclaringClass()->getName() === self::class) $clauses[] = "handles {$method} with the base permission(), which allows everyone";

        if (!$clauses) return;

        $message = implode('. ', $clauses) . '.';

        _doing_it_wrong(static::class, $message, 'Lattice 1.0');

        // A served REST request suppresses the trigger and only sets an X-WP-DoingItWrong header, so the log line is written here.
        if (defined('REST_REQUEST') && REST_REQUEST && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) error_log('Lattice: ' . static::class . ': ' . $message);

    }

    public function callback_wrap (WP_REST_Request $request) {

        if (WP_DEBUG) $this->notice_defaults($request);

        if ($this->get_require_nonce()) {

            $nonce_check = $this->check_nonce($request);
            if ($nonce_check instanceof WP_Error) return $nonce_check;

        }

        $handler = $this->get_handler();

        if (!method_exists($this, $handler)) return $this->respond(new WP_Error('route-handler-missing', "Handler method '{$handler}' does not exist on " . static::class, ['status' => 500] ));

        $result = $this->request_inject($request, $handler);
        if ($result instanceof WP_Error) return $this->respond($result);

        if ($view = $this->get_view()) {

            if (!is_subclass_of($view, View::class)) return $this->respond(new WP_Error('view-error', "\$view must be a subclass of \Lattice\View, '{$view}' provided."));

            $params     = [];
            $definition = $this->get_definition();

            if ($definition['args'] ?? []) foreach ($definition['args'] as $key => $arg) $params[$key] = $request->get_param($key);

            return $this->respond($this->render_view($view, $params));

        }

        return $this->respond($result);

    }
    
    //

    protected function respond ($response) {

        return rest_ensure_response($response);

    }

    protected function request_inject (WP_REST_Request $request, $method) {

        if (!method_exists($this, $method)) return new WP_Error('route-handler-missing', "Handler method '{$method}' does not exist on " . static::class, ['status' => 500]);

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

    // Strict

    // Falls through to PHP's own errors so a typo is never hidden; strict only adds the get_param() hint. Note is_callable([$route, x]) is now true for any x.
    public function __call ($name, $args) {

        if ($name === 'get_param') Strict::fail(static::class, 'has no get_param(); the request does.', 'Read $request->get_param() from the WP_REST_Request handed to permission() and the handler.');

        Strict::undefined_method($this, $name);

    }

    public static function strict_audit (string $class) {

        $reflection = new \ReflectionClass($class);
        $class      = $reflection->getName();

        foreach (['method', 'methods'] as $prop) {

            if (self::declares_prop($reflection, $class, $prop)) Strict::violation($class, "declares \${$prop}, which Route never reads.", "Move it to \$definition = ['methods' => ...].");

        }

        if (self::declares_prop($reflection, $class, 'version'))               Strict::violation($class, "declares \$version, which Route never reads.", "Put the version in \$namespace: 'my-plugin/v1'.");
        if (self::declares_method($reflection, $class, 'permission_callback')) Strict::violation($class, "defines permission_callback(), which Route never calls; permission_wrap() calls permission().", "Rename it permission(WP_REST_Request \$request).");
        if (self::declares_prop($reflection, $class, 'rest_args'))             Strict::violation($class, "declares \$rest_args, a Deprecated_Route property Route never reads.", "\$definition carries register_rest_route's arguments and \$args its 'args' map.");
        if (self::declares_prop($reflection, $class, 'html_prefix'))           Strict::violation($class, "declares \$html_prefix, a Deprecated_Route property Route never reads.", "HTML is served at wp-html/ once the app loads the 'lattice/html-rest-api' feature; \$format = 'html' then points get_url() there.");

        foreach (['get_params', 'get_rest_args', 'register_api_routes'] as $method) {

            if (self::declares_method($reflection, $class, $method)) Strict::violation($class, "defines {$method}(), a Deprecated_Route method Route never calls, so nothing it returns reaches register_rest_route().", "Route registers itself from protected \$args (register_rest_route's 'args' map; override public get_args() when computed) and \$definition.");

        }

    }

    protected static function declares_prop ($reflection, $class, $name) {

        return $reflection->hasProperty($name) && ($reflection->getProperty($name)->getDeclaringClass()->name === $class);

    }

    protected static function declares_method ($reflection, $class, $name) {

        return $reflection->hasMethod($name) && ($reflection->getMethod($name)->getDeclaringClass()->name === $class);

    }

}
