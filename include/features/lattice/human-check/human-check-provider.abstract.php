<?php

namespace Digitalis;

abstract class Human_Check_Provider {

    const PASSED      = 'passed';
    const FAILED      = 'failed';
    const UNAVAILABLE = 'unavailable';

    abstract public function get_script_url () : string;

    abstract public function get_adapter_url () : string;

    abstract public function get_widget_params (string $action, array $keys) : array;

    abstract public function get_token_field () : string;

    // ['outcome' => PASSED|FAILED|UNAVAILABLE, 'action' => ?string, 'hostname' => ?string, 'codes' => string[]]
    abstract public function verify (string $token, string $secret) : array;

    abstract public function get_hosts () : array;

    protected function get_timeout () : float {

        return (float) apply_filters('lattice.human_check.timeout', 3, $this);

    }

    protected function answer (string $outcome, array $codes = [], ?string $action = null, ?string $hostname = null) : array {

        return ['outcome' => $outcome, 'action' => $action, 'hostname' => $hostname, 'codes' => array_values($codes)];

    }

}
