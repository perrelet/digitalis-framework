<?php

declare(strict_types=1);

namespace Lattice;

use Closure;
use DateTime;

final class Resource_Field {

    const STORED_FORMATS = [
        'date_time_picker' => 'Y-m-d H:i:s',
        'date_picker'      => 'Ymd',
        'time_picker'      => 'H:i:s',
    ];

    const CHOICE_TYPES = ['select', 'radio', 'button_group', 'checkbox'];

    const MULTIPLE = ['checkbox', 'relationship', 'gallery'];

    const REFERENCES = ['post_object', 'relationship', 'page_link', 'taxonomy', 'user', 'image', 'file', 'gallery'];

    const INTERNAL = ['ID', 'prefix', '_name', '_valid', '_prepare', 'parent', 'parent_layout', 'menu_order', 'wrapper', 'class', 'id', 'value', 'sub_fields', 'layouts', 'get', 'set', 'validate'];

    public /* readonly */ string $name;
    public /* readonly */ string $type;
    public /* readonly */ string $key;
    public /* readonly */ bool   $required;

    /**
     * @param array $acf        ACF's field array; a declared field gives the same shape.
     * @param array $sub_fields Resource_Fields by name.
     * @param array $layouts    A flexible field's sub-fields, by layout name.
     */
    public function __construct (
        public /* readonly */ array    $acf,
        public /* readonly */ string   $source      = 'acf',
        public /* readonly */ bool     $computed    = false,
        public /* readonly */ bool     $action_only = false,
        public /* readonly */ array    $sub_fields  = [],
        public /* readonly */ array    $layouts     = [],
        public /* readonly */ ?Closure $validate    = null,
    ) {

        $this->name     = (string) $acf['name'];
        $this->type     = (string) ($acf['type'] ?? 'text');
        $this->key      = (string) ($acf['key'] ?? '');
        $this->required = !empty($acf['required']);

    }

    // Dotted paths: 'stages.stage_key'.
    public static function from_acf (array $field, array $computed = [], array $action_only = [], array $secret = [], string $prefix = '') : static {

        $path     = $prefix . $field['name'];
        $children = fn (array $fields) => array_column(array_map(fn ($sub) => static::from_acf($sub, $computed, $action_only, $secret, "{$path}."), array_filter($fields, fn ($sub) => ($sub['name'] ?? '') !== '')), null, 'name');

        return new static(
            acf:         $field + (in_array($path, $secret, true) ? ['secret' => true] : []),
            computed:    in_array($path, $computed, true),
            action_only: in_array($path, $action_only, true),
            sub_fields:  $children((array) ($field['sub_fields'] ?? [])),
            layouts:     array_map(fn ($layout) => $children((array) ($layout['sub_fields'] ?? [])), array_column((array) ($field['layouts'] ?? []), null, 'name')),
        );

    }

    public function is_rows () : bool {

        return $this->type === 'repeater';

    }

    public function is_group () : bool {

        return $this->type === 'group';

    }

    public function is_flexible () : bool {

        return $this->type === 'flexible_content';

    }

    public function is_multiple () : bool {

        return in_array($this->type, static::MULTIPLE, true) || !empty($this->acf['multiple']);

    }

    public function is_secret () : bool {

        return !empty($this->acf['secret']) || ($this->type === 'password');

    }

    public function get_format () : string {

        return static::STORED_FORMATS[$this->type] ?? '';

    }

    public function get_choices () : array {

        $choices = (array) ($this->acf['choices'] ?? []);
        $flat    = [];

        array_walk_recursive($choices, function ($label, $value) use (&$flat) { $flat[(string) $value] = $label; });

        return $flat;

    }

    public function describe () : array {

        $describe = fn (array $fields) => array_values(array_map(fn ($field) => $field->describe(), $fields));

        return array_filter([
            ...array_diff_key($this->acf, array_flip(static::INTERNAL)),
            'format'      => $this->get_format(),
            'secret'      => $this->is_secret(),
            'computed'    => $this->computed,
            'action_only' => $this->action_only,
            'sub_fields'  => $describe($this->sub_fields),
            'layouts'     => array_map(fn ($layout) => ['name' => $layout['name'], 'label' => $layout['label'] ?? '', 'sub_fields' => $describe($this->layouts[$layout['name']] ?? [])], array_values((array) ($this->acf['layouts'] ?? []))),
        ], fn ($value) => !in_array($value, ['', [], null, false], true));

    }

    // Changes, for people

    const SHOWN_ITEMS = 10;

    /** @return array[] ['field' => path, 'label', 'before', 'after'], one per change a person would name */
    public function present_change ($before, $after, string $label = '', string $path = '') : array {

        if ($before === $after) return [];

        $own   = (string) (($this->acf['label'] ?? '') ?: $this->name);
        $label = $label ? "{$label} › {$own}" : $own;
        $path  = $path ? "{$path}.{$this->name}" : $this->name;

        return match (true) {
            $this->type === 'terms'                    => $this->present_terms((array) $before, (array) $after, $label, $path),
            $this->is_rows(), $this->is_flexible()     => $this->present_rows(array_values((array) $before), array_values((array) $after), $label, $path),
            $this->is_group()                          => static::present_row($this->sub_fields, (array) $before, (array) $after, $label, $path),
            is_array($before) || is_array($after)      => [['field' => $path, 'label' => $label, 'before' => $this->display_list((array) $before), 'after' => $this->display_list((array) $after)]],
            default                                    => [['field' => $path, 'label' => $label, 'before' => $this->display($before), 'after' => ($this->display($before) === $this->display($after)) ? 'Changed' : $this->display($after)]],
        };

    }

    protected function present_rows (array $before, array $after, string $label, string $path) : array {

        $lines = [];

        foreach (array_keys($after + $before) as $i) {

            $was    = $before[$i] ?? null;
            $is     = $after[$i] ?? null;
            $fields = $this->is_flexible() ? ($this->layouts[($is ?? $was)['layout'] ?? ''] ?? []) : $this->sub_fields;
            $row    = "{$label} › Row " . ($i + 1);

            if (is_null($was))    $lines[] = ['field' => "{$path}.{$i}", 'label' => $row, 'before' => '', 'after' => 'Added'];
            elseif (is_null($is)) $lines[] = ['field' => "{$path}.{$i}", 'label' => $row, 'before' => 'Removed', 'after' => ''];
            else array_push($lines, ...static::present_row($fields, (array) $was, (array) $is, $row, "{$path}.{$i}"));

        }

        return $lines;

    }

    protected static function present_row (array $fields, array $before, array $after, string $label, string $path) : array {

        $lines = [];

        foreach ($fields as $name => $field) array_push($lines, ...$field->present_change($before[$name] ?? null, $after[$name] ?? null, $label, $path));

        return $lines;

    }

    protected function present_terms (array $before, array $after, string $label, string $path) : array {

        $lines = [];

        foreach (array_keys($after + $before) as $taxonomy) {

            if (($was = (array) ($before[$taxonomy] ?? [])) == ($is = (array) ($after[$taxonomy] ?? []))) continue;

            $names   = fn (array $ids) => array_map(fn ($id) => get_term((int) $id)?->name ?? "#{$id}", $ids);
            $lines[] = ['field' => "{$path}.{$taxonomy}", 'label' => "{$label} › " . (get_taxonomy($taxonomy)?->labels->singular_name ?? $taxonomy), 'before' => $this->shorten_list($names($was)), 'after' => $this->shorten_list($names($is))];

        }

        return $lines;

    }

    public function display ($value) : string {

        if (in_array($value, [null, '', []], true) || (in_array($value, [0, '0'], true) && in_array($this->type, static::REFERENCES, true))) return '';

        $id = is_numeric($value) ? (int) $value : 0;

        return match (true) {
            $this->type === 'true_false'                                       => $value ? 'Yes' : 'No',
            in_array($this->type, static::CHOICE_TYPES, true)                 => (string) ($this->get_choices()[(string) $value] ?? $value),
            in_array($this->type, ['image', 'file', 'gallery'], true) && $id  => wp_basename((string) get_attached_file($id)) ?: "#{$id}",
            in_array($this->type, ['post_object', 'relationship', 'page_link'], true) && $id => get_the_title($id) ?: "#{$id}",
            ($this->type === 'taxonomy') && $id                                => get_term($id)?->name ?? "#{$id}",
            ($this->type === 'user') && $id                                    => get_userdata($id)?->display_name ?? "#{$id}",
            default                                                            => wp_html_excerpt(wp_strip_all_tags((string) $value), 100, '…'),
        };

    }

    protected function display_list (array $values) : string {

        return $this->shorten_list(array_map(fn ($value) => $this->display($value), array_values($values)));

    }

    protected function shorten_list (array $items) : string {

        return (count($items) > static::SHOWN_ITEMS) ? count($items) . ' items' : implode(', ', $items);

    }

    // Secrets

    public function mask ($value) {

        if ($this->is_secret()) return in_array($value, [null, '', []], true) ? '' : '[set ' . substr(wp_hash(maybe_serialize($value)), 0, 8) . ']';

        return $this->map_rows($value, fn (array $fields, array $row) => static::map_row($fields, $row, fn ($field, $one) => $field->mask($one)));

    }

    // A mask sent back is the value it stands for.
    public function unmask ($sent, $raw) {

        if ($this->is_secret()) return ($sent === $this->mask($raw)) ? $raw : $sent;

        return $this->map_rows($sent, fn (array $fields, array $row, $i) => static::map_row($fields, $row, fn ($field, $one, $name) => $field->unmask($one, ($this->is_group() ? $raw : ($raw[$i] ?? []))[$name] ?? null)));

    }

    protected function map_rows ($value, callable $map) {

        if (!is_array($value)) return $value;

        return match (true) {
            $this->is_group()    => $map($this->sub_fields, $value, null),
            $this->is_rows()     => array_map(fn ($row, $i) => is_array($row) ? $map($this->sub_fields, $row, $i) : $row, $value, array_keys($value)),
            $this->is_flexible() => array_map(fn ($row, $i) => is_array($row) ? $map($this->layouts[$row['layout'] ?? ''] ?? [], $row, $i) : $row, $value, array_keys($value)),
            default              => $value,
        };

    }

    protected static function map_row (array $fields, array $row, callable $map) : array {

        foreach (array_intersect_key($row, $fields) as $name => $one) $row[$name] = $map($fields[$name], $one, $name);

        return $row;

    }

    // Raw shape

    // ACF keys raw rows by field key, and a flexible row's layout by acf_fc_layout.
    public function from_raw ($raw) {

        $rows = is_array($raw) ? $raw : [];

        return match (true) {
            $this->is_rows()     => array_values(array_map(fn ($row) => static::row_from_raw($this->sub_fields, (array) $row), $rows)),
            $this->is_group()    => static::row_from_raw($this->sub_fields, $rows),
            $this->is_flexible() => array_values(array_map(fn ($row) => ['layout' => $row['acf_fc_layout'] ?? ''] + static::row_from_raw($this->layouts[$row['acf_fc_layout'] ?? ''] ?? [], (array) $row), $rows)),
            default              => $raw,
        };

    }

    public function to_raw ($value) {

        $rows = is_array($value) ? $value : [];

        return match (true) {
            $this->is_rows()     => array_values(array_map(fn ($row) => static::row_to_raw($this->sub_fields, (array) $row), $rows)),
            $this->is_group()    => static::row_to_raw($this->sub_fields, $rows),
            $this->is_flexible() => array_values(array_map(fn ($row) => ['acf_fc_layout' => $row['layout'] ?? ''] + static::row_to_raw($this->layouts[$row['layout'] ?? ''] ?? [], (array) $row), $rows)),
            default              => $value,
        };

    }

    protected static function row_from_raw (array $fields, array $row) : array {

        $named = [];

        foreach ($fields as $name => $field) $named[$name] = $field->from_raw($row[$field->key] ?? $row[$name] ?? null);

        return $named;

    }

    protected static function row_to_raw (array $fields, array $row) : array {

        $raw = [];

        foreach ($fields as $name => $field) if (array_key_exists($name, $row)) $raw[$field->key ?: $name] = $field->to_raw($row[$name]);

        return $raw;

    }

    // Checks

    // ACF's own first, then what ACF does not check on the server. $restoring lets undo bring back row keys the record no longer has.
    public function check ($value, string $path, $current = null, bool $restoring = false) : array {

        if ($this->computed)    return ["{$path} is worked out by the save, never written."];
        if ($this->action_only) return ["{$path} changes only through an action."];

        return $this->check_shape($value, $path, $current, $restoring) ?: $this->check_with_acf($value, $path);

    }

    protected function check_shape ($value, string $path, $current = null, bool $restoring = false) : array {

        if ($this->validate) return ($this->validate)($value, $path);

        if (in_array($value, [null, '', []], true)) return [];

        if ($this->is_rows())     return $this->check_rows($value, $path, is_array($current) ? $current : [], $restoring);
        if ($this->is_group())    return static::check_row($this->sub_fields, $value, $path, (is_array($current) && !array_is_list($current)) ? [$current] : (array) $current, $restoring);
        if ($this->is_flexible()) return $this->check_layouts($value, $path, is_array($current) ? $current : [], $restoring);

        if ($this->is_multiple() && !(is_array($value) && array_is_list($value))) return ["{$path} is a list."];

        foreach ($this->is_multiple() ? $value : [$value] as $one) if ($problem = $this->check_one($one)) return ["{$path} {$problem}."];

        return [];

    }

    protected function check_rows ($rows, string $path, array $current, bool $restoring) : array {

        if (!is_array($rows) || !array_is_list($rows)) return ["{$path} is a list of rows."];

        $problems = [];

        foreach (array_filter($this->sub_fields, fn ($sub) => $sub->computed) as $name => $sub) {

            $known = array_filter(array_map(fn ($row) => (string) ($row[$name] ?? ''), $current));
            $seen  = [];

            foreach ($rows as $i => $row) {

                if (($key = (string) ($row[$name] ?? '')) === '') continue;

                if (isset($seen[$key]))                               $problems[] = "{$path}.{$i}.{$name} {$key} is repeated.";
                elseif (!$restoring && !in_array($key, $known, true)) $problems[] = "{$path}.{$i}.{$name} {$key} is not one of this record's: send a new row without it.";

                $seen[$key] = true;

            }

        }

        foreach ($rows as $i => $row) array_push($problems, ...static::check_row($this->sub_fields, $row, "{$path}.{$i}", $current, $restoring));

        return $problems;

    }

    protected function check_layouts ($rows, string $path, array $current, bool $restoring) : array {

        if (!is_array($rows) || !array_is_list($rows)) return ["{$path} is a list of rows."];

        $problems = [];

        foreach ($rows as $i => $row) {

            $layout = is_array($row) ? (string) ($row['layout'] ?? '') : '';

            if (!isset($this->layouts[$layout])) $problems[] = "{$path}.{$i}.layout is one of " . implode(', ', array_keys($this->layouts)) . '.';
            else array_push($problems, ...static::check_row($this->layouts[$layout], array_diff_key($row, ['layout' => 0]), "{$path}.{$i}", array_values(array_filter($current, fn ($was) => ($was['layout'] ?? '') === $layout)), $restoring));

        }

        return $problems;

    }

    // A nested repeater's row keys are known from its rows under every current parent row, since a row may move between parents.
    protected static function check_row (array $fields, $row, string $path, array $current = [], bool $restoring = false) : array {

        if (!is_array($row) || ($row && array_is_list($row))) return ["{$path} is an object of " . implode(', ', array_keys($fields)) . '.'];

        $problems = ($unknown = array_diff(array_keys($row), array_keys($fields))) ? ["{$path} has no " . implode(', ', $unknown) . '.'] : [];

        foreach (array_intersect_key($row, $fields) as $name => $value) {

            $field = $fields[$name];
            $was   = array_column($current, $name);
            $was   = $field->is_group() ? array_values(array_filter($was, 'is_array')) : array_merge(...array_map(fn ($rows) => is_array($rows) ? array_values($rows) : [], $was));

            if ($field->action_only) $problems[] = "{$path}.{$name} changes only through an action.";
            elseif (!$field->computed) array_push($problems, ...$field->check_shape($value, "{$path}.{$name}", $was, $restoring));

        }

        return $problems;

    }

    protected function check_one ($value) : string {

        if (is_array($value) || is_object($value)) return 'is not a list or an object here';

        if (in_array($value, [0, '0'], true) && in_array($this->type, static::REFERENCES, true)) return '';

        $id    = is_numeric($value) ? (int) $value : 0;
        $types = array_filter((array) ($this->acf['post_type'] ?? []));

        return match (true) {
            in_array($this->type, static::CHOICE_TYPES, true)              => (!empty($this->acf['allow_custom']) || !empty($this->acf['other_choice']) || array_key_exists((string) $value, $this->get_choices())) ? '' : 'is one of ' . implode(', ', array_keys($this->get_choices())),
            (bool) $this->get_format()                                    => $this->is_formatted((string) $value) ? '' : 'is a date as ' . $this->get_format(),
            in_array($this->type, ['post_object', 'relationship'], true),
            ($this->type === 'page_link') && is_numeric($value)           => ($id && get_post_status($id) && (!$types || in_array(get_post_type($id), $types, true))) ? '' : 'is the id of a ' . implode(' or ', $types ?: ['post']),
            $this->type === 'taxonomy'                                    => ($id && term_exists($id, (string) ($this->acf['taxonomy'] ?? ''))) ? '' : 'is the id of a term in ' . ($this->acf['taxonomy'] ?? 'its taxonomy'),
            $this->type === 'user'                                        => ($id && get_userdata($id)) ? '' : 'is the id of a user',
            in_array($this->type, ['image', 'file', 'gallery'], true)     => ($id && get_post_type($id) === 'attachment') ? '' : 'is the id of a file in the media library',
            default                                                       => '',
        };

    }

    protected function is_formatted (string $value) : bool {

        $date = DateTime::createFromFormat($this->get_format(), $value);

        return $date && $date->format($this->get_format()) === $value;

    }

    // ACF reports sub-fields by key.
    protected function check_with_acf ($value, string $path) : array {

        if (($this->source !== 'acf') || !$this->key || !function_exists('acf_validate_value')) return [];

        acf_reset_validation_errors();
        acf_validate_value($this->to_raw($value), $this->acf, $path);

        $errors = acf_get_validation_errors() ?: [];

        acf_reset_validation_errors();

        $names = $this->get_names_by_key();

        return array_map(fn ($error) => implode('.', array_map(fn ($part) => $names[$part] ?? $part, preg_split('/\]?\[|\]/', $error['input'], -1, PREG_SPLIT_NO_EMPTY))) . ": {$error['message']}", $errors);

    }

    protected function get_names_by_key () : array {

        $names = [];

        foreach ([$this->sub_fields, ...array_values($this->layouts)] as $fields) foreach ($fields as $field) $names += [$field->key => $field->name] + $field->get_names_by_key();

        return $names;

    }

}
