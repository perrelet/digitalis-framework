<?php

namespace Lattice\Component;

use Lattice\View;

use Lattice\Field\Input;

class Field_Group extends \Lattice\Component {

    protected static $template = 'components/field-group';
    protected static $template_path = LATTICE_PATH . "/templates/digitalis/";

    protected static $defaults = [
        'fields'        => [],
        'defaults'      => [],
        'label'         => false,
        'id'            => false,
        'tag'           => 'div',
        'condition'     => null,
        'classes'       => ['digitalis-field-group', 'field-group'],
        'attributes'    => [],
    ];

    public function params (&$p) {

        $p['fields'] = $this->get_fields();

        foreach ($p['defaults'] as $key => $default) foreach ($p['fields'] as &$field) {
        
            if (($field['name'] ?? ($field['key']) ?? '') != $key) continue;
            $field['default'] = $default;
            break;

        }

        if ($p['fields']) foreach ($p['fields'] as &$field) {

            if (!($field instanceof View)) $field = wp_parse_args($field, [
                'field' => Input::class,
            ]);

            $field = Hook::filter('lattice.field_group.field', $field, $p, static::class);

            if (isset($field['options'])) $field['options'] = $this->get_field_options($field['options'], $field);

        }

        if ($p['condition']) {

            $p['attributes']['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', LATTICE_URI . "assets/js/fields.js", [], LATTICE_VERSION, [
                'in_footer' => true,
            ]);

        }

        parent::params($p);

    }

    public function get_fields () {

        return $this['fields'];

    }

    public function get_field_options ($options, $field) {
        
        return $options;
        
    }

}