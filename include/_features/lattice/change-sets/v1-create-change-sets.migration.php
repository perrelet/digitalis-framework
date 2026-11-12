<?php

namespace Lattice;

use Lattice\DB\Migration;
use Lattice\DB\Schema_Context;

final class V1_Create_Change_Sets extends Migration {

    public function up (Schema_Context $ctx) : void {

        foreach ([Change_Sets_Table::class, Change_Steps_Table::class] as $table) if (!$ctx->registered_table_exists($table)) $ctx->query($ctx->create_table_sql($table));

    }

}
