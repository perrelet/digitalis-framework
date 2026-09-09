<?php

// Redirect WordPress system emails (recovery mode, auto-updates) to a custom address.

namespace Lattice\WP;

class System_Emails extends \Lattice\Feature {

    protected $address = '';

    protected $hooks = [
        'recovery_mode_email',
        'auto_core_update_email',
        'automatic_updates_debug_email',
    ];

    public function __construct () {

        if (!$this->address) return;

        foreach ($this->hooks as $hook) {
            $this->add_filter($hook, 'redirect');
        }

    }

    public function redirect ($email) {

        $email['to'] = $this->address;

        return $email;

    }

}
