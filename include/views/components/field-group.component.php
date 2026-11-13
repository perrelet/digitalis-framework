<?php

declare(strict_types=1);

namespace Lattice\Component;

use Lattice\View;
use Lattice\Hook;

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
        'field_params'  => [],
    ];

    const MERGED_PARAMS = ['attributes', 'classes', 'row_classes'];

    public function params (array &$p) {

        $fields      = $this->get_fields();
        $p['fields'] = is_array($fields) ? array_map(fn ($field) => ($field instanceof View) ? clone $field : $field, $fields) : $fields;

        foreach ($p['defaults'] as $key => $default) foreach ($p['fields'] as &$field) {
        
            if (($field['name'] ?? ($field['key']) ?? '') != $key) continue;
            $field['default'] = $default;
            break;

        }

        if ($p['fields']) foreach ($p['fields'] as &$field) {

            if (!($field instanceof View)) $field = wp_parse_args($field, [
                'field' => Input::class,
            ]);

            if ($p['field_params']) $field = $this->apply_field_params($field, $p['field_params']);

            $field = Hook::filter('lattice.field_group.field', $field, $p, static::class);

            if (isset($field['options'])) $field['options'] = $this->get_field_options($field['options'], $field);

        }

        if ($p['condition']) {

            $p['attributes']['data-field-condition'] = json_encode($p['condition']);
            $p['attributes']['data-field-id']        = $p['id'] ?: "field-group-{$p['view_index']}";

            wp_enqueue_script('digitalis-fields', LATTICE_URI . "assets/js/fields.js", [], LATTICE_VERSION, [
                'in_footer' => true,
            ]);

        }

        parent::params($p);

    }

    public function get_fields () {

        return $this['fields'];

    }

    protected function apply_field_params ($field, array $map) {

        $class = ($field instanceof View) ? get_class($field) : $field['field'];

        if (is_a($class, Field_Group::class, true)) {

            $field['field_params'] = array_merge((array) ($field['field_params'] ?? []), $map);
            return $field;

        }

        $name = rtrim((string) ($field['name'] ?? $field['key'] ?? ''), '[]');

        if (($name === '') || !isset($map[$name])) return $field;

        foreach ($map[$name] as $key => $value) $field[$key] = (in_array($key, static::MERGED_PARAMS, true) && is_array($value)) ? array_merge((array) ($field[$key] ?? []), $value) : $value;

        return $field;

    }

    public function get_field_options (mixed $options, mixed $field) {
        
        return $options;
        
    }

}