<?php

namespace Lattice;

use Lattice\DB\Migration;
use Lattice\DB\Schema_Context;

final class V1_Create_Journal extends Migration {

    public function up (Schema_Context $ctx) : void {

        if (!$ctx->registered_table_exists(Journal_Table::class)) $ctx->query($ctx->create_table_sql(Journal_Table::class));

    }

}
