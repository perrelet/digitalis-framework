<?php

namespace Digitalis;

class Visitor extends Service {

    protected $user;
    protected $ip;

    protected $cookie_prefix       = 'digitalis_';
    protected $default_cookie_ttl  = MONTH_IN_SECONDS;

    public function __construct () {
    
        $this->user = User::inst();
    
    }

    // Identity

    public function get_user () {
    
        return $this->user;
    
    }

    public function is_logged_in () {
    
        return $this->user !== null;
    
    }

    public function get_user_id () {
    
        return $this->is_logged_in() ? $this->user->get_id() : null;
    
    }

    public function get_session_id () {

        if (isset($_COOKIE['digitalis_sid'])) return $_COOKIE['digitalis_sid'];

        $id = bin2hex(random_bytes(16));
        setcookie('digitalis_sid', $id, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        return $id;
    
    }

    public function get_id () {

        return $this->is_logged_in() ? $this->get_user_id() : $this->get_session_id();

    }

    public function get_identity_hash ($salt = 'visitor_identity') {

        $entropy = [
            $this->get_session_id(),
            $this->get_ip(false), 
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        ];

        if ($salt) $entropy[] = wp_salt($salt);

        return substr(hash('sha256', implode('|', $entropy)), 0, 32);

    }

    // Cookie

    public function get_cookie_prefix () {
    
        return $this->cookie_prefix;
    
    }

    public function set_cookie_prefix ($cookie_prefix) {
    
        $this->cookie_prefix = $cookie_prefix;
        return $this;
    
    }

    public function get_default_cookie_ttl () {
    
        return $this->default_cookie_ttl;
    
    }

    public function set_default_cookie_ttl ($ttl) {
    
        $this->default_cookie_ttl = $ttl;
        return $this;
    
    }

    protected function get_cookie_args ($ttl = null, $args = []) {

        if (is_null($ttl)) $ttl = $this->get_default_cookie_ttl();

        $ttl = match ($ttl) {
            null    => MONTH_IN_SECONDS,
            'hour'  => HOUR_IN_SECONDS,
            'day'   => DAY_IN_SECONDS,
            'week'  => WEEK_IN_SECONDS,
            'month' => MONTH_IN_SECONDS,
            'year'  => YEAR_IN_SECONDS,
            default => (int) $ttl,
        };
    
        $args = wp_parse_args($args, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'domain'   => null,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $args['samesite'] = ucfirst(strtolower((string) $args['samesite']));
        if (!in_array($args['samesite'], ['Lax', 'Strict', 'None'], true)) $args['samesite'] = 'Lax';
        if ($args['samesite'] === 'None') $args['secure'] = true;

        return $args;
    
    }

    public function set_cookie ($key, $value, $ttl = null, $args = []) {

        $name  = $this->get_cookie_prefix() . $key;
        $value = !is_scalar($value) ? wp_json_encode($value) : (string) $value;
        $args  = $this->get_cookie_args($ttl, $args);

        setcookie($name, $value, $args);
        $_COOKIE[$name] = $value;

        return $this;

    }

    public function set_cookie_js ($key, $value, $ttl = null, $args = [], $defer = true) {

        $name  = $this->get_cookie_prefix() . $key;
        $value = !is_scalar($value) ? wp_json_encode($value) : (string) $value;
        $args  = $this->get_cookie_args($ttl, $args);

        $args['expires'] = gmdate('D, d M Y H:i:s T', (int) $args['expires']);

        $cookie_parts = [];
        $cookie_parts[] = "{$name}={$value}";
        if ($args['secure']) $cookie_parts[] = 'Secure';
        unset($args['secure']);
        foreach ($args as $key => $value) if (!empty($value)) $cookie_parts[] = "{$key}={$value}";

        $cookie_js = json_encode(implode('; ', $cookie_parts));

        $script = "<script>(function(){ try { document.cookie = {$cookie_js}; } catch(e) {} })();</script>";

        if ($defer && function_exists('add_action')) {

            add_action('wp_footer', fn() => print $script, 99);

        } else {
    
            echo $script;

        }

    }

    public function ensure_cookie ($key, $value, $ttl = null, $args = [], $defer = false) {

        if (headers_sent() || $defer) {

            return $this->set_cookie_js($key, $value, $ttl, $defer);

        } else {

            return $this->set_cookie($key, $value, $ttl, $args);

        }

    }

    public function get_cookie ($key, $default = null, $decode_json = true) {

        $name = $this->get_cookie_prefix() . $key;

        if (!isset($_COOKIE[$name])) return $default;

        $raw = stripslashes($_COOKIE[$name]);

        if ($decode_json) {

            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;

        }

        return $raw;

    }

    public function forget_cookie ($key, $args = []) {

        $name = $this->get_cookie_prefix() . $key;

        $args = wp_parse_args($args, [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        setcookie($name, '', $args);
        unset($_COOKIE[$name]);

        return $this;

    }

    // Network

    public function get_ip ($deep_detect = true) {

        if (is_null($this->ip)) {

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
            if ($deep_detect) {
    
                $headers = [
                    'HTTP_X_FORWARDED_FOR',
                    'HTTP_CLIENT_IP',
                    'HTTP_CF_CONNECTING_IP', // Cloudflare
                ];
        
                foreach ($headers as $header) {
                    
                    if (empty($_SERVER[$header])) continue;
    
                    $parts     = explode(',', $_SERVER[$header]);
                    $candidate = trim($parts[0]);
    
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        $ip = $candidate;
                        break;
                    }
                    
                }
    
            }
        
            $this->ip = $ip ?: '0.0.0.0';

        }

        return $this->ip;

    }

    public function get_protocol () {

        if (
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"'))
        ) {
            return 'https';
        }

        return 'http';

    }

    public function get_host () {

        if (!isset($_SERVER['HTTP_HOST'])) return null;

        $host = strtolower(trim($_SERVER['HTTP_HOST']));
        return preg_replace('/:\d+$/', '', $host);

    }

    public function get_request_uri () {

        return $_SERVER['REQUEST_URI'] ?? '/';

    }

    public function get_request_path () {

        return parse_url($this->get_request_uri(), PHP_URL_PATH) ?: '/';

    }

    public function get_user_agent () {

        return $_SERVER['HTTP_USER_AGENT'] ?? null;

    }

    public function get_referer ($validated = true) {

        if (!$ref = $_SERVER['HTTP_REFERER'] ?? null) return null;

        $ref = esc_url_raw($ref);

        if ($validated) {

            $host     = $this->get_host();
            $ref_host = parse_url($ref, PHP_URL_HOST);

            if ($host && $ref_host && ($ref_host === $host)) return null;

        }

        return $ref;

    }

    public function get_request_time () {

        return (float) $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? microtime();

    }

    public function get_headers (bool $normalize = true) {

        if (function_exists('getallheaders')) {
    
            $headers = getallheaders();

        } else {

            $headers = [];

            foreach ($_SERVER as $key => $value) if (str_starts_with($key, 'HTTP_')) {

                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;

            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {

                $name = str_replace('_', '-', $key);
                $headers[$name] = $value;

            }

        }

        if (!$normalize) return $headers;

        $normalized = [];

        foreach ($headers as $name => $value) {

            $pretty = implode('-', array_map('ucfirst', array_map('strtolower', explode('-', $name))));
            $normalized[$pretty] = $value;

        }

        return $normalized;

    }

    public function get_request_context() {

        return [
            'protocol' => $this->get_protocol(),
            'host'     => $this->get_host(),
            'uri'      => $this->get_request_uri(),
            'time'     => $this->get_request_time(),
            'headers'  => $this->get_headers(),
        ];

    }

    // Geo

    public function get_country () {

        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {

            $code = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
            return ($code !== 'XX') ? $code : null;
    
        }
    
        if (function_exists('geoip_country_code_by_name')) {

            $ip   = $this->get_ip(false);
            $code = @geoip_country_code_by_name($ip);

            if ($code && strlen($code) === 2) return strtoupper($code);

        }
    
    }

    public function get_language () {
    
        if (!$header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') return get_locale();
    
        $languages = explode(',', $header);
    
        return str_replace('_', '-', strtolower(substr(trim($languages[0]), 0, 5)));

    }

    // Tracking

    public function get_utm_params () {

        $params = [
            'utm_source'   => null,
            'utm_medium'   => null,
            'utm_campaign' => null,
            'utm_term'     => null,
            'utm_content'  => null,
        ];

        parse_str($_SERVER['QUERY_STRING'] ?? '', $query_vars);
        foreach ($params as $key => $value) if (!empty($query_vars[$key])) $params[$key] = sanitize_text_field($query_vars[$key]);

        if (!array_filter($params) && ($referer = $this->get_referer(false)) && ($url_parts = parse_url($referer)) && !empty($url_parts['query'])) {

            parse_str($url_parts['query'], $ref_query);
            foreach ($params as $key => $value) if (!empty($ref_query[$key])) $params[$key] = sanitize_text_field($ref_query[$key]);
        
        }

        return $params;

    }

    protected function normalize_path ($path) {

        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim($path, '/');
        return strtolower(rtrim($path, '/')) ?: '/';

    }

}