<?php

declare(strict_types=1);

namespace Lattice;

final class Framework extends App {

    protected function get_boot_priority () : int {

        return doing_action('plugins_loaded') ? parent::get_boot_priority() : 5;

    }

    public function load () {

        Model_Record::follow_deletes();

        Query_Manager::get_instance();
        Task_Handler::get_instance();

    }

}
