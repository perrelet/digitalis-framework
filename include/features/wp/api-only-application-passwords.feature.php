<?php

namespace Digitalis;

// wp_signon() fires wp_authenticate; the API's own Basic authentication never does.
class API_Only_Application_Passwords extends Feature {

    public function get_hooks () {

        return [
            'application_password_is_api_request' => ['refuse_sign_in', PHP_INT_MAX],
        ];

    }

    public function refuse_sign_in ($is_api_request) {

        return $is_api_request && !did_action('wp_authenticate');

    }

}
