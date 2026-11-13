<?php

declare(strict_types=1);

namespace Lattice\Component;

use Lattice\Hook;
use Lattice\View;

class Form extends Field_Group {

    protected static $defaults = [
        'tag'         => 'form',
        'method'      => null,
        'action'      => null,
        'unavailable' => '',
        'preview'     => false,
    ];

    public function params (array &$p) {

        $p = Hook::filter('lattice.form.params', $p, $this);

        if ($p['method']) $p['attributes']['method'] = $p['method'];
        if ($p['action']) $p['attributes']['action'] = $p['action'];

        // docs/FORMS.md, "When a route cannot serve".
        if ($p['unavailable']) {

            $p['classes'][] = 'form-preview';
            $p['fields']    = [new \Lattice\Component(['tag' => 'p', 'classes' => ['form-unavailable'], 'content' => esc_html("Visitors can't see this form: {$p['unavailable']}")]), ...$this->disarm_fields((array) $p['fields'])];

        }

        parent::params($p);

    }

    public function condition () {

        return !$this['unavailable'] || $this['preview'];

    }

    protected function disarm_fields (array $fields) : array {

        $kept = [];

        foreach ($fields as $field) {

            $class = ($field instanceof View) ? get_class($field) : ($field['field'] ?? '');

            if (is_a($class, \Lattice\Field\Human_Check::class, true)) continue;

            if (is_a($class, \Lattice\Field\Submit::class, true)) {

                if ($field instanceof View) $field = clone $field;
                $field['disabled'] = true;

            }

            $kept[] = $field;

        }

        return $kept;

    }

}