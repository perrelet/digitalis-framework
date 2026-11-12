<?php

namespace Lattice;

use Lattice\DB\Table;
use Lattice\DB\Column;
use Lattice\DB\Index;

class Change_Sets_Table extends Table {

    protected static $slug      = 'lattice_change_sets';
    protected static $base_name = 'lattice_change_sets';
    protected static $scope     = self::SCOPE_SITE;

    public static function get_columns () : array {

        return [
            new Column('set_id',       'BIGINT(20) UNSIGNED', auto_increment: true),
            new Column('author_id',    'BIGINT(20) UNSIGNED'),
            new Column('purpose',      'TEXT'),
            new Column('state',        'VARCHAR(16)'),
            new Column('version',      'INT(10) UNSIGNED', default: 0),
            new Column('note',         'TEXT', nullable: true),
            new Column('reason',       'TEXT', nullable: true),
            new Column('created_at',   'DATETIME'),
            new Column('updated_at',   'DATETIME'),
            new Column('submitted_at', 'DATETIME', nullable: true),
            new Column('credential',   'VARCHAR(36)', nullable: true),
        ];

    }

    public static function get_indexes () : array {

        return [
            Index::primary(['set_id']),
            Index::index('author_state', ['author_id', 'state']),
            Index::index('state_updated', ['state', 'updated_at']),
        ];

    }

    public static function create_sql (\wpdb $wpdb) : string {

        return rtrim(parent::create_sql($wpdb), ';') . ' ENGINE=InnoDB;';

    }

}
