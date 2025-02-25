<?php

namespace Digitalis;

use Digitalis\Field\Input;

class Field_Group extends Component {

    protected static $template = 'components/field-group';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/";

    protected static $defaults = [
        'fields'        => [],
        'label'         => false,
        'id'            => false,
        'tag'           => 'div',
        'classes'       => ['digitalis-field-group', 'field-group'],
        'attributes'    => [],
    ];

    protected static $merge = [
        'classes',
        'attributes',
    ];

    public static function get_attributes ($p, $prefix = '') {

        $p = parent::get_attributes($p, $prefix);

        if (isset($p['condition'])) {

            $p['attributes']['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
                'in_footer' => true,
            ]);

        }

        return $p;

    }

    public static function get_fields ($fields) {
        
        return $fields;
        
    }

    public static function get_field_options ($options, $field) {
        
        return $options;
        
    }

    public static function params ($p) {

        $p['fields'] = static::get_fields($p['fields']);

        if ($p['fields']) foreach ($p['fields'] as &$field) {

            if ($field instanceof View) {

                $field = apply_filters('Digitalis/Field_Group/Field', $field, [], static::class);

            } else {

                if (isset($field['options'])) $field['options'] = static::get_field_options($field['options'], $field);

                $field = wp_parse_args($field, [
                    'field' => Input::class,
                ]);
    
                $field = apply_filters('Digitalis/Field_Group/Field', $field, $p, static::class);

            }

        }

        return parent::params($p);

    }

}