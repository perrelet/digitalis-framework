<?php

namespace Digitalis;

use Digitalis\DB\Migration;
use Digitalis\DB\Schema_Context;

// A site made after this column joined the table has it from version 1.
final class V4_Journal_Credential_Name extends Migration {

    public function up (Schema_Context $ctx) : void {

        $table = $ctx->get_table_name(Journal_Table::class);

        if (!$ctx->column_exists($table, 'credential_name')) $ctx->query("ALTER TABLE `{$table}` ADD COLUMN `credential_name` VARCHAR(255) NULL");

    }

}
