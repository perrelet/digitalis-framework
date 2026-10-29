<?php

namespace Digitalis;

abstract class Model_Resource extends Singleton {

    protected static $name        = '';
    protected static $model_class = '';
    protected static $label       = '';

    const ACTION_ONLY = [];

    const SECRET = [];

    protected $fields;
    protected $post_type;
    protected $record_fields = [];

    public function init () {

        Resources::inst()->register($this);

    }

    public function get_name () : string {

        return static::$name;

    }

    public function get_label () : string {

        return static::$label ?: static::$name;

    }

    public function get_model_class () : string {

        return static::$model_class;

    }

    public function get_post_type () : string {

        return $this->post_type ??= (string) (static::$model_class)::create()->get_post_type();

    }

    // Fields

    // Never written: 'dotted.path' => null, or 'name' => fn ($model) for a value outside ACF.
    public function get_computed () : array {

        return [];

    }

    // Through get_{name}() and set_{name}(), or the methods 'get' and 'set' name ('get' may be a closure). A dry run calls the setter: it must only stage.
    public function get_model_fields () : array {

        return [
            'title'  => ['type' => 'text', 'label' => 'Title', 'get' => fn ($post) => $post->get_wp_post()->post_title],
            'status' => ['type' => 'select', 'label' => 'Status', 'choices' => ['draft' => 'Draft', 'publish' => 'Published']],
        ];

    }

    // name => why
    public function get_locked_fields ($model) : array {

        return [];

    }

    /** @return Resource_Field[] by name: every field a record of this type can show. */
    public function get_fields () : array {

        return $this->fields ??= $this->build_fields($this->get_type_groups());

    }

    /** @return Resource_Field[] by name: the fields this record shows. */
    public function get_fields_for ($model) : array {

        if (!$model?->get_id() || !function_exists('acf_get_field_groups')) return $this->get_fields();

        return $this->record_fields[$model->get_id()] ??= $this->build_fields(acf_get_field_groups(['post_id' => $model->get_id(), 'post_type' => $this->get_post_type()]));

    }

    public function get_field (string $name) : ?Resource_Field {

        return $this->get_fields()[$name] ?? null;

    }

    protected function build_fields (array $groups) : array {

        $fields = [];

        foreach ($this->get_model_fields() as $name => $settings) $fields[$name] = new Resource_Field(['name' => $name] + array_diff_key($settings, ['validate' => 0]), 'model', validate: $settings['validate'] ?? null);

        foreach ($groups as $group) foreach ((array) acf_get_fields($group) as $field) if (($field['name'] ?? '') !== '') $fields[$field['name']] = Resource_Field::from_acf($field + array_intersect_key($group, ['applies_when' => 0]), array_keys($this->get_computed()), static::ACTION_ONLY, static::SECRET);

        foreach ($this->get_computed() as $name => $reader) if (is_callable($reader) && !isset($fields[$name])) $fields[$name] = new Resource_Field(['name' => $name, 'type' => 'computed'], 'reader', computed: true);

        return $fields;

    }

    // ACF matches a group with a term rule only against a record, so the type's own match leaves it out.
    protected function get_type_groups () : array {

        if (!function_exists('acf_get_field_groups')) return [];

        $groups = array_column(acf_get_field_groups(['post_type' => $this->get_post_type()]), null, 'key');

        foreach (acf_get_field_groups() as $group) if (!isset($groups[$group['key']]) && ($when = array_values(array_filter(array_map([$this, 'get_term_rules'], (array) $group['location']))))) $groups[$group['key']] = $group + ['applies_when' => $when];

        return array_values($groups);

    }

    // Empty unless the rule set asks only for this post type and its terms.
    protected function get_term_rules (array $rules) : array {

        $type       = $this->get_post_type();
        $taxonomies = get_object_taxonomies($type);
        $typed      = array_filter($rules, fn ($rule) => ($rule['param'] === 'post_type') && ($rule['operator'] === '==') && ($rule['value'] === $type));
        $terms      = array_filter($rules, fn ($rule) => ($rule['param'] === 'post_taxonomy') && in_array(strtok((string) $rule['value'], ':'), $taxonomies, true));

        return ($terms && (count($typed) + count($terms) === count($rules))) ? array_values(array_map(fn ($rule) => array_intersect_key($rule, ['param' => 0, 'operator' => 0, 'value' => 0]), $terms)) : [];

    }

    // ACF skips a required field its conditions hide. Conditions name fields by key and compare raw values; one on a field this level lacks counts as met.
    public function is_shown (Resource_Field $field, array $record) : bool {

        $conditions = $field->acf['conditional_logic'] ?? null;

        if (!is_array($conditions) || !$conditions) return true;

        $names = [];

        foreach ($this->get_fields() as $name => $each) if ($each->key) $names[$each->key] = $name;

        foreach ($conditions as $group) if (!array_filter($group, fn ($rule) => isset($names[$rule['field']]) && !$this->meets($rule, $record[$names[$rule['field']]] ?? null))) return true;

        return false;

    }

    protected function meets (array $rule, $value) : bool {

        $values = array_map('strval', is_array($value) ? $value : (in_array($value, [null, ''], true) ? [] : [$value]));
        $want   = (string) ($rule['value'] ?? '');

        return match ($rule['operator']) {
            '=='         => in_array($want, $values, true),
            '!='         => !in_array($want, $values, true),
            '==empty'    => !$values,
            '!=empty'    => (bool) $values,
            '==contains' => str_contains(implode(' ', $values), $want),
            '!=contains' => !str_contains(implode(' ', $values), $want),
            '<'          => $values && (float) $values[0] < (float) $want,
            '>'          => $values && (float) $values[0] > (float) $want,
            default      => true,
        };

    }

    // The layer's reads, diffs and journal rows are masked: a secret leaves as a keyed hash, so a change still shows.
    public function read ($model, bool $mask = true) : array {

        $values  = [];
        $readers = $this->get_computed();

        foreach ($this->get_fields_for($model) as $name => $field) {

            $getter = $field->acf['get'] ?? "get_{$name}";
            $value  = match ($field->source) {
                'model'  => ($getter instanceof \Closure) ? $getter($model) : $model->$getter(),
                'reader' => $readers[$name]($model),
                default  => $field->from_raw($model->get_field($name, false)),
            };

            $values[$name] = $mask ? $field->mask($value) : $value;

        }

        return $values;

    }

    public function mask (array $values, ?array $fields = null) : array {

        $fields ??= $this->get_fields();

        foreach ($values as $name => $value) if (isset($fields[$name])) $values[$name] = $fields[$name]->mask($value);

        return $values;

    }

    public function to_raw (array $values, ?array $fields = null) : array {

        $fields ??= $this->get_fields();
        $raw      = [];

        foreach ($values as $name => $value) if (($fields[$name] ?? null)?->source === 'acf') $raw[$name] = $fields[$name]->to_raw($value);

        return $raw;

    }

    public function stage_model_values ($model, array $values) : void {

        foreach ($values as $name => $value) if (($field = $this->get_field($name)) && $field->source === 'model') $model->{$field->acf['set'] ?? "set_{$name}"}($value);

    }

    public function get_unsaved_copy ($model, array $values) : object {

        $copy = $model->get_unsaved_copy($this->to_raw($values, $this->get_fields_for($model)));

        if (method_exists($copy, 'get_wp_post') && $copy->get_wp_post()) $copy->set_wp_post(clone $copy->get_wp_post());

        $this->stage_model_values($copy, $values);

        return $copy;

    }

    // ACF formats a sub-field from its cache whatever value it is given.
    public function forget_values ($model) : void {

        if (function_exists('acf_get_store') && ($id = $model->get_acf_id())) {

            $store = acf_get_store('values');

            foreach (array_keys((array) $store->get()) as $key) if (str_starts_with((string) $key, "{$id}:")) $store->remove($key);

        }

        unset($this->record_fields[$model->get_id()]);

        $model->forget_derived();

    }

    // Records

    public function find ($id) : ?object {

        $id = (int) $id;

        return ($id && get_post_type($id) === $this->get_post_type()) ? (static::$model_class)::get_instance($id) : null;

    }

    // status, search, page, per_page
    public function query (array $args) : array {

        $statuses = ['draft', 'pending', 'publish', 'future', 'private'];
        $status   = (string) ($args['status'] ?? '');

        return (static::$model_class)::query(array_filter([
            'post_status'    => in_array($status, $statuses, true) ? $status : $statuses,
            's'              => (string) ($args['search'] ?? ''),
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]));

    }

    public function make_model (array $values) : object {

        return (static::$model_class)::create(['post_status' => 'draft', 'post_title' => (string) ($values['title'] ?? '')]);

    }

    public function insert_model ($model) : object {

        $saved = $model->save(['post_author' => get_current_user_id()]);

        return is_wp_error($saved) ? $saved : (static::$model_class)::get_instance($model->get_id());

    }

    public function get_status ($model) : string {

        return (string) $model?->get_status();

    }

    public function summarise ($model) : array {

        return [
            'title'    => wp_specialchars_decode($model->get_title(), ENT_QUOTES),
            'status'   => $model->get_status(),
            'modified' => get_post_modified_time('c', true, $model->get_id()),
        ];

    }

    public function get_edit_url ($model) : string {

        return admin_url('post.php?action=edit&post=' . $model->get_id());

    }

    public function get_states ($model) : array {

        return ['status' => $model->get_status()];

    }

    // An extra capability updating needs in the record's current state.
    public function get_lock ($model) : ?string {

        return null;

    }

    // With a model, read and update are meta capabilities checked against it.
    public function get_capability (string $operation, $model = null) : string {

        $caps = get_post_type_object($this->get_post_type())?->cap;

        return match (true) {
            $model && ($operation === 'read')   => 'read_post',
            $model && ($operation === 'update') => 'edit_post',
            $operation === 'create'             => $caps?->create_posts ?? 'edit_posts',
            $operation === 'publish'            => $caps?->publish_posts ?? 'publish_posts',
            default                             => $caps?->edit_posts ?? 'edit_posts',
        };

    }

    public function can (string $operation, $model = null) : bool {

        $meta = $model && in_array($operation, ['read', 'update'], true);

        return current_user_can($this->get_capability($operation, $model), ...($meta ? [$model->get_id()] : []));

    }

    // Actions, views and consequences

    // name => ['label', 'capability', 'when' => fn ($model) => reason or '', 'input' => [name => type], 'sets' => [field => value], 'confirm', 'changes_record', 'undo'].
    public function get_actions () : array {

        return [];

    }

    public function get_views () : array {

        return [];

    }

    public function get_consequences (Resource_Change $change) : array {

        return [];

    }

    // Writing

    public function before_write ($model) : mixed {

        return null;

    }

    // Run what the model's acf/save_post handlers would.
    public function after_write ($model, mixed $before, string $why) : void {}

}
