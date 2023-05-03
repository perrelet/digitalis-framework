<?php

namespace Digitalis;

trait Has_Integrations {

    use Can_Load;

    public function load_integrations ($path) {

        $this->autoload('integration', $path, 'integration.php', 'get_instance');

    }

    public function get_integration ($class_name) {

        return $this->get_object('integration', $class_name);

    }

    public function get_integrations () {

        return $this->get_object_group('integration');

    }

}