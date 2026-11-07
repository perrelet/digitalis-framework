<?php

declare(strict_types=1);

namespace Lattice;

require_once __DIR__ . '/dev-role.command.php';

class Dev_Role extends Feature {

    protected $cap      = 'dev';
    protected $meta_key = 'is_dev';

    public function __construct () {

        $this->add_filter('user_has_cap', 'grant_cap', 10);

    }

    public function get_hooks () {

        return ['cli_init' => 'register_commands'];

    }

    public function register_commands () {

        Dev_Role_Command::register($this->meta_key);

    }

    public function grant_cap ($caps, $req, $args, $wp_user) {

        if (User::get_instance($wp_user->ID)?->get_meta($this->meta_key)) {
            $caps[$this->cap] = true;
        }

        return $caps;

    }

}
