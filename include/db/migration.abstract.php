<?php

namespace Digitalis\DB;

abstract class Migration {

    abstract public function up (Schema_Context $ctx) : void;

}