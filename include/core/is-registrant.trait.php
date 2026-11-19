<?php

declare(strict_types=1);

namespace Lattice;

trait Is_Registrant {

    use Has_WP_Hooks;

    public function get_hooks () {

        return [];

    }

    // A family adds its own hooks here and calls the parent; a site's class uses get_hooks() and run() (init() on a plain Singleton).
    protected function switch_on () : void {

        $this->add_hooks((array) $this->get_hooks());
        $this->run();

    }

    // Removes every hook added through this instance; what run() did otherwise stays.
    public function switch_off () : void {

        foreach ($this->added_hooks as [$hook_name, $callback, $priority]) remove_filter($hook_name, $callback, $priority);

        $this->added_hooks = [];

    }

}
