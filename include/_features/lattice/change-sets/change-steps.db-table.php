<?php

namespace Lattice;

use Lattice\DB\Table;
use Lattice\DB\Column;
use Lattice\DB\Index;

class Change_Steps_Table extends Table {

    protected static $slug      = 'lattice_change_steps';
    protected static $base_name = 'lattice_change_steps';
    protected static $scope     = self::SCOPE_SITE;

    public static function get_columns () : array {

        return [
            new Column('step_id',      'BIGINT(20) UNSIGNED', auto_increment: true),
            new Column('set_id',       'BIGINT(20) UNSIGNED'),
            new Column('position',     'SMALLINT(5) UNSIGNED'),
            new Column('op',           'VARCHAR(16)'),
            new Column('resource',     'VARCHAR(64)'),
            new Column('record_id',    'VARCHAR(64)', nullable: true),
            new Column('action',       'VARCHAR(32)', nullable: true),
            new Column('input',        'LONGTEXT', nullable: true),
            new Column('proposed',     'LONGTEXT', nullable: true),
            new Column('expected',     'LONGTEXT', nullable: true),
            new Column('removed',      'LONGTEXT', nullable: true),
            new Column('workspace_id', 'BIGINT(20) UNSIGNED', nullable: true),
            new Column('wait',         'TINYINT(1)', default: 0),
            new Column('marker',       'VARCHAR(16)', default: ''),
            new Column('marked_at',    'DATETIME', nullable: true),
            new Column('journal_id',   'BIGINT(20) UNSIGNED', nullable: true),
            new Column('error',        'TEXT', nullable: true),
        ];

    }

    public static function get_indexes () : array {

        return [
            Index::primary(['step_id']),
            Index::index('set_position', ['set_id', 'position']),
            Index::index('touching', ['resource', 'record_id', 'marker']),
        ];

    }

    public static function create_sql (\wpdb $wpdb) : string {

        return rtrim(parent::create_sql($wpdb), ';') . ' ENGINE=InnoDB;';

    }

}
