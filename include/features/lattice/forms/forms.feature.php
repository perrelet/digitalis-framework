<?php

namespace Digitalis;

use Digitalis\Field\Checkbox;
use Digitalis\Field\Checkbox_Group;
use Digitalis\Field\Hidden;
use Digitalis\Field\Input;
use Digitalis\Field\Number;
use Digitalis\Field\Radio;
use Digitalis\Field\Select;
use Digitalis\Field\Textarea;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/form-rules.utility.php';
require_once __DIR__ . '/form-route.route.php';
require_once __DIR__ . '/form-summary.view.php';

// Forms that check themselves (docs/FORMS.md): a form posts to its own page, which hands the post to its Form_Route and draws the result.
class Lattice_Forms extends Feature {

    const FIELD = '_lattice_form';

    // The controls a browser can focus to say a required field is empty.
    const REQUIRABLE = [Input::class, Textarea::class, Select::class, Checkbox::class, Radio::class];
    const NOT_REQUIRABLE = [Checkbox_Group::class, Hidden::class];

    protected $id = 'forms';

    protected $body;
    protected $kept;
    protected $results = [];

    public function get_hooks () {

        return [
            'do_parse_request'      => ['set_aside', 0],
            'parse_request'         => ['give_back', 0],
            'template_redirect'     => ['dispatch', 1],
            'Digitalis/Form/Params' => 'bind_form',
        ];

    }

    // Before WordPress reads the request, so a field named `name` or `s` never becomes a query.
    public function set_aside ($parse) {

        if ((($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') || !isset($_POST[static::FIELD])) return $parse;

        $this->body = wp_unslash($_POST);
        $this->kept = [$_POST, $_REQUEST];

        $_POST    = [];
        $_REQUEST = $_GET;

        return $parse;

    }

    // A request to the REST API itself keeps its body, as if nothing had happened.
    public function give_back ($wp) {

        if (!$this->body || empty($wp->query_vars['rest_route'])) return;

        [$_POST, $_REQUEST] = $this->kept;
        $this->body = $this->kept = null;

    }

    public function dispatch () {

        if (!$this->body) return;

        [$path, $id] = $this->read_target((string) ($this->body[static::FIELD] ?? ''));

        if (!$path || is_null($params = $this->match($path))) return;

        nocache_headers();

        $user     = get_current_user_id();
        $nonces   = array_filter([$this->body['_wpnonce'] ?? null, $_SERVER['HTTP_X_WP_NONCE'] ?? null], 'is_string');
        $switched = $user && !Route::find_valid_nonce($nonces);

        if ($switched) wp_set_current_user(0);

        try {

            $request = new WP_REST_Request('POST', $path);
            $request->set_body_params($this->body);
            $request->set_url_params($params);

            $response = rest_do_request($request);

        } finally {

            if ($switched) wp_set_current_user($user);

        }

        $this->answer($id, $response);

    }

    protected function read_target (string $target) : array {

        [$path, $id] = array_pad(explode('#', $target, 2), 2, '');

        $path = '/' . ltrim($path, '/');
        $id   = preg_replace('/[^A-Za-z0-9_-]/', '', $id);

        return ($id && ($path !== '/')) ? [$path, $id] : [null, null];

    }

    // The route WordPress would match, and only if it is a form route's: a post can reach nothing else.
    protected function match (string $path) : ?array {

        foreach (rest_get_server()->get_routes() as $route => $handlers) {

            if (!preg_match('@^' . $route . '$@i', $path, $matches)) continue;

            foreach ($handlers as $handler) {

                $callback = $handler['callback'] ?? null;

                if (is_array($callback) && ($callback[0] instanceof Form_Route) && ($callback[1] === 'callback_wrap') && !empty($handler['methods']['POST'])) return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            }

            return null;

        }

        return null;

    }

    protected function answer (string $id, WP_REST_Response $response) {

        $data = $response->get_data();

        if ($url = $response->get_headers()[Form_Route::REDIRECT_HEADER] ?? null) {

            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {

                status_header(200);
                header('HX-Redirect: ' . esc_url_raw($url));

            } else {

                wp_redirect($url, 303);

            }

            $this->end();

        }

        if ($response->is_error()) {

            $error = $response->as_error();
            $about = (array) $error->get_error_data();

            $this->results[$id] = [
                'message'  => $error->get_error_message(),
                'problems' => (array) ($about['params'] ?? []),
                'values'   => empty($about['permission']) ? $this->body : [],
            ];

            return;

        }

        if (is_string($data)) {

            $this->results[$id] = ['markup' => $data];
            return;

        }

        _doing_it_wrong(__METHOD__, 'A Form_Route answers redirect(), markup or a View.', '');

        $this->results[$id] = [];

    }

    protected function end () {

        exit;

    }

    public function get_result (string $id) : ?array {

        return $this->results[$id] ?? null;

    }

    // A form given a `route` and an `id` posts to its page, takes its fields' rules from the route, and draws its result.
    public function bind_form ($p, $form) {

        if (empty($p['route'])) return $p;

        $route = is_object($p['route']) ? $p['route'] : (class_exists($p['route']) ? $p['route']::get_instance() : null);

        if (!($route instanceof Form_Route)) {

            _doing_it_wrong(get_class($form), 'A form\'s route must be a Form_Route.', '');
            return $p;

        }

        if (empty($p['id'])) {

            _doing_it_wrong(get_class($form), 'A form with a route needs a stable id.', '');
            return $p;

        }

        $id     = (string) $p['id'];
        $path   = $route->get_path((array) ($p['route_params'] ?? []));
        $rules  = $route->get_rules(new WP_REST_Request('POST', $path));
        $result = $this->get_result($id);
        $page   = add_query_arg([]);

        $p['method']                         = 'post';
        $p['action']                         = $page;
        $p['attributes']['data-lattice-form'] = $id;

        if (apply_filters('lattice.forms.htmx', wp_script_is('htmx', 'enqueued') || wp_script_is('htmx', 'done'), $form)) $p['attributes'] += [
            'hx-post'   => $page,
            'hx-select' => "#{$id}",
            'hx-target' => 'this',
            'hx-swap'   => 'outerHTML',
        ];

        $fields = (array) $p['fields'];

        $p['field_params'] = $this->merge_params($this->field_params($id, $rules, $fields, $result), (array) ($p['field_params'] ?? []));

        $before = [new Hidden(['name' => static::FIELD, 'value' => ltrim($path, '/') . "#{$id}", 'id' => "{$id}-target", 'wrap' => false])];

        if (is_user_logged_in()) $before[] = new Hidden(['name' => '_wpnonce', 'value' => wp_create_nonce('wp_rest'), 'id' => "{$id}-nonce", 'wrap' => false]);

        if ($result) {

            if (isset($result['markup'])) $fields = [new Component(['tag' => 'div', 'classes' => ['form-answer'], 'content' => $result['markup']])];
            elseif ($summary = $this->summary($id, $result, $rules)) array_unshift($before, $summary);

        }

        $p['fields'] = array_merge($before, $fields);

        return $p;

    }

    protected function field_params (string $id, array $rules, array $fields, ?array $result) : array {

        $params = [];
        $first  = true;

        foreach ($rules as $name => $rule) {

            $class = $this->field_class($name, $fields);
            $param = ['id' => "{$id}-{$name}"];

            if (!empty($rule['when'])) $param['condition'] = array_values($rule['when']);

            if ($class) $param = array_merge($param, $this->browser_params($class, $rule));

            if ($class && (is_a($class, Checkbox::class, true) || is_a($class, Checkbox_Group::class, true))) $param['null_value'] = null;

            if ($result && !isset($result['markup'])) {

                if (array_key_exists('values', $result)) $param['value'] = $result['values'][$name] ?? ((($rule['type'] ?? '') === 'array') ? [] : '');

                if (isset($result['problems'][$name])) {

                    $param['error'] = $result['problems'][$name];

                    if ($first) $param['attributes']['autofocus'] = 'autofocus';
                    $first = false;

                }

            }

            $params[$name] = $param;

        }

        return $params;

    }

    // Only what the browser checks the same way: never pattern, never required on a group, a hidden-by-condition field or an unfocusable control.
    protected function browser_params (string $class, array $rule) : array {

        $params = [];

        if (!empty($rule['required']) && empty($rule['when']) && $this->requirable($class)) $params['required'] = true;

        if (($rule['format'] ?? '') === 'email' && (is_a($class, Input::class, true)) && $this->default_type($class) === 'text') $params['type'] = 'email';

        if (isset($rule['maxLength'])) $params['attributes']['maxlength'] = (int) $rule['maxLength'];
        if (isset($rule['minLength'])) $params['attributes']['minlength'] = (int) $rule['minLength'];

        if (is_a($class, Number::class, true)) {

            if (isset($rule['minimum'])) $params['min'] = $rule['minimum'];
            if (isset($rule['maximum'])) $params['max'] = $rule['maximum'];

        }

        return $params;

    }

    protected function requirable (string $class) : bool {

        if ($this->is_custom_control($class)) return false;

        foreach (static::NOT_REQUIRABLE as $not) if (is_a($class, $not, true)) return false;
        foreach (static::REQUIRABLE as $can)     if (is_a($class, $can, true)) return true;

        return false;

    }

    // A subclass that draws its own control (Select_Nice, Date_Picker) hides the native one, which the browser cannot focus.
    protected function is_custom_control (string $class) : bool {

        return in_array($class, apply_filters('lattice.forms.unfocusable', ['Digitalis\\Field\\Select_Nice', 'Digitalis\\Field\\Date_Picker', 'Digitalis\\Field\\Date_Range']), true);

    }

    protected function default_type (string $class) : string {

        return (string) ($class::get_defaults()['type'] ?? 'text');

    }

    protected function field_class (string $name, array $fields) : ?string {

        foreach ($fields as $field) {

            $class = ($field instanceof View) ? get_class($field) : ($field['field'] ?? Input::class);

            if (is_a($class, Field_Group::class, true)) {

                if ($found = $this->field_class($name, (array) ($field['fields'] ?? []))) return $found;
                continue;

            }

            if (rtrim((string) ($field['name'] ?? $field['key'] ?? ''), '[]') === $name) return $class;

        }

        return null;

    }

    protected function summary (string $id, array $result, array $rules) : ?View {

        $problems = (array) ($result['problems'] ?? []);

        if (!$problems && empty($result['message'])) return null;

        $links = [];
        foreach (array_keys($problems) as $name) if (isset($rules[$name])) $links[$name] = "{$id}-{$name}";

        return new Form_Summary([
            'message'  => $problems ? 'Please check the answers marked below.' : $result['message'],
            'problems' => $problems,
            'links'    => $links,
        ]);

    }

    protected function merge_params (array $ours, array $theirs) : array {

        foreach ($theirs as $name => $param) $ours[$name] = array_merge($ours[$name] ?? [], (array) $param);

        return $ours;

    }

}
