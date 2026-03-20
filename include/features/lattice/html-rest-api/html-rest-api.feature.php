<?php

namespace Digitalis;

use WP_REST_Request;
use WP_REST_Response;

class HTML_REST_API extends Feature {

    protected $rest_prefix = 'wp-html';

    public function __construct () {

        include 'html-rest-url.class.php';

        REST_URL_Builder::get_instance()->register_format('html', function (Route $route) {
            return (new HTML_REST_URL($this->rest_prefix))->get_url("{$route->get_namespace()}/{$route->get_route()}");
        });
    
        add_action('init',                       [$this, 'add_rewrite_rules']);
        add_filter('rest_authentication_errors', [$this, 'rest_authentication_errors'], 5);
        add_filter('rest_pre_serve_request',     [$this, 'rest_pre_serve_request'], 10, 4);

    }

    protected function get_rewrite_regex () {
    
        return '^' . preg_quote($this->rest_prefix, '#') . '/(.*)?';
    
    }

    public function add_rewrite_rules () {
    
        add_rewrite_rule(
            $this->get_rewrite_regex(),
            'index.php?rest_route=/$matches[1]',
            'top'
        );
    
    }

    public function rest_authentication_errors ($result) {

        if (!empty($result))             return $result;
        if (!$this->is_html_rest_path()) return $result;

        // WordPress auth cookies are scoped to /wp-admin/ and /wp-content/plugins/,
        // so standard REST cookie auth doesn't fire for wp-html/ requests.
        // We authenticate manually using the logged_in cookie (path: /) instead.

        $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? $_REQUEST['_wpnonce'] ?? null;
        if (!$nonce) return $result;

        $user_id = wp_validate_logged_in_cookie(false);
        if (!$user_id) return $result;

        wp_set_current_user($user_id);

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error('rest_cookie_invalid_nonce', __('Cookie check failed'), ['status' => 403]);
        }

        return true;

    }

    protected function is_html_rest_path (): bool {

        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $path = ltrim((string) $path, '/');

        return str_starts_with($path, $this->rest_prefix . '/');

    }

    protected function is_html_rest_request (WP_REST_Request $_request): bool {

        return $this->is_html_rest_path();

    }

    public function rest_pre_serve_request ($served, $result, $request, $wp_rest_server) {

        if (!$this->is_html_rest_request($request)) return $served;

        if (is_wp_error($result)) {

            $status = (int) $result->get_error_data('status');
            if ($status < 400) $status = 500;

            status_header($status);
            header('Content-Type: text/plain; charset=UTF-8');

            echo $result->get_error_message();
            return true;

        }

        if ($result instanceof WP_REST_Response) {

            $status  = $result->get_status();
            $headers = $result->get_headers();
            $body    = $result->get_data();

            if ($result->is_error()) {

                $message = '';

                if (is_array($body) && isset($body['message']) && is_string($body['message'])) {
                    $message = $body['message'];
                } elseif (is_string($body)) {
                    $message = $body;
                } else {
                    $message = 'Request failed.';
                }

                status_header($status);
                header('Content-Type: text/plain; charset=UTF-8');
                echo $message;
                return true;

            }

        } else {

            $status  = 200;
            $headers = [];
            $body    = $result;
    
        }

        if ($body instanceof View) $body = (string) $body;

        status_header($status);

        if (!isset($headers['Content-Type'])) $headers['Content-Type'] = 'text/html; charset=UTF-8';

        foreach ($headers as $name => $value) header($name . ': ' . $value);

        if (is_string($body) || is_numeric($body) || is_null($body)) {

            echo (string) $body;
            return true;

        }

        header('Content-Type: text/plain; charset=UTF-8', true);
        echo 'HTML REST endpoints must return a string body.';
        return true;

    }

    public function get_path ($path = '') {
    
        $url   = $this->get_url($path);
        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '';

        if (!empty($parts['query']))    $path .= '?' . $parts['query'];
        if (!empty($parts['fragment'])) $path .= '#' . $parts['fragment'];

        return $path;
    
    }

    public function get_url ($path = '', $blog_id = null, $scheme = 'rest') {

        $url = get_rest_url($blog_id, $path, $scheme);

        $rest_prefix = trim(rest_get_url_prefix(), '/');
        $html_prefix = trim($this->rest_prefix, '/');

        if (!($parts = wp_parse_url($url)) || empty($parts['path'])) return $url;

        $parts['path'] = preg_replace(
            '#/' . preg_quote($rest_prefix, '#') . '/#',
            '/' . $html_prefix . '/',
            $parts['path'],
            1
        );

        return $this->unparse_url($parts);

    }

    protected function unparse_url ($parts) {

        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user     = $parts['user'] ?? '';
        $pass     = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth     = ($user !== '' || $pass !== '') ? $user . $pass . '@' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;

    }

}