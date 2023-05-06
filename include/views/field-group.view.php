<?php

namespace Digitalis;

class Field_Group extends View {

    protected static $template = 'field-group';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/";

    protected static $defaults = [
        'fields'        => [],
        'id'            => false,
        'tag'           => 'div',
        'classes'       => [],
        'attributes'    => [],
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

    public static function params ($p) {

        if ($p['fields']) foreach ($p['fields'] as &$field) {

            $field = wp_parse_args($field, [
                'field' => '\Digitalis\Field\Input',
            ]);

        }

        $p['classes']    = static::generate_classes(static::get_classes($p));
        $p['attributes'] = static::generate_attributes($p);

        return $p;

    }

}