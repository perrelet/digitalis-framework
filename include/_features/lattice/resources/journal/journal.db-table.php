<?php

declare(strict_types=1);

namespace Lattice;

use Lattice\DB\Table;
use Lattice\DB\Column;
use Lattice\DB\Index;

class Journal_Table extends Table {

    protected static $slug      = 'lattice_journal';
    protected static $base_name = 'lattice_journal';
    protected static $scope     = self::SCOPE_SITE;

    public static function get_columns () : array {

        return [
            new Column('id',             'BIGINT(20) UNSIGNED', auto_increment: true),
            new Column('at',             'DATETIME'),
            new Column('user_id',        'BIGINT(20) UNSIGNED'),
            new Column('via',            'VARCHAR(8)'),
            new Column('resource',       'VARCHAR(64)'),
            new Column('record_id',      'VARCHAR(64)'),
            new Column('action',         'VARCHAR(32)'),
            new Column('why',            'TEXT'),
            new Column('before',         'LONGTEXT', nullable: true),
            new Column('after',          'LONGTEXT', nullable: true),
            new Column('version_before', 'INT(10) UNSIGNED'),
            new Column('version_after',  'INT(10) UNSIGNED'),
            new Column('undoes',         'BIGINT(20) UNSIGNED', nullable: true),
        ];

    }

    public static function get_indexes () : array {

        return [
            Index::primary(['id']),
            Index::index('record', ['resource', 'record_id', 'id']),
            Index::index('at', ['at']),
        ];

    }

}
