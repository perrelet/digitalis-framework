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
    
        add_action('init',                   [$this, 'add_rewrite_rules']);
        add_filter('rest_pre_serve_request', [$this, 'rest_pre_serve_request'], 10, 4);

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

    protected function is_html_rest_request (WP_REST_Request $request)  {

        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, $this->rest_prefix . '/'))                   return true;
        if (strtolower((string) $request->get_header('HX-Request')) === 'true') return true; // HTMX request

        return false;

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

        status_header($status);

        if (!isset($headers['Content-Type'])) $headers['Content-Type'] = 'text/html; charset=UTF-8';

        foreach ($headers as $name => $value) header($name . ': ' . $value);

        if (is_string($body) || is_numeric($body)) {

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