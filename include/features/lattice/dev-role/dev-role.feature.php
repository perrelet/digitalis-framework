<?php

namespace Digitalis;

use WP_CLI;

require_once __DIR__ . '/dev-role-cli.php';

class Dev_Role extends Feature {

    protected $cap      = 'dev';
    protected $meta_key = 'is_dev';
    protected $command  = 'lattice dev';

    public function __construct () {

        $this->add_filter('user_has_cap', 'grant_cap', 10, 4);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command($this->command, new Dev_Role_CLI($this->meta_key));
        }

    }

    public function grant_cap ($caps, $req, $args, $wp_user) {

        if (User::get_instance($wp_user->ID)?->get_meta($this->meta_key)) {
            $caps[$this->cap] = true;
        }

        return $caps;

    }

}
