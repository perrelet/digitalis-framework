<?php

namespace Lattice;

final class Change_Sets_Schema extends DB\Schema {

    protected static $slug    = 'lattice_change_sets';
    protected static $version = 2;

    public static function get_tables () : array {

        return [Change_Sets_Table::class, Change_Steps_Table::class];

    }

    public static function get_migrations () : array {

        return [1 => V1_Create_Change_Sets::class, 2 => V2_Change_Sets_Credential::class];

    }

}
