<?php

namespace Digitalis;

use Digitalis\DB\Migration;
use Digitalis\DB\Schema_Context;

// A site made after this column joined the table has it from version 1.
final class V2_Change_Sets_Credential extends Migration {

    public function up (Schema_Context $ctx) : void {

        $table = $ctx->get_table_name(Change_Sets_Table::class);

        if (!$ctx->column_exists($table, 'credential')) $ctx->query("ALTER TABLE `{$table}` ADD COLUMN `credential` VARCHAR(36) NULL");

    }

}
