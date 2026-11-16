<?php

namespace Digitalis;

// RESOURCES.md, People.
interface Holds_People {

    // The records this address reaches; an empty address reaches none.
    public function find_ids_by_email (string $email) : array;

}
