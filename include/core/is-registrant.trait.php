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

}
