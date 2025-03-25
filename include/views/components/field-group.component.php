<?php

namespace Digitalis;

use Digitalis\Field\Input;

class Field_Group extends Component {

    protected static $template = 'components/field-group';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/";

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

            $field = apply_filters('Digitalis/Field_Group/Field', $field, $p, static::class);

            if (isset($field['options'])) $field['options'] = $this->get_field_options($field['options'], $field);

        }

        if ($p['condition']) {

            $p['attributes']['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
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