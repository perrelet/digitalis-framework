<?php

namespace Lattice;

use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/human-check-provider.abstract.php';
require_once __DIR__ . '/turnstile-provider.human-check-provider.php';
require_once __DIR__ . '/human-check.field.php';

// docs/FORMS.md, "Proving a person"
class Lattice_Human_Check extends Feature {

    const KEYWORD    = 'human_check';
    const DOWN       = 'lattice_human_check_down';
    const DOWN_FOR   = 30;
    const CACHE      = 'lattice_human_check';
    const TOKEN_LIFE = 300;

    protected static $requires = ['lattice/forms'];

    protected $id = 'human-check';

    public function get_hooks () {

        return [
            'lattice.forms.rule' => 'add_rule',
        ];

    }

    public function add_rule ($rule, $name) {

        if (empty($rule[static::KEYWORD])) return $rule;

        $action   = (string) $rule[static::KEYWORD];
        $provider = $this->get_provider($action);

        $rule['costly']       = true;
        $rule['field_params'] = array_merge((array) ($rule['field_params'] ?? []), ['action' => $action]);

        // Unchecked without a provider or keys: its route then cannot serve (Form_Route::get_unavailable_reason()).
        if ($provider && $this->get_keys($provider) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $action)) $rule['validate_callback'] = fn ($value, WP_REST_Request $request) => $this->verify($action, (string) $request->get_param($provider->get_token_field()));

        return $rule;

    }

    public function get_provider (string $action) : ?Human_Check_Provider {

        $class = apply_filters('lattice.human_check.provider', Turnstile_Provider::class, $action);

        return (is_string($class) && is_subclass_of($class, Human_Check_Provider::class)) ? new $class() : null;

    }

    public function get_keys (Human_Check_Provider $provider) : array {

        $keys = (array) apply_filters('lattice.human_check.keys', [], get_class($provider));

        return (!empty($keys['site_key']) && !empty($keys['secret'])) ? $keys : [];

    }

    // Options add to the provider's params and never replace them: a provider pins what its flow needs.
    public function get_widget (string $action, array $options = []) : ?array {

        if (!($provider = $this->get_provider($action)) || !($keys = $this->get_keys($provider))) return null;

        unset($keys['secret']);

        $options = array_merge((array) apply_filters('lattice.human_check.widget', [], $action, get_class($provider)), $options);

        return [
            'script'  => $provider->get_script_url(),
            'adapter' => $provider->get_adapter_url(),
            'params'  => $provider->get_widget_params($action, $keys) + $options,
        ];

    }

    public function get_loader_url () : string {

        return LATTICE_URI . 'assets/js/human-check.js?ver=' . LATTICE_VERSION;

    }

    public function get_hostnames () : array {

        return array_map('strtolower', (array) apply_filters('lattice.human_check.hostnames', [wp_parse_url(home_url(), PHP_URL_HOST)]));

    }

    public function verify (string $action, string $token) {

        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $action)) {

            _doing_it_wrong(__METHOD__, 'A person check\'s action is 1 to 32 letters, digits, underscores or hyphens.', '');
            return $this->refuse_unavailable('a malformed action');

        }

        if (get_transient(static::DOWN)) return $this->refuse_unavailable();

        $provider = $this->get_provider($action);
        $keys     = $provider ? $this->get_keys($provider) : [];

        if (!$provider) return $this->refuse_unavailable('no provider');
        if (!$keys)     return $this->refuse_unavailable('no keys');

        if ($token === '') return $this->refuse_failed();

        // Turnstile takes a few seconds to spread a spent token.
        if (!wp_cache_add(hash('sha256', $token), 1, static::CACHE, static::TOKEN_LIFE)) return $this->refuse_failed();

        $result = $provider->verify($token, (string) $keys['secret']);

        if ($result['outcome'] === Human_Check_Provider::UNAVAILABLE) {

            set_transient(static::DOWN, 1, static::DOWN_FOR);
            return $this->refuse_unavailable(implode(', ', $result['codes']));

        }

        if ($result['outcome'] !== Human_Check_Provider::PASSED) return $this->refuse_failed();

        if (!is_null($result['action']) && ($result['action'] !== $action)) return $this->refuse_failed();
        if (!is_null($result['hostname']) && !in_array(strtolower($result['hostname']), $this->get_hostnames(), true)) return $this->refuse_failed();

        return true;

    }

    protected function refuse_failed () : WP_Error {

        return new WP_Error('human_check_failed', 'Please confirm you are a person, then send the form again.', ['status' => 400]);

    }

    protected function refuse_unavailable (string $why = '') : WP_Error {

        if ($why) error_log("Lattice human check unavailable: {$why}");

        return new WP_Error('human_check_unavailable', 'This form cannot be checked just now. Please try again in a minute.', ['status' => 503]);

    }

}
