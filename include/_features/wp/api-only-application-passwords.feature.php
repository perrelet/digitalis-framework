<?php

declare(strict_types=1);

namespace Lattice;

// wp_signon() fires wp_authenticate; the API's own Basic authentication never does.
class API_Only_Application_Passwords extends Feature {

    const WRONG_CREDENTIALS = ['invalid_username', 'invalid_email', 'incorrect_password'];

    public function get_hooks () {

        return [
            'application_password_is_api_request' => ['refuse_sign_in', PHP_INT_MAX],
            'rest_authentication_errors'          => ['report_refusal', PHP_INT_MAX],
        ];

    }

    public function refuse_sign_in ($is_api_request) {

        return $is_api_request && !did_action('wp_authenticate');

    }

    // The API's Basic authentication fires no wp_login_failed, whose other listeners expect a login form (LearnDash redirects), so WP Fail2Ban Redux is told directly. Only a request REST refuses counts: another plugin's Basic keys fail here first and can still succeed.
    public function report_refusal ($result) {

        if (is_wp_error($result) && in_array($result->get_error_code(), static::WRONG_CREDENTIALS, true) && class_exists('WP_Fail2Ban_Redux')) \WP_Fail2Ban_Redux::get_instance()->wp_login_failed(sanitize_user((string) ($_SERVER['PHP_AUTH_USER'] ?? '')));

        return $result;

    }

}
