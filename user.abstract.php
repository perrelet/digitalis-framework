<?php

namespace Digitalis;

abstract class User {

    protected $id;

    public function __construct ($user_id = null) {

        if (is_null($user_id)) $user_id = get_current_user_id();

        $this->id = $user_id;

        $this->init();

    }

    public function init () {}

    public function get_id () {

        return $this->id;

    }

}