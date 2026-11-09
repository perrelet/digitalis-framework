<?php

namespace Digitalis;

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
            'cli_init'                        => 'register_commands',
            'wp_delete_application_password' => 'forget',
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

}
