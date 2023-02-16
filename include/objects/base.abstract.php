<?php

namespace Digitalis;

// Consider changing to has-identifier

abstract class Base {

    protected $identifier;

    protected function get_identifier () {

        if (is_null($this->identifier)) $this->identifier = strtolower(str_replace("_", "-", get_class($this)));
        return $this->identifier;

    }

}