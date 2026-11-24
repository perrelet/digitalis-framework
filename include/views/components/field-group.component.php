<?php

declare(strict_types=1);

namespace Lattice\Component;

use Lattice\View;
use Lattice\Hook;
use Lattice\Strict;

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
        $p['fields'] = [];

        if (is_array($fields)) foreach ($fields as $index => $field) {

            if ($field instanceof View) $p['fields'][$index] = clone $field;
            else $this->reject_field($index, $field);

        }

        foreach ($p['defaults'] as $key => $default) foreach ($p['fields'] as $field) {
        
            if (($field['name'] ?? '') != $key) continue;
            $field['default'] = $default;
            break;

        }

        foreach ($p['fields'] as $index => $field) {

            if ($p['field_params']) $field = $this->apply_field_params($field, $p['field_params']);

            $field = Hook::filter('lattice.field_group.field', $field, $p, static::class);

            if (!($field instanceof View)) {

                $this->reject_field($index, $field, ' from the lattice.field_group.field filter');
                unset($p['fields'][$index]);
                continue;

            }

            if (isset($field['options'])) $field['options'] = $this->get_field_options($field['options'], $field);

            $p['fields'][$index] = $field;

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

    protected function reject_field (int|string $index, mixed $field, string $source = '') : void {

        $class   = is_array($field) && is_string($field['field'] ?? null) ? ltrim($field['field'], '\\') : 'Input';
        $problem = "fields entry " . var_export($index, true) . ' is ' . get_debug_type($field) . "{$source}, not a View, so it is skipped.";

        Strict::fail(static::class, $problem, "Build each field with new: new {$class}([...]).");
        Strict::log_once(static::class, $problem);

    }

    protected function apply_field_params ($field, array $map) {

        if ($field instanceof Field_Group) {

            $field['field_params'] = array_merge((array) ($field['field_params'] ?? []), $map);
            return $field;

        }

        $name = rtrim((string) ($field['name'] ?? ''), '[]');

        if (($name === '') || !isset($map[$name])) return $field;

        foreach ($map[$name] as $key => $value) $field[$key] = (in_array($key, static::MERGED_PARAMS, true) && is_array($value)) ? array_merge((array) ($field[$key] ?? []), $value) : $value;

        return $field;

    }

    public function get_field_options (mixed $options, mixed $field) {
        
        return $options;
        
    }

}