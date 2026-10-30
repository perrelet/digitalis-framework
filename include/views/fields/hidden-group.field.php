<?php

declare(strict_types=1);

namespace Lattice\Field;

class Hidden_Group extends \Lattice\Component\Field_Group {

    protected static $defaults = [
        'data'    => [],
        'classes' => ['hidden-group'],
    ];

    public function params (array &$p) {

        if ($p['data']) foreach ($p['data'] as $name => $value) {

            $p['fields'][] = new Hidden([
                'name'  => $name,
                'value' => $value,
            ]);

        }

        parent::params($p);

    }

}
