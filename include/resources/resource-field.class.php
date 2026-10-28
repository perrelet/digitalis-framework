<?php

namespace Digitalis;

use DateTime;

final class Resource_Field {

    const STORED_FORMATS = [
        'date_time_picker' => 'Y-m-d H:i:s',
        'date_picker'      => 'Ymd',
        'time_picker'      => 'H:i:s',
    ];

    const MULTIPLE = ['checkbox', 'relationship', 'gallery'];

    public function __construct (
        public readonly string $name,
        public readonly string $type,
        public readonly string $source       = 'acf',
        public readonly string $key          = '',
        public readonly string $label        = '',
        public readonly string $instructions = '',
        public readonly bool   $required     = false,
        public readonly array  $choices      = [],
        public readonly bool   $multiple     = false,
        public readonly array  $post_types   = [],
        public readonly string $taxonomy     = '',
        public readonly array  $roles        = [],
        public readonly array  $sub_fields   = [],
        public readonly ?array $shown_when   = null,
        public readonly bool   $computed     = false,
        public readonly bool   $action_only  = false,
    ) {}

    // Dotted paths: 'stages.stage_key'.
    public static function from_acf (array $field, array $computed = [], array $action_only = [], string $prefix = '') : static {

        $path = $prefix . $field['name'];

        return new static(
            name:         $field['name'],
            type:         $field['type'],
            key:          $field['key'] ?? '',
            label:        $field['label'] ?? '',
            instructions: (string) ($field['instructions'] ?? ''),
            required:     !empty($field['required']),
            choices:      static::flatten_choices((array) ($field['choices'] ?? [])),
            multiple:     in_array($field['type'], static::MULTIPLE, true) || !empty($field['multiple']),
            post_types:   array_values(array_filter((array) ($field['post_type'] ?? []))),
            taxonomy:     ($field['type'] === 'taxonomy') ? (string) ($field['taxonomy'] ?? '') : '',
            roles:        array_values(array_filter((array) ($field['role'] ?? []))),
            sub_fields:   array_column(array_map(fn ($sub) => static::from_acf($sub, $computed, $action_only, "{$path}."), array_filter((array) ($field['sub_fields'] ?? []), fn ($sub) => ($sub['name'] ?? '') !== '')), null, 'name'),
            shown_when:   static::flatten_conditions($field['conditional_logic'] ?? null),
            computed:     in_array($path, $computed, true),
            action_only:  in_array($path, $action_only, true),
        );

    }

    protected static function flatten_choices (array $choices) : array {

        $flat = [];

        foreach ($choices as $value => $label) is_array($label) ? $flat += static::flatten_choices($label) : $flat[(string) $value] = $label;

        return $flat;

    }

    protected static function flatten_conditions ($logic) : ?array {

        $any = [];

        foreach ((array) ($logic ?: []) as $group) $any[] = array_map(fn ($rule) => ['field' => $rule['field'] ?? '', 'operator' => $rule['operator'] ?? '', 'value' => $rule['value'] ?? ''], (array) $group);

        return $any ?: null;

    }

    public function is_rows () : bool {

        return $this->type === 'repeater';

    }

    public function is_group () : bool {

        return $this->type === 'group';

    }

    public function get_format () : string {

        return static::STORED_FORMATS[$this->type] ?? '';

    }

    public function describe () : array {

        return array_filter([
            'name'         => $this->name,
            'label'        => $this->label,
            'type'         => $this->type,
            'instructions' => $this->instructions,
            'required'     => $this->required,
            'choices'      => $this->choices,
            'multiple'     => $this->multiple,
            'post_types'   => $this->post_types,
            'taxonomy'     => $this->taxonomy,
            'roles'        => $this->roles,
            'format'       => $this->get_format(),
            'shown_when'   => $this->shown_when,
            'computed'     => $this->computed,
            'action_only'  => $this->action_only,
            'sub_fields'   => array_values(array_map(fn ($sub) => $sub->describe(), $this->sub_fields)),
        ], fn ($value) => !in_array($value, ['', [], null, false], true));

    }

    // ACF keys raw rows by field key.
    public function from_raw ($raw) {

        if ($this->is_rows())  return array_values(array_map(fn ($row) => $this->row_from_raw((array) $row), is_array($raw) ? $raw : []));
        if ($this->is_group()) return $this->row_from_raw(is_array($raw) ? $raw : []);

        return $raw;

    }

    protected function row_from_raw (array $row) : array {

        $named = [];

        foreach ($this->sub_fields as $name => $sub) $named[$name] = $sub->from_raw($row[$sub->key] ?? $row[$name] ?? null);

        return $named;

    }

    public function to_raw ($value) {

        if ($this->is_rows())  return array_values(array_map(fn ($row) => $this->row_to_raw((array) $row), is_array($value) ? $value : []));
        if ($this->is_group()) return $this->row_to_raw(is_array($value) ? $value : []);

        return $value;

    }

    protected function row_to_raw (array $row) : array {

        $raw = [];

        foreach ($this->sub_fields as $name => $sub) if (array_key_exists($name, $row)) $raw[$sub->key ?: $name] = $sub->to_raw($row[$name]);

        return $raw;

    }

    // $restoring lets undo bring back row keys the record no longer has.
    public function check ($value, string $path, $current = null, bool $restoring = false) : array {

        if ($this->computed)    return ["{$path} is worked out by the save, never written."];
        if ($this->action_only) return ["{$path} changes only through an action."];

        return $this->check_shape($value, $path, $current, $restoring) ?: $this->check_with_acf($value, $path);

    }

    protected function check_shape ($value, string $path, $current = null, bool $restoring = false) : array {

        if (in_array($value, [null, '', []], true)) return $this->required ? ["{$path} is required."] : [];

        if ($this->is_rows())  return $this->check_rows($value, $path, is_array($current) ? $current : [], $restoring);
        if ($this->is_group()) return is_array($value) && !array_is_list($value) ? $this->check_row($value, $path) : ["{$path} is an object of " . implode(', ', array_keys($this->sub_fields)) . '.'];

        if ($this->multiple && !(is_array($value) && array_is_list($value))) return ["{$path} is a list."];

        foreach ($this->multiple ? $value : [$value] as $one) if ($problem = $this->check_one($one)) return ["{$path} {$problem}."];

        return [];

    }

    protected function check_rows ($rows, string $path, array $current, bool $restoring) : array {

        if (!is_array($rows) || !array_is_list($rows)) return ["{$path} is a list of rows."];

        $problems = [];

        foreach ($this->sub_fields as $name => $sub) {

            if (!$sub->computed) continue;

            $known = array_filter(array_map(fn ($row) => (string) ($row[$name] ?? ''), $current));
            $seen  = [];

            foreach ($rows as $i => $row) {

                $key = (string) ($row[$name] ?? '');

                if ($key === '') continue;

                if (isset($seen[$key]))                              $problems[] = "{$path}.{$i}.{$name} {$key} is repeated.";
                elseif (!$restoring && !in_array($key, $known, true)) $problems[] = "{$path}.{$i}.{$name} {$key} is not one of this record's: send a new row without it.";

                $seen[$key] = true;

            }

        }

        foreach ($rows as $i => $row) array_push($problems, ...(is_array($row) && !array_is_list($row) ? $this->check_row($row, "{$path}.{$i}") : ["{$path}.{$i} is an object of " . implode(', ', array_keys($this->sub_fields)) . '.']));

        return $problems;

    }

    protected function check_row (array $row, string $path) : array {

        $problems = [];

        if ($unknown = array_diff(array_keys($row), array_keys($this->sub_fields))) $problems[] = "{$path} has no " . implode(', ', $unknown) . '.';

        foreach ($row as $name => $value) if (($sub = $this->sub_fields[$name] ?? null) && !$sub->computed) array_push($problems, ...($sub->action_only ? ["{$path}.{$name} changes only through an action."] : $sub->check_shape($value, "{$path}.{$name}")));

        return $problems;

    }

    // ACF reports sub-fields by key.
    protected function check_with_acf ($value, string $path) : array {

        if (!$this->key || !function_exists('acf_validate_value') || !($field = acf_get_field($this->key))) return [];

        acf_reset_validation_errors();
        acf_validate_value($this->to_raw($value), $field, $path);

        $errors = acf_get_validation_errors() ?: [];

        acf_reset_validation_errors();

        $names = $this->get_names_by_key();

        return array_map(fn ($error) => implode('.', array_map(fn ($part) => $names[$part] ?? $part, preg_split('/\]?\[|\]/', $error['input'], -1, PREG_SPLIT_NO_EMPTY))) . ": {$error['message']}", $errors);

    }

    protected function get_names_by_key () : array {

        $names = [];

        foreach ($this->sub_fields as $sub) $names += [$sub->key => $sub->name] + $sub->get_names_by_key();

        return $names;

    }

    protected function check_one ($value) : string {

        if (is_array($value) || is_object($value)) return 'is not a list or an object here';

        $id = is_numeric($value) ? (int) $value : 0;

        return match ($this->type) {
            'text', 'textarea', 'wysiwyg', 'password', 'oembed', 'color_picker' => is_string($value) ? '' : 'is text',
            'email'                                                      => is_email((string) $value) ? '' : 'is an email address',
            'url'                                                        => filter_var($value, FILTER_VALIDATE_URL) ? '' : 'is a URL',
            'number', 'range'                                            => is_numeric($value) ? '' : 'is a number',
            'true_false'                                                 => in_array($value, [0, 1, '0', '1', true, false], true) ? '' : 'is 1 or 0',
            'select', 'radio', 'button_group', 'checkbox'                => array_key_exists((string) $value, $this->choices) ? '' : 'is one of ' . implode(', ', array_keys($this->choices)),
            'date_time_picker', 'date_picker', 'time_picker'             => $this->is_formatted((string) $value) ? '' : 'is a date as ' . $this->get_format(),
            'post_object', 'relationship', 'page_link'                   => ($id && get_post_status($id) && (!$this->post_types || in_array(get_post_type($id), $this->post_types, true))) ? '' : 'is the id of a ' . ($this->post_types ? implode(' or ', $this->post_types) : 'post'),
            'taxonomy'                                                   => ($id && term_exists($id, $this->taxonomy ?: '')) ? '' : 'is the id of a term' . ($this->taxonomy ? " in {$this->taxonomy}" : ''),
            'user'                                                       => ($id && get_userdata($id)) ? '' : 'is the id of a user',
            'image', 'file', 'gallery'                                   => ($id && get_post_type($id) === 'attachment') ? '' : 'is the id of a file in the media library',
            default                                                      => '',
        };

    }

    protected function is_formatted (string $value) : bool {

        $date = DateTime::createFromFormat($this->get_format(), $value);

        return $date && $date->format($this->get_format()) === $value;

    }

}
