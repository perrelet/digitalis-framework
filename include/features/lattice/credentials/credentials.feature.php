<?php

namespace Digitalis;

use WP_Error;

require_once __DIR__ . '/credential-policy.class.php';
require_once __DIR__ . '/credentials.service.php';
require_once __DIR__ . '/credentials-command.class.php';

class Lattice_Credentials extends Feature {

    const PRESETS = [
        'read_only'    => ['version' => 1, 'rules' => [['verbs' => 'reads', 'on' => 'resource:*', 'answer' => 'allow']]],
        'propose_only' => ['version' => 1, 'rules' => [['verbs' => 'reads', 'on' => 'resource:*', 'answer' => 'allow'], ['verbs' => 'writes', 'on' => 'resource:*', 'answer' => 'review']]],
    ];

    public function get_hooks () {

        return [
            'cli_init'                                   => 'register_commands',
            'wp_delete_application_password'             => 'forget',
            'wp_authenticate_application_password_errors' => ['refuse_password', 10],
            'application_password_did_authenticate'     => ['note_password', 10],
            'rest_request_before_callbacks'              => ['gate_dispatch', PHP_INT_MAX],
            'rest_pre_dispatch'                          => ['gate_answer', PHP_INT_MAX],
            'send_auth_cookies'                          => ['refuse_session', PHP_INT_MAX],
        ];

    }

    public function run () {

        foreach (static::PRESETS as $name => $policy) Credentials::inst()->define($name, $policy);

    }

    public function register_commands () {

        Credentials_Command::register();

    }

    public function forget ($user_id, $item) {

        Credentials::inst()->clear_entry((int) $user_id, (string) ($item['uuid'] ?? ''));

    }

    // Authenticating

    // A refusal here leaves the request with nobody, before any plugin code sees the account.
    public function refuse_password ($error, $wp_user, $item, $password) {

        $entry = Credentials::inst()->get_entry((int) $wp_user->ID, (string) ($item['uuid'] ?? ''));

        if (is_null($entry)) return;

        $reason = Credentials::inst()->refuse_entry($entry);

        if (!$reason && !(defined('REST_REQUEST') && REST_REQUEST)) $reason = 'lattice_credential_api_only';
        if (!$reason && !Credentials::inst()->may_reach($entry, [$this->get_path()], $this->get_method())) $reason = 'lattice_credential';

        if ($reason) $error->add('forbidden', 'This password may not do that.', ['status' => 403, 'reason' => $reason]);

    }

    public function note_password ($wp_user, $item) {

        Credentials::inst()->note_current((int) $wp_user->ID, (string) ($item['uuid'] ?? ''));

    }

    // As serve_request() reads it.
    protected function get_path () : string {

        return untrailingslashit((string) ($GLOBALS['wp']->query_vars['rest_route'] ?? '')) ?: '/';

    }

    // WordPress takes the method from _method, then the override header, so the gate reads it the same way.
    protected function get_method () : string {

        return strtoupper((string) ($_GET['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET'));

    }

    // Dispatching

    public function gate_dispatch ($response, $handler, $request) {

        return $this->refuse_route($request) ?? $response;

    }

    // An answer given before dispatch (a cache, a plugin answering early, core's OPTIONS) never reaches the gate above. Here and not on rest_post_dispatch, which internal dispatches never see.
    public function gate_answer ($result, $server, $request) {

        return empty($result) ? $result : ($this->refuse_route($request) ?? $result);

    }

    public function refuse_session ($send) {

        return Credentials::inst()->get_current() ? false : $send;

    }

    // Decided on the path and every pattern WordPress could match it to, so a path reaching a denied handler is refused however it is spelt.
    protected function refuse_route ($request) : ?WP_Error {

        if (!$current = Credentials::inst()->get_current()) return null;

        $path     = $request->get_route();
        $patterns = array_filter(array_keys(rest_get_server()->get_routes()), fn ($pattern) => preg_match('@^' . $pattern . '$@i', $path));

        if (Credentials::inst()->may_reach($current['entry'], [$path, ...$patterns], $request->get_method())) return null;

        return new WP_Error('forbidden', 'This password may not do that.', ['status' => 403, 'reason' => 'lattice_credential']);

    }

}
