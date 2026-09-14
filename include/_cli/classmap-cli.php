<?php

namespace Lattice;

use WP_CLI;

class Classmap_CLI {

    // Writes what this boot autoloaded. Run it as the request type you want cached; a CLI run does not see `_admin`, which then tokenizes until you regenerate from admin.
    public function __invoke ($args, $assoc_args) {

        foreach (Classmap::all() as $classmap) WP_CLI::success('Wrote ' . $classmap->write());

    }

}
