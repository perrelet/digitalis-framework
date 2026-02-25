<?php

namespace Digitalis;

class REST_URL_Builder extends Singleton {

    protected $formatters = [];

    public function __construct () {

        $this->register_format('json', function (Route $route) {
            return get_rest_url(null, "{$route->get_namespace()}/{$route->get_route()}");
        });

    }

    public function register_format (string $name, callable $builder) {

        $this->formatters[$name] = $builder;

    }

    public function for_route (Route $route, array $query_params = [], bool $nonce = true, string $format = 'json') {

        if (!isset($this->formatters[$format])) throw new \InvalidArgumentException("REST URL format '{$format}' not registered.");

        $url = ($this->formatters[$format])($route);
        
        if ($query_params) $url = $route->add_query_params($url, $query_params);
        if ($nonce)        $url = $route->nonce_url($url);

        return $url;

    }

}