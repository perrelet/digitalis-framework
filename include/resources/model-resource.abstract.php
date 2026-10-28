<?php

namespace Digitalis;

abstract class Model_Resource extends Singleton {

    protected static $name        = '';
    protected static $model_class = '';
    protected static $label       = '';

    const ACTION_ONLY = [];

    const POST_FIELDS = ['title' => 'post_title', 'status' => 'post_status'];

    protected $fields;
    protected $post_type;

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

    public function get_declared_fields () : array {

        return [];

    }

    public function is_available () : bool {

        return (bool) $this->get_declared_fields() || function_exists('acf_get_field_groups');

    }

    /** @return Resource_Field[] by name */
    public function get_fields () : array {

        if (!is_null($this->fields)) return $this->fields;

        $fields = [
            'title'  => new Resource_Field('title', 'text', source: 'post', label: 'Title'),
            'status' => new Resource_Field('status', 'select', source: 'post', label: 'Status', choices: ['draft' => 'Draft', 'publish' => 'Published']),
        ];

        foreach ($this->get_declared_fields() ?: $this->get_acf_fields() as $field) $fields[$field->name] = $field;

        $computed = array_filter($this->get_computed(), fn ($reader, $path) => is_callable($reader) && !str_contains($path, '.') && !isset($fields[$path]), ARRAY_FILTER_USE_BOTH);

        foreach (array_keys($computed) as $name) $fields[$name] = new Resource_Field($name, 'computed', source: 'reader', computed: true);

        return $this->fields = $fields;

    }

    public function get_field (string $name) : ?Resource_Field {

        return $this->get_fields()[$name] ?? null;

    }

    protected function get_acf_fields () : array {

        if (!function_exists('acf_get_field_groups')) return [];

        $fields = [];

        foreach (acf_get_field_groups(['post_type' => $this->get_post_type()]) as $group) foreach ((array) acf_get_fields($group) as $field) if (($field['name'] ?? '') !== '') $fields[] = Resource_Field::from_acf($field, array_keys($this->get_computed()), static::ACTION_ONLY);

        return $fields;

    }

    public function read ($model) : array {

        $values  = [];
        $readers = $this->get_computed();

        foreach ($this->get_fields() as $name => $field) $values[$name] = match ($field->source) {
            'post'   => ($name === 'title') ? $model->get_title() : $model->get_status(),
            'reader' => $readers[$name]($model),
            default  => $field->from_raw($model->get_field($name, false)),
        };

        return $values;

    }

    public function to_raw (array $values) : array {

        $raw = [];

        foreach ($values as $name => $value) if (($field = $this->get_field($name)) && $field->source === 'acf') $raw[$name] = $field->to_raw($value);

        return $raw;

    }

    public function get_unsaved_copy ($model, array $values) : object {

        $copy = $model->get_unsaved_copy($this->to_raw($values));

        if (!$post = array_intersect_key($values, static::POST_FIELDS)) return $copy;

        $wp_post = clone $copy->get_wp_post();

        foreach ($post as $name => $value) $wp_post->{static::POST_FIELDS[$name]} = $value;

        return $copy->set_wp_post($wp_post);

    }

    // ACF formats a sub-field from its cache whatever value it is given.
    public function forget_values ($model) : void {

        if (function_exists('acf_get_store') && ($id = $model->get_acf_id())) {

            $store = acf_get_store('values');

            foreach (array_keys((array) $store->get()) as $key) if (str_starts_with((string) $key, "{$id}:")) $store->remove($key);

        }

        $model->forget_derived();

    }

    // Records

    public function find ($id) : ?object {

        $id = (int) $id;

        return ($id && get_post_type($id) === $this->get_post_type()) ? (static::$model_class)::get_instance($id) : null;

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

    public function get_capability (string $operation, $model = null) : string {

        $caps = get_post_type_object($this->get_post_type())?->cap;

        return match ($operation) {
            'list'    => $caps?->edit_posts ?? 'edit_posts',
            'create'  => $caps?->create_posts ?? 'edit_posts',
            'publish' => $caps?->publish_posts ?? 'publish_posts',
            default   => $caps?->edit_posts ?? 'edit_posts',
        };

    }

    public function can (string $operation, $model = null) : bool {

        if ($model && in_array($operation, ['read', 'update'], true)) return current_user_can(($operation === 'read') ? 'read_post' : 'edit_post', $model->get_id());

        return current_user_can($this->get_capability($operation, $model));

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
