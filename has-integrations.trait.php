<?php

namespace Digitalis;

trait Has_Integrations {

    protected $integrations = [];

    public function load_integrations ($path) {

        foreach (glob($path . '/*.integration.php') as $integration_path) {

            include $integration_path;
            $integration_class = end(get_declared_classes());
		    $this->integrations[$integration_class] = new $integration_class();

        }

    }

    public function get_integration ($class_name) {

        return isset($this->integrations[$class_name]) ? $this->integrations[$class_name] : null;

    }

    public function get_integrations () {

        return $this->integrations;

    }

}