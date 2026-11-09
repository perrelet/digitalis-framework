<?php

namespace Digitalis;

use WP_CLI;
use WP_Application_Passwords;

class Credentials_Command {

    public static function register () : void {

        $command = new static;

        WP_CLI::add_command('lattice credentials list', [$command, 'list_scoped'], ['shortdesc' => 'Every scoped password, or one account\'s.', 'synopsis' => '[<user>]']);
        WP_CLI::add_command('lattice credentials show', [$command, 'show'], ['shortdesc' => 'A password\'s policy.', 'synopsis' => '<user> <uuid>']);
        WP_CLI::add_command('lattice credentials set', [$command, 'set'], ['shortdesc' => 'Give a password a policy: its own rules, a shared policy or both.', 'synopsis' => '<user> <uuid> [--input=<file>] [--policy=<name>] [--expires=<when>]']);
        WP_CLI::add_command('lattice credentials clear', [$command, 'clear'], ['shortdesc' => 'Take a password\'s policy away, leaving it a full password.', 'synopsis' => '<user> <uuid>']);
        WP_CLI::add_command('lattice credentials ask', [$command, 'ask'], ['shortdesc' => 'What a password\'s policy answers for one verb on one target.', 'synopsis' => '<user> <uuid> <on> <verb> [--fields=<fields>] [--facts=<json>] [--after=<json>] [--dry-run]']);
        WP_CLI::add_command('lattice credentials shared', [$command, 'shared'], ['shortdesc' => 'Shared policies: list them, show one, store one or delete one.', 'synopsis' => '[<name>] [--input=<file>] [--delete]']);

    }

    public function list_scoped ($args, $assoc) {

        $user = isset($args[0]) ? $this->find_user($args[0]) : null;

        $this->answer(array_values(array_filter(Credentials::inst()->get_scoped(), fn ($scoped) => !$user || ($scoped['user_id'] === $user->ID))));

    }

    public function show ($args, $assoc) {

        [$user, $uuid] = $this->find_password($args);

        $entry = Credentials::inst()->get_entry($user->ID, $uuid);

        [, $problems] = Credentials::inst()->read($entry);

        $this->answer(['entry' => $entry, 'problems' => is_null($entry) ? [] : $problems, 'expired' => $entry ? Credentials::inst()->is_expired($entry) : false]);

    }

    public function set ($args, $assoc) {

        [$user, $uuid] = $this->find_password($args);

        $entry = isset($assoc['input']) ? $this->read_json($assoc['input']) : (Credentials::inst()->get_entry($user->ID, $uuid) ?: ['version' => 1]);

        if (isset($assoc['policy'])) {

            [$ref, $problem] = Credentials::inst()->find_shared($assoc['policy']);

            if (!$ref) WP_CLI::error($problem ?? "No shared policy {$assoc['policy']}.");

            $entry['policy'] = $ref;

        }

        if (isset($assoc['expires'])) $entry['expires'] = $assoc['expires'];

        [$policies, $problems] = Credentials::inst()->read($entry);

        if (isset($entry['rules']) && $policies) array_push($problems, ...Credentials::inst()->get_unknown($policies[0]));

        if ($problems) WP_CLI::error(implode(' ', $problems));

        Credentials::inst()->set_entry($user->ID, $uuid, $entry);

        $this->answer(['user_id' => $user->ID, 'uuid' => $uuid, 'entry' => $entry]);

    }

    public function clear ($args, $assoc) {

        [$user, $uuid] = $this->find_password($args);

        Credentials::inst()->clear_entry($user->ID, $uuid);

        WP_CLI::success("{$uuid} is a full password again.");

    }

    public function ask ($args, $assoc) {

        [$user, $uuid] = $this->find_password($args);

        [$surface, $target] = array_pad(explode(':', $args[2], 2), 2, '');

        $entry = Credentials::inst()->get_entry($user->ID, $uuid);

        if (is_null($entry)) WP_CLI::error("{$uuid} holds no policy: it may do what its account may.");

        $request = array_filter([
            'surface' => $surface,
            'target'  => $target,
            'verb'    => $args[3],
            'fields'  => isset($assoc['fields']) ? explode(',', $assoc['fields']) : [],
            'facts'   => isset($assoc['facts']) ? $this->decode($assoc['facts'], 'facts') : [],
            'after'   => isset($assoc['after']) ? $this->decode($assoc['after'], 'after') : null,
            'dry_run' => !empty($assoc['dry-run']),
        ], fn ($value) => !is_null($value));

        $this->answer(['answer' => Credentials::inst()->answer($entry, $request), 'fields' => Credentials::inst()->answer_fields($entry, $request)]);

    }

    public function shared ($args, $assoc) {

        $api = Credentials::inst();

        if (!isset($args[0])) return $this->answer(['stored' => array_values($api->get_stored()), 'defined' => array_map(fn ($policy) => $policy ?: 'defined twice: unreadable', $api->get_defined())]);

        $name = $args[0];

        [$ref, $problem] = $api->find_shared($name);

        if (!empty($assoc['delete'])) {

            if (!$ref || !isset($api->get_stored()[$ref])) WP_CLI::error("No stored policy {$name}.");

            $api->delete_shared($ref);

            return WP_CLI::success("{$name} deleted: every password holding it now denies everything.");

        }

        if (!isset($assoc['input'])) return $this->answer(['ref' => $ref, 'problem' => $problem, 'policy' => $ref ? ($api->get_stored()[$ref]['policy'] ?? $api->get_defined()[$ref]) : null]);

        if (array_key_exists($name, $api->get_defined())) WP_CLI::error("{$name} is defined in code.");

        $policy = new Credential_Policy($input = $this->read_json($assoc['input']));

        if ($problems = [...$policy->get_problems(), ...($policy->get_problems() ? [] : $api->get_unknown($policy))]) WP_CLI::error(implode(' ', $problems));

        $this->answer(['id' => $api->store_shared($name, $input), 'name' => $name]);

    }

    protected function find_user (string $who) : \WP_User {

        $wp_user = get_user_by(is_numeric($who) ? 'id' : (is_email($who) ? 'email' : 'login'), $who);

        if (!$wp_user) WP_CLI::error("No account {$who}.");

        return $wp_user;

    }

    protected function find_password (array $args) : array {

        $wp_user = $this->find_user($args[0]);

        if (!WP_Application_Passwords::get_user_application_password($wp_user->ID, $args[1])) WP_CLI::error("{$wp_user->user_login} has no application password {$args[1]}.");

        return [$wp_user, $args[1]];

    }

    protected function decode (string $json, string $flag) : array {

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) WP_CLI::error("--{$flag} is not a JSON object.");

        return $decoded;

    }

    protected function read_json (string $file) : array {

        $json = json_decode((string) file_get_contents($file === '-' ? 'php://stdin' : $file), true);

        if (!is_array($json)) WP_CLI::error('--input is not a JSON object.');

        return $json;

    }

    protected function answer ($result) : void {

        WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    }

}
