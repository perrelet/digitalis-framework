<?php

namespace Digitalis;

abstract class User {

    protected $id;
    protected $wp_user;

    public function __construct ($user_id = null) {

        if (is_null($user_id)) $user_id = get_current_user_id();

        $this->id = $user_id;

        $this->init();

    }

    public function init () {}

    public function get_id () {

        return $this->id;

    }

    public function get_wp_user () {

        if (is_null($this->wp_user)) $this->wp_user = get_user_by('id', $this->id);

        return $this->wp_user;

    }

}