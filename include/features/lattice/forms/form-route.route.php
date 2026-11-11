<?php

namespace Digitalis;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WeakMap;

class Form_Route extends Route {

    const REDIRECT_HEADER = 'X-Lattice-Redirect';

    protected $rules;
    protected $verdicts;

    public function __construct () {

        parent::__construct();

        if ($this->view) _doing_it_wrong(static::class, 'A Form_Route draws no $view: it would be drawn over the answer.', '');

    }

    // Every rule moves into one check, so all problems come back at once.
    public function get_definition () {

        if (!is_null($this->definition_cache)) return $this->definition_cache;

        $definition  = parent::get_definition();
        $this->rules = $definition['args'] ?? [];

        foreach ($this->rules as $name => $rule) {

            unset($rule['required'], $rule['validate_callback']);
            $rule['sanitize_callback'] = [$this, 'sanitize_arg'];
            $definition['args'][$name] = $rule;

        }

        $own = $definition['validate_callback'] ?? null;

        $definition['validate_callback'] = function (WP_REST_Request $request) use ($own) {

            $result = $this->check_rules($request);

            return (is_wp_error($result) || !$own) ? $result : call_user_func($own, $request);

        };

        return $this->definition_cache = $definition;

    }

    public function get_rules (WP_REST_Request $request) : array {

        $this->get_definition();

        $rules = [];

        foreach ($this->rules as $name => $rule) $rules[$name] = apply_filters('lattice.forms.rule', $rule, $name, $this);

        return $rules;

    }

    public function get_path (array $params = []) : string {

        return preg_replace_callback('/\(\?P<(\w+)>[^)]*\)/', fn ($m) => rawurlencode((string) ($params[$m[1]] ?? '')), $this->get_rest_path());

    }

    // As WordPress's dispatch will set them: the pattern's captures from the encoded path, as strings.
    public function make_request (string $path) : WP_REST_Request {

        $request = new WP_REST_Request('POST', $path);

        if (preg_match('@^' . $this->get_rest_path() . '$@i', $path, $matches)) $request->set_url_params(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

        return $request;

    }

    // Memoised: the check and WordPress's dispatch both ask.
    public function permission_wrap (WP_REST_Request $request) {

        $this->verdicts ??= new WeakMap();

        if (!isset($this->verdicts[$request])) $this->verdicts[$request] = parent::permission_wrap($request);

        return $this->verdicts[$request];

    }

    public function check_rules (WP_REST_Request $request) {

        $permission = $this->permission_wrap($request);

        if (is_wp_error($permission)) return $this->refuse_permission($permission->get_error_code(), $permission->get_error_message(), (array) $permission->get_error_data());
        if (!$permission)             return $this->refuse_permission('rest_forbidden', __('Sorry, you are not allowed to do that.'), ['status' => rest_authorization_required_code()]);

        $values   = Form_Rules::get_submitted($request);
        $problems = [];

        foreach ($this->get_rules($request) as $name => $rule) {

            if (!Form_Rules::is_applicable($rule, $values)) {

                // Not set to null in the sanitizer: WordPress sanitizes defaults too.
                if (array_key_exists($name, $values)) $request->set_param($name, null);
                continue;

            }

            if (!empty($rule['costly'])) continue;

            if ($problem = $this->judge_rule($name, $rule, $values[$name] ?? null, $request)) $problems[$name] = $problem;

        }

        return $problems ? Form_Rules::refuse($problems) : true;

    }

    protected function judge_rule (string $name, array $rule, $value, WP_REST_Request $request) : ?string {

        if (Form_Rules::is_empty($value)) return empty($rule['required']) ? null : Form_Rules::get_message('missing', $name, $rule);

        if (!empty($rule['type'])) {

            $valid = rest_validate_value_from_schema($value, $rule, $name);
            if (is_wp_error($valid)) return Form_Rules::get_message($valid->get_error_code(), $name, $rule, $valid->get_error_message());

        }

        if (empty($rule['validate_callback'])) return null;

        $valid = call_user_func($rule['validate_callback'], $value, $request, $name);

        if ($valid === false)  return Form_Rules::get_message('invalid', $name, $rule);
        if (is_wp_error($valid)) return Form_Rules::get_message($valid->get_error_code(), $name, $rule, $valid->get_error_message());

        return null;

    }

    // Null falls through to the default.
    public function sanitize_arg ($value, WP_REST_Request $request, string $name) {

        $rule = $this->get_rules($request)[$name] ?? [];

        if (Form_Rules::is_empty($value)) return null;

        if (!empty($rule['sanitize_callback'])) return call_user_func($rule['sanitize_callback'], $value, $request, $name);
        if (!empty($rule['type']))              return rest_sanitize_value_from_schema($value, $rule, $name);

        return $value;

    }

    public function callback_wrap (WP_REST_Request $request) {

        if ($this->get_require_nonce() && is_wp_error($nonce = $this->check_nonce($request))) return $this->respond($nonce);

        if (is_wp_error($costly = $this->check_costly($request))) return $this->respond($costly);

        return parent::callback_wrap($request);

    }

    protected function check_costly (WP_REST_Request $request) {

        $values   = Form_Rules::get_submitted($request);
        $problems = [];

        foreach ($this->get_rules($request) as $name => $rule) {

            if (empty($rule['costly']) || !Form_Rules::is_applicable($rule, $values)) continue;

            if (empty($rule['validate_callback'])) return new WP_Error('lattice_form_unavailable', 'This form cannot be checked just now. Please try again later.', ['status' => 503]);

            $valid = call_user_func($rule['validate_callback'], $values[$name] ?? null, $request, $name);

            if (is_wp_error($valid) && ((int) ($valid->get_error_data()['status'] ?? 0) >= 500)) return $valid;

            if ($valid === false)    $problems[$name] = Form_Rules::get_message('invalid', $name, $rule);
            if (is_wp_error($valid)) $problems[$name] = Form_Rules::get_message($valid->get_error_code(), $name, $rule, $valid->get_error_message());

        }

        return $problems ? Form_Rules::refuse($problems) : true;

    }

    // The form handler turns this into a 303, or HX-Redirect for htmx.
    public function redirect (string $url) : WP_REST_Response {

        $url = wp_validate_redirect($url, wp_get_referer() ?: home_url('/'));

        return new WP_REST_Response(['redirect' => $url], 200, [static::REDIRECT_HEADER => $url]);

    }

    protected function respond ($response) {

        return parent::respond(($response instanceof View) ? (string) $response : $response);

    }

    protected function refuse_permission (string $code, string $message, array $data) : WP_Error {

        return new WP_Error($code, $message, ['permission' => true] + $data);

    }

}
