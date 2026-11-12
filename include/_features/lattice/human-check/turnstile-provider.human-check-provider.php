<?php

declare(strict_types=1);

namespace Lattice;

class Turnstile_Provider extends Human_Check_Provider {

    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    const UNAVAILABLE_CODES = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

    public function get_script_url () : string {

        return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

    }

    public function get_adapter_url () : string {

        return LATTICE_URI . 'assets/js/human-check-turnstile.js?ver=' . LATTICE_VERSION;

    }

    public function get_widget_params (string $action, array $keys) : array {

        return [
            'sitekey'             => (string) ($keys['site_key'] ?? ''),
            'action'              => $action,
            'refresh-expired'     => 'auto',
            'execution'           => 'render',
            'response-field-name' => $this->get_token_field(),
        ];

    }

    public function get_token_field () : string {

        return 'cf-turnstile-response';

    }

    public function verify (string $token, string $secret) : array {

        $response = wp_remote_post(static::VERIFY_URL, ['timeout' => $this->get_timeout(), 'body' => ['secret' => $secret, 'response' => $token]]);

        if (is_wp_error($response) || (wp_remote_retrieve_response_code($response) !== 200)) return $this->answer(static::UNAVAILABLE, ['unreachable']);

        $body  = json_decode(wp_remote_retrieve_body($response), true);
        $codes = (array) ($body['error-codes'] ?? []);

        if (!is_array($body))                                return $this->answer(static::UNAVAILABLE, ['unreadable']);
        if (!is_string($body['action'] ?? '') || !is_string($body['hostname'] ?? '')) return $this->answer(static::UNAVAILABLE, ['unreadable']);
        if (!empty($body['success']))                       return $this->answer(static::PASSED, $codes, $body['action'] ?? null, $body['hostname'] ?? null);
        if (array_intersect($codes, static::UNAVAILABLE_CODES)) return $this->answer(static::UNAVAILABLE, $codes);

        return $this->answer(static::FAILED, $codes);

    }

    public function get_hosts () : array {

        return ['challenges.cloudflare.com'];

    }

}
