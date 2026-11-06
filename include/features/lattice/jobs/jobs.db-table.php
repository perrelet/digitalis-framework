<?php

namespace Digitalis;

use Digitalis\DB\Table;
use Digitalis\DB\Column;
use Digitalis\DB\Index;

class Jobs_Table extends Table {

    protected static $slug      = 'lattice_jobs';
    protected static $base_name = 'lattice_jobs';
    protected static $scope     = self::SCOPE_SITE;

    public static function get_columns () : array {

        return [
            new Column('id',          'BIGINT(20) UNSIGNED', auto_increment: true),
            new Column('type',        'VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'),
            new Column('args',        'LONGTEXT'),
            new Column('job_key',     'VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', nullable: true),
            new Column('open',        'TINYINT(1)', nullable: true),
            new Column('state',       'VARCHAR(10)'),
            new Column('run_after',   'DATETIME'),
            new Column('expires_at',  'DATETIME', nullable: true),
            new Column('created_at',  'DATETIME'),
            new Column('ended_at',    'DATETIME', nullable: true),
            new Column('parked_at',   'DATETIME', nullable: true),
            new Column('attempts',    'INT(11)', default: 0),
            new Column('position',    'LONGTEXT', nullable: true),
            new Column('done',        'BIGINT(20) UNSIGNED', default: 0),
            new Column('total',       'BIGINT(20) UNSIGNED', nullable: true),
            new Column('lease_token', 'CHAR(16)', nullable: true),
            new Column('lease_until', 'DATETIME', nullable: true),
            new Column('cancel_why',  'TEXT', nullable: true),
            new Column('again',       'TINYINT(1)', default: 0),
            new Column('error',       'TEXT', nullable: true),
        ];

    }

    public static function get_indexes () : array {

        return [
            Index::primary(['id']),
            Index::unique('open_key', ['type', 'job_key', 'open']),
            Index::index('state_run', ['state', 'run_after']),
            Index::index('ended', ['ended_at']),
        ];

    }

    public static function create_sql (\wpdb $wpdb) : string {

        return rtrim(parent::create_sql($wpdb), ';') . ' ENGINE=InnoDB;';

    }

}
