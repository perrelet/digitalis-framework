<?php

declare(strict_types=1);

namespace Lattice;

abstract class Integration extends Singleton {

    protected function switch_on () : void {

        parent::switch_on();
        $this->run();

    }

    public function run () {}

}
