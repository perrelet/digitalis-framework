<?php

namespace Digitalis;

use Digitalis\DB\Migration;
use Digitalis\DB\Schema_Context;

final class V1_Create_Jobs extends Migration {

    public function up (Schema_Context $ctx) : void {

        if (!$ctx->registered_table_exists(Jobs_Table::class)) $ctx->query($ctx->create_table_sql(Jobs_Table::class));

    }

}
