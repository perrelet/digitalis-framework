<?php

declare(strict_types=1);

namespace Lattice;

final class Journal_Schema extends DB\Schema {

    protected static $slug    = 'lattice_journal';
    protected static $version = 2;

    public static function get_tables () : array {

        return [Journal_Table::class];

    }

    public static function get_migrations () : array {

        return [1 => V1_Create_Journal::class, 2 => V2_Journal_Sets::class];

    }

}
