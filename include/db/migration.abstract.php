<?php

declare(strict_types=1);

namespace Lattice\DB;

abstract class Migration {

    abstract public function up (Schema_Context $ctx) : void;

}