<?php

namespace Lattice;

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

    // The guest customer (id 0) has no fields.
    public function get_field_id () {

        return $this->get_id() === 0 ? null : parent::get_field_id();

    }

}