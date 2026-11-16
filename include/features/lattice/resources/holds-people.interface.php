<?php

namespace Digitalis;

// RESOURCES.md, People.
interface Holds_People {

    // An empty address reaches none.
    public function find_ids_by_email (string $email) : array;

}
