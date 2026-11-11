<?php

namespace Digitalis;

use WP_User;

class Account extends User {

    use Has_Version, Has_Unsaved_Copies;

    const COLUMNS = ['user_login', 'user_email', 'display_name', 'user_url'];
    const META    = ['first_name', 'last_name', 'nickname', 'description'];

    protected array $staged = [];

    public function stage (string $key, mixed $value) : void {

        $this->staged[$key] = $value;

    }

    public function has_staged (string $key) : bool {

        return array_key_exists($key, $this->staged);

    }

    public function get_staged (string $key, mixed $default = null) : mixed {

        return $this->has_staged($key) ? $this->staged[$key] : $default;

    }

    public function get_column (string $column) : string {

        return (string) $this->get_staged($column, $this->get_wp_user()?->$column ?? '');

    }

    public function get_meta_value (string $key) : string {

        return (string) $this->get_staged($key, $this->get_id() ? get_user_meta($this->get_id(), $key, true) : '');

    }

    public function get_account_roles () : array {

        return array_values((array) $this->get_staged('roles', $this->get_wp_user()?->roles ?? []));

    }

    // set_role('') leaves no role.
    public function save ($user_data = []) {

        try {

            return $this->save_staged();

        } finally {

            $this->staged = [];

        }

    }

    protected function save_staged () {

        $update = array_intersect_key($this->staged, array_flip([...array_diff(static::COLUMNS, ['user_login']), ...static::META]));

        if ($update && is_wp_error($saved = wp_update_user(['ID' => $this->get_id()] + $update))) return $saved;

        if ($this->has_staged('roles')) {

            $roles   = array_values((array) $this->staged['roles']);
            $wp_user = new WP_User($this->get_id());

            if ($roles !== array_values($wp_user->roles)) {

                $wp_user->set_role($roles[0] ?? '');

                foreach (array_slice($roles, 1) as $role) $wp_user->add_role($role);

            }

        }

        clean_user_cache($this->get_id());

        $this->set_wp_user(get_user_by('id', $this->get_id()));

        return true;

    }

}
