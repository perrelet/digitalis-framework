<?php

define('DIGITALIS_FRAMEWORK_VERSION', LATTICE_VERSION);
define('DIGITALIS_FRAMEWORK_PATH',    LATTICE_PATH);
define('DIGITALIS_LIBRARY_PATH',      LATTICE_LIBRARY_PATH);
define('DIGITALIS_FRAMEWORK_URI',     LATTICE_URI);

// Traits cannot be aliased; consumers using framework traits must update the namespace.
spl_autoload_register(function ($name) {

    $sub_namespaces = ['Component', 'Field', 'DB', 'WP', 'Woo', 'ACF', 'Oxygen'];

    if (str_starts_with($name, 'Digitalis\\')) {

        $short = substr($name, 10);

    } elseif (str_starts_with($name, 'Lattice\\') && !str_contains(substr($name, 8), '\\')) {

        $short = substr($name, 8);

    } else {

        return;

    }

    $candidates = ["Lattice\\{$short}"];

    foreach ($sub_namespaces as $sub) $candidates[] = "Lattice\\{$sub}\\{$short}";

    foreach ($candidates as $target) {

        if ($target === $name) continue;

        if (class_exists($target) || interface_exists($target)) {

            class_alias($target, $name);
            return;

        }

    }

});
