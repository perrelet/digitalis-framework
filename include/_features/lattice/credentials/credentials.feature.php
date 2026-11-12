<?php

namespace Lattice;

use WP_Error;

require_once __DIR__ . '/credential-policy.class.php';
require_once __DIR__ . '/credentials.service.php';
require_once __DIR__ . '/credential-stop.class.php';
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
            'map_meta_cap'                               => ['limit_meta_caps', PHP_INT_MAX],
            'editable_roles'                             => ['limit_roles', PHP_INT_MAX],
            // The storage stage runs before every other listener and stops the request, so nothing else acts on a change it refuses.
            'add_user_metadata'                          => ['guard_meta', PHP_INT_MIN],
            'update_user_metadata'                       => ['guard_meta', PHP_INT_MIN],
            'delete_user_metadata'                       => ['guard_meta', PHP_INT_MIN],
            'update_user_metadata_by_mid'                => ['guard_meta_by_mid', PHP_INT_MIN],
            'delete_user_metadata_by_mid'                => ['guard_meta_by_mid', PHP_INT_MIN],
            'password_reset'                             => ['guard_reset', PHP_INT_MIN],
            'pre_update_option'                          => ['guard_option', PHP_INT_MIN],
            'add_option'                                 => ['guard_added_option', PHP_INT_MIN],
            'delete_option'                              => ['guard_added_option', PHP_INT_MIN],
            'wp_pre_insert_user_data'                    => ['guard_user_data', PHP_INT_MIN],
            'wp_set_password'                            => ['guard_password', PHP_INT_MIN],
            'delete_user'                                => ['guard_delete', PHP_INT_MIN],
            'allow_password_reset'                       => ['refuse_reset', PHP_INT_MAX],
            'wp_ability_permission_result'               => ['gate_ability', PHP_INT_MAX],
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
        // Multisite gives a super admin everything before any capability filter runs, so a scoped password is not offered there yet.
        if (!$reason && is_multisite()) $reason = 'lattice_credential_multisite';
        if (!$reason && !Credentials::inst()->may_reach($entry, [$this->get_path()], $this->get_method())) $reason = 'lattice_credential';

        if ($reason) $error->add('forbidden', 'This password may not do that.', ['status' => 403, 'reason' => $reason]);

    }

    public function note_password ($wp_user, $item) {

        Credentials::inst()->note_current((int) $wp_user->ID, (string) ($item['uuid'] ?? ''), (string) ($item['name'] ?? ''));

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

    // Capabilities: a scoped request is only ever narrowed here. Until accounts are a resource (S6a) a scoped credential changes no account at all.

    // Every capability, primitive ones asked with an id included (WooCommerce asks delete_users), passes through map_meta_cap, and WordPress maps the application password ones to edit_user.
    const ACCOUNT_CAPS = ['edit_user', 'promote_user', 'delete_user', 'remove_user', 'add_users', 'create_users', 'edit_users', 'promote_users', 'delete_users', 'remove_users'];

    public function limit_meta_caps ($caps, $cap, $user_id, $args) {

        if (!($current = Credentials::inst()->get_current()) || ((int) $user_id !== $current['user_id'])) return $caps;

        // Only the layer's own check of an account resource (A11).
        if (in_array($cap, static::ACCOUNT_CAPS, true)) return $this->is_layer_checking() ? $caps : ['do_not_allow'];

        // A read bar holds for code that checks capabilities, outside the layer, which asks the policy itself.
        if (($cap === 'read_post') && class_exists(Resources::class) && !Resources::inst()->is_checking() && ($post = get_post($args[0] ?? null)) && ($resource = Resources::inst()->get_resource_for_post_type($post->post_type)) && !Resources::inst()->credential_reads($resource, $resource->find($post->ID))) return ['do_not_allow'];

        return $caps;

    }

    public function limit_roles ($roles) {

        return (Credentials::inst()->get_current() && !$this->is_layer_checking()) ? [] : $roles;

    }

    // Storage: a backstop for code that changes an account without asking a capability.

    public function guard_meta ($check, $user_id, $key, $value) {

        global $wpdb;

        if (!Credentials::inst()->get_current()) return $check;

        // MySQL matches a meta key ignoring case, trailing spaces and accents, so a key outside plain ASCII could reach a guarded one.
        $key = strtolower(rtrim((string) $key));

        if (!$this->stopped && ($account = $this->find_account_write((int) $user_id)) && $this->permits_meta($account, $key, $value)) return $check;

        $guarded = !preg_match('/^[\x20-\x7e]*$/', $key) || in_array($key, array_map('strtolower', ['_application_passwords', 'session_tokens', Credentials::META, Credentials::MADE_BY, $wpdb->get_blog_prefix() . 'capabilities', $wpdb->get_blog_prefix() . 'user_level']), true) || str_starts_with($key, '_two_factor_');

        return $guarded ? $this->stop('This password may not change an account, a credential or a session.') : $check;

    }

    // By its row, the key it holds and, on an update, the key it would take.
    public function guard_meta_by_mid ($check, $meta_id, $value = null, $key = null) {

        if (!Credentials::inst()->get_current()) return $check;

        $row = get_metadata_by_mid('user', (int) $meta_id);

        foreach (array_filter([(string) ($row->meta_key ?? ''), (string) $key]) as $each) $this->guard_meta($check, (int) ($row->user_id ?? 0), $each, $value);

        return $check;

    }

    // Fired with the new password before it is written, and a user sync listens here.
    public function guard_reset ($wp_user, $password) {

        if (Credentials::inst()->get_current()) $this->stop('This password may not reset a password.');

    }

    // Role definitions are every holder's account, and shared policies are policies (S6). An option's name is matched as MySQL matches it.
    protected function is_guarded_option (string $option) : bool {

        return in_array(strtolower(trim($option)), [strtolower($GLOBALS['wpdb']->prefix . 'user_roles'), strtolower(Credentials::SHARED)], true);

    }

    public function guard_option ($value, $option, $old) {

        return (Credentials::inst()->get_current() && $this->is_guarded_option((string) $option) && ($value !== $old)) ? $this->stop('This password may not change roles or policies.') : $value;

    }

    public function guard_added_option ($option) {

        if (Credentials::inst()->get_current() && $this->is_guarded_option((string) $option)) $this->stop('This password may not change roles or policies.');

    }

    public function guard_user_data ($data, $update, $user_id, $userdata) {

        if (!Credentials::inst()->get_current()) return $data;

        $account = $this->stopped ? null : $this->find_account_write($update ? (int) $user_id : 0, $update ? null : (string) ($data['user_login'] ?? ''));

        if (!$update) return ($account && (($data['user_login'] ?? '') === $account->get_column('user_login'))) ? $data : $this->stop('This password may not make an account.');

        $stored = get_userdata((int) $user_id);

        $email_allowed = $account && $account->has_staged('user_email') && (($data['user_email'] ?? '') === $account->get_column('user_email'));
        $reset_allowed = $account && $this->is_sending_link($account);

        // A reset key set by its caller is a known reset link.
        if ($stored && ((!$email_allowed && (($data['user_email'] ?? $stored->user_email) !== $stored->user_email)) || (isset($data['user_pass']) && ($data['user_pass'] !== $stored->user_pass)) || (!$reset_allowed && !empty($data['user_activation_key']) && ($data['user_activation_key'] !== $stored->user_activation_key)))) $this->stop('This password may not change an account\'s email, password or reset key.');

        return $data;

    }

    // wp_set_password() writes before it tells anyone, so the old hash goes back before any other listener hears of it.
    public function guard_password ($password, $user_id, $old_user_data) {

        global $wpdb;

        if (!Credentials::inst()->get_current() || !$old_user_data) return;

        // wp_insert_user() sets the new account's password through here.
        if (!$this->stopped && ($account = $this->find_account_write((int) $user_id)) && $account->is_unsaved()) return;

        $wpdb->update($wpdb->users, ['user_pass' => $old_user_data->user_pass, 'user_activation_key' => $old_user_data->user_activation_key], ['ID' => (int) $user_id]);

        clean_user_cache((int) $user_id);

        $this->stop('This password may not change a password.');

    }

    public function guard_delete ($user_id, $reassign) {

        if (!Credentials::inst()->get_current()) return;

        if (!$this->stopped && ($account = $this->find_account_write((int) $user_id)) && $account->get_staged('delete') && ((int) $reassign === (int) $account->get_staged('reassign'))) return;

        $this->stop('This password may not delete an account.');

    }

    public function refuse_reset ($allow, $user_id) {

        if (!Credentials::inst()->get_current()) return $allow;

        return (!$this->stopped && ($account = $this->find_account_write((int) $user_id)) && $this->is_sending_link($account)) ? $allow : false;

    }

    protected bool $stopped = false;

    protected function is_layer_checking () : bool {

        return class_exists(Resources::class) && Resources::inst()->is_checking_accounts();

    }

    protected function is_sending_link ($account) : bool {

        return (bool) ($account->get_staged('notify') || $account->get_staged('reset'));

    }

    protected function find_account_write (int $user_id, ?string $login = null) : ?object {

        return class_exists(Resources::class) ? Resources::inst()->get_account_write($user_id, $login) : null;

    }

    protected function permits_meta ($account, string $key, $value) : bool {

        global $wpdb;

        $prefix = strtolower($wpdb->get_blog_prefix());

        // The one case S6 allows (A7).
        if ($account->get_staged('delete')) return true;

        if ($key === "{$prefix}capabilities") return !array_diff(array_keys(array_filter((array) maybe_unserialize($value))), $account->get_account_roles());

        return in_array($key, ["{$prefix}user_level", strtolower(Credentials::MADE_BY), strtolower(Resources::SET_KEY)], true);

    }

    protected function stop (string $message) : never {

        $this->stopped = true;

        if (class_exists(Resources::class) && Resources::inst()->is_writing()) throw new Credential_Stop($message);

        if (defined('REST_REQUEST') && REST_REQUEST) wp_send_json(['code' => 'forbidden', 'message' => $message, 'data' => ['status' => 403, 'reason' => 'lattice_credential']], 403);

        wp_die(esc_html($message), '', ['response' => 403]);

    }

    public function gate_ability ($result, $name, $input, $ability) {

        if (!($current = Credentials::inst()->get_current()) || ($result !== true)) return $result;

        $answer = Credentials::inst()->answer($current['entry'], ['surface' => 'ability', 'target' => (string) $name, 'verb' => 'run', 'facts' => ['category' => $ability->get_category()]]);

        return ($answer === 'allow') ? $result : new WP_Error('forbidden', 'This password may not run that.', ['status' => 403, 'reason' => 'lattice_credential']);

    }

}
