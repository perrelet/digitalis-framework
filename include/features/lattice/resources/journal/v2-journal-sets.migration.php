<?php

namespace Digitalis;

use Digitalis\DB\Migration;
use Digitalis\DB\Schema_Context;

// A site made after these columns joined the table has them from version 1.
final class V2_Journal_Sets extends Migration {

    public function up (Schema_Context $ctx) : void {

        $table = $ctx->get_table_name(Journal_Table::class);

        foreach (['set_key' => 'BIGINT(20) UNSIGNED NULL', 'set_step' => 'SMALLINT(5) UNSIGNED NULL', 'proposer_id' => 'BIGINT(20) UNSIGNED NULL'] as $column => $type) {

            if (!$ctx->column_exists($table, $column)) $ctx->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$type}");

        }

        foreach (['set_row' => '`set_key`, `id`', 'proposer_row' => '`proposer_id`, `id`'] as $index => $columns) {

            if (!$ctx->index_exists($table, $index)) $ctx->query("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");

        }

    }

}
