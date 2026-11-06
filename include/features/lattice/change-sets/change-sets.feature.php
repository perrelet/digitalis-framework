<?php

namespace Digitalis;

require_once __DIR__ . '/change-sets.db-table.php';
require_once __DIR__ . '/change-steps.db-table.php';
require_once __DIR__ . '/v1-create-change-sets.migration.php';
require_once __DIR__ . '/change-sets.schema.php';

class Lattice_Change_Sets extends Feature {

    protected static $requires = ['lattice/resources'];

    public function run () {

        Change_Sets_Schema::ensure();

        // A row written before the journal is current loses its marks for good.
        if (!Change_Sets_Schema::is_current() || !Journal_Schema::is_current()) return;

        // Here and not above: a feature's file is included before its requirements load, and this implements one of theirs.
        require_once __DIR__ . '/change-set.model.php';

    }

}
