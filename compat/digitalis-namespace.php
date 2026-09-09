<?php

define('DIGITALIS_FRAMEWORK_VERSION', LATTICE_VERSION);
define('DIGITALIS_FRAMEWORK_PATH',    LATTICE_PATH);
define('DIGITALIS_LIBRARY_PATH',      LATTICE_LIBRARY_PATH);
define('DIGITALIS_FRAMEWORK_URI',     LATTICE_URI);

// Traits cannot be aliased; consumers using framework traits must update the namespace.
spl_autoload_register(function ($name) {

    if (!str_starts_with($name, 'Digitalis\\')) return;

    $target = 'Lattice\\' . substr($name, 10);

    if (class_exists($target) || interface_exists($target)) class_alias($target, $name);

});
