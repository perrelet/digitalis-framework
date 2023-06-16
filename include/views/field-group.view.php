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

    public static function get_classes ($p) {

        $classes = [
            "digitalis-field-group",
            "field-group",
        ];

        if ($p['classes']) $classes = array_merge($classes, $p['classes']);

        return $classes;

    }

    public static function generate_classes ($classes) {

        return implode(' ', $classes);

    }

    public static function generate_attributes ($p) {

        $p['attributes']['class'] = $p['classes'];

        $attributes = '';
        if ($p['attributes']) foreach ($p['attributes'] as $att_name => $att_value) $attributes .= " {$att_name}='{$att_value}'";
        return $attributes;

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

        $p['classes']    = static::generate_classes(static::get_classes($p));
        $p['attributes'] = static::generate_attributes($p);

        return $p;

    }

}