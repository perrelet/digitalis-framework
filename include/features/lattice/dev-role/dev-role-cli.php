<?php

namespace Digitalis;

use WP_CLI;

class Dev_Role_CLI {

    protected $meta_key;

    public function __construct (string $meta_key) {

        $this->meta_key = $meta_key;

    }

    /**
     * Grant dev access to a user.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, login, or email.
     *
     * ## EXAMPLES
     *
     *     wp lattice dev grant 1
     *     wp lattice dev grant jamie
     *     wp lattice dev grant jamie@example.com
     */
    public function grant ($args, $assoc_args) {

        $user = $this->resolve($args[0]);
        $user->update_meta($this->meta_key, 1);

        WP_CLI::success(sprintf('Dev access granted to %s (#%d).', $user->get_username(), $user->get_id()));

    }

    /**
     * Revoke dev access from a user.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, login, or email.
     *
     * ## EXAMPLES
     *
     *     wp lattice dev revoke 1
     */
    public function revoke ($args, $assoc_args) {

        $user = $this->resolve($args[0]);
        $user->delete_meta($this->meta_key);

        WP_CLI::success(sprintf('Dev access revoked from %s (#%d).', $user->get_username(), $user->get_id()));

    }

    /**
     * List all users with dev access.
     *
     * ## EXAMPLES
     *
     *     wp lattice dev list
     */
    public function list ($args, $assoc_args) {

        $users = User::query([
            'meta_key'   => $this->meta_key,
            'meta_value' => 1,
            'number'     => -1,
        ]);

        if (!$users) {
            WP_CLI::log('No users have dev access.');
            return;
        }

        $rows = array_map(fn ($u) => [
            'ID'    => $u->get_id(),
            'login' => $u->get_username(),
            'email' => $u->get_email(),
        ], $users);

        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'login', 'email']);

    }

    protected function resolve (string $arg): User {

        if (is_numeric($arg))    $user = User::get_instance((int) $arg);
        elseif (is_email($arg))  $user = User::get_by_email($arg);
        else                     $user = User::get_by_login($arg);

        if (!$user) WP_CLI::error("User not found: $arg");

        return $user;

    }

}
