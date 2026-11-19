<?php

declare(strict_types=1);

namespace Lattice;

abstract class Registrant extends Factory {

    use Is_Registrant;

    public function __construct () {}

    public function run () {}

}
