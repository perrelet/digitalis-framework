<?php

namespace Digitalis;

final class HTML_REST_URL {

    protected string $rest_prefix;

    public function __construct (string $rest_prefix = 'wp-html') {

        $this->rest_prefix = $rest_prefix;

    }

    public function get_path (string $path = '') {
    
        $url   = $this->get_url($path);
        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '';

        if (!empty($parts['query']))    $path .= '?' . $parts['query'];
        if (!empty($parts['fragment'])) $path .= '#' . $parts['fragment'];

        return $path;
    
    }

    public function get_url (string $path = '', ?int $blog_id = null, string $scheme = 'rest') {

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

    protected function unparse_url (array $parts) {

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