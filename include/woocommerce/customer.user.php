<?php

namespace Digitalis;

class Customer extends User {

    use Is_Woo_Customer;

    public static function get_global_id () {

        return get_current_user_id();

    }

    public static function validate_id ($id) {

        if ($id === 0) return true;

        return parent::validate_id($id);

    }

    //

    public function get_acf_id () {

        return ($this->is_new() || ($this->get_id() === 0)) ? null : 'user_' . $this->wp_user->ID;

    }

}