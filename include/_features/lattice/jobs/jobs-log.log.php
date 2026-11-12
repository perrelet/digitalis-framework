<?php

declare(strict_types=1);

namespace Lattice;

class Jobs_Log extends Log {

    protected $file = 'jobs.log';
    protected $name = 'Jobs';

    // Exception messages must not land in the web root.
    public function write ($text) {

        $directory = wp_normalize_path($this->get_directory());

        if (!path_is_absolute($directory) || ($directory === '/') || str_starts_with(trailingslashit($directory), wp_normalize_path(ABSPATH))) return error_log($text);

        return parent::write($text);

    }

}
