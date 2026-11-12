<?php

declare(strict_types=1);

namespace Lattice;

use Lattice\DB\Migration;
use Lattice\DB\Schema_Context;

// A site made after this column joined the table has it from version 1.
final class V3_Journal_Credential extends Migration {

    public function up (Schema_Context $ctx) : void {

        $table = $ctx->get_table_name(Journal_Table::class);

        if (!$ctx->column_exists($table, 'credential')) $ctx->query("ALTER TABLE `{$table}` ADD COLUMN `credential` VARCHAR(36) NULL");

    }

}
