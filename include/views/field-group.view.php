<?php

namespace Digitalis;

use Digitalis\Field\Input;

class Field_Group extends View {

    protected static $template = 'field-group';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/";

    protected static $defaults = [
        'fields'        => [],
        'label'         => false,
        'id'            => false,
        'tag'           => 'div',
        'classes'       => [],
        'attributes'    => [],
    ];

    protected static $merge = [
        'classes',
        'attributes',
    ];

    public static function generate_classes ($classes) {

        return implode(' ', $classes);

    }

    public static function get_classes (&$p) {

        if ($p['classes'] && !is_array($p['classes'])) $p['classes'] = [$p['classes']];

        $p['classes'][] = 'digitalis-field-group';
        $p['classes'][] = 'field-group';

    }

    public static function generate_attributes ($attributes) {

        $html = '';
        if ($attributes) foreach ($attributes as $att_name => $att_value) $html .= " {$att_name}='{$att_value}'";
        return $html;

    }

    public static function get_attributes (&$p) {

        $p['attributes']['class'] = $p['classes'];

        if (isset($p['condition'])) {

            $p['attributes']['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
                'in_footer' => true,
            ]);

        }

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

            if (isset($field['options'])) $field['options'] = static::get_field_options($field['options'], $field);

            $field = wp_parse_args($field, [
                'field' => Input::class,
            ]);

            $field = apply_filters('Digitalis/Field_Group/Field', $field, $p, static::class);

        }

        static::get_classes($p);
        $p['classes'] = static::generate_classes($p['classes']);

        static::get_attributes($p);
        $p['attributes']  = static::generate_attributes($p['attributes']);

        return $p;

    }

}