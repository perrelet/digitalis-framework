<?php

namespace Digitalis;

abstract class Model_Resource extends Singleton {

    protected static $name        = '';
    protected static $model_class = '';
    protected static $label       = '';

    const ACTION_ONLY = [];

    const SECRET = [];

    // Beyond updates and creates, what may be proposed on it: 'trash', 'untrash' and action names, each with 'confirm' where a call must carry its dry run's fingerprint.
    const PROPOSABLE = [];

    // Of get_post_fields(): title, status, content, excerpt, featured_image, terms.
    const POST_FIELDS = ['title', 'status'];

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

    // Through get_{name}() and set_{name}(), or the methods or closures 'get' and 'set' give. A dry run calls the setter: it must only stage.
    public function get_model_fields () : array {

        return array_intersect_key($this->get_post_fields(), array_flip(static::POST_FIELDS));

    }

    public function get_post_fields () : array {

        $taxonomies = get_object_taxonomies($this->get_post_type());

        return [
            'title'          => ['type' => 'text', 'label' => 'Title', 'get' => fn ($post) => $post->get_wp_post()->post_title],
            'status'         => ['type' => 'select', 'label' => 'Status', 'choices' => ['draft' => 'Draft', 'publish' => 'Published']],
            'content'        => ['type' => 'wysiwyg', 'label' => 'Content', 'get' => fn ($post) => $post->get_wp_post()->post_content],
            'excerpt'        => ['type' => 'textarea', 'label' => 'Excerpt', 'get' => fn ($post) => $post->get_wp_post()->post_excerpt],
            'featured_image' => ['type' => 'image', 'label' => 'Featured image', 'get' => fn ($post) => (int) $post->get_thumbnail_id(), 'set' => fn ($post, $id) => $post->stage_thumbnail_id((int) $id)],
            'terms'          => ['type' => 'terms', 'label' => 'Terms', 'taxonomies' => $taxonomies, 'validate' => fn ($terms, $path) => $this->check_terms($terms, $path, $taxonomies),
                                 'get' => fn ($post) => array_combine($taxonomies, array_map(fn ($taxonomy) => $post->get_term_ids($taxonomy), $taxonomies)),
                                 'set' => function ($post, $terms) { foreach ($terms as $taxonomy => $ids) $post->stage_terms((array) $ids, $taxonomy); }],
        ];

    }

    // taxonomy => term ids; taxonomies left out keep their terms.
    protected function check_terms ($terms, string $path, array $taxonomies) : array {

        if (!is_array($terms) || ($terms && array_is_list($terms))) return ["{$path} is an object of " . implode(', ', $taxonomies) . '.'];

        $problems = ($unknown = array_diff(array_keys($terms), $taxonomies)) ? ["{$path} has no " . implode(', ', $unknown) . '.'] : [];

        foreach (array_intersect_key($terms, array_flip($taxonomies)) as $taxonomy => $ids) foreach ((array) $ids as $id) if (!is_numeric($id) || !term_exists((int) $id, $taxonomy)) $problems[] = "{$path}.{$taxonomy} has no term {$id}.";

        return $problems;

    }

    // name => why
    public function get_locked_fields ($model) : array {

        return [];

    }

    /** @return Resource_Field[] by name: every field a record of this type can show. */
    public function get_fields () : array {

        return $this->fields ??= $this->build_fields($this->get_type_groups());

    }

    /** @return Resource_Field[] by name: the fields this record shows, with $values' terms when it brings some. */
    public function get_fields_for ($model, array $values = []) : array {

        if (!$model?->get_id() || !function_exists('acf_get_field_groups')) return $this->get_fields();

        $screen = ['post_id' => $model->get_id()] + $this->get_acf_screen();

        if ($this->get_field('terms')?->source === 'model') {

            if (is_array($values['terms'] ?? null)) return $this->build_fields(acf_get_field_groups($screen + ['post_terms' => $values['terms'] + $this->read_terms($model)]));
            if ($model->is_unsaved_copy())         return $this->build_fields(acf_get_field_groups($screen + ['post_terms' => $this->read_terms($model)]));

        }

        return $this->record_fields[$model->get_id()] ??= $this->build_fields(acf_get_field_groups($screen));

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

        $groups = array_column(acf_get_field_groups($this->get_acf_screen()), null, 'key');

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
                default  => $field->from_raw(($field->is_flexible() && !$model->has_unsaved_value($name)) ? $field->read_rows($model->get_acf_id()) : $model->get_field($name, false)),
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

    // Values held for later, whoever reads them: a resource that hides a secret from an account must not let it through raw.
    public function mask_held (array $values, $model = null) : array {

        // A field only this record shows is still a field, and masking it whole would make every start a conflict.
        $fields = $this->get_fields_for($model, $values) + $this->get_fields();

        foreach ($values as $name => $value) {

            $field = $fields[$name] ?? null;

            // Rows are masked leaf by leaf, which only reaches what is shaped like rows.
            $values[$name] = (!$field || in_array($name, static::SECRET, true) || ($field->holds_secret() && !is_array($value))) ? Resource_Field::mask_value($value) : $field->mask($value);

        }

        return $values;

    }

    public function to_raw (array $values, ?array $fields = null) : array {

        $fields ??= $this->get_fields();
        $raw      = [];

        foreach ($values as $name => $value) if (($fields[$name] ?? null)?->source === 'acf') $raw[$name] = $fields[$name]->to_raw($value);

        return $raw;

    }

    // For people: labels, rows one by one, names for what ids point at.
    public function present_changes (array $before, array $after, $model = null) : array {

        $lines = [];

        foreach (array_intersect_key($this->get_fields_for($model, $after) + $this->get_fields(), $before + $after) as $name => $field) array_push($lines, ...$field->present_change($before[$name] ?? null, $after[$name] ?? null));

        return (array) apply_filters('lattice.resources.present', $lines, $this, $before, $after, $model);

    }

    public function stage_model_values ($model, array $values) : void {

        foreach ($values as $name => $value) {

            if (($field = $this->get_field($name))?->source !== 'model') continue;

            $setter = $field->acf['set'] ?? "set_{$name}";

            ($setter instanceof \Closure) ? $setter($model, $value) : $model->$setter($value);

        }

    }

    protected function read_terms ($model) : array {

        $getter = $this->get_field('terms')->acf['get'];

        return $getter($model);

    }

    public function get_unsaved_copy ($model, array $values) : object {

        $copy = $model->get_unsaved_copy($this->to_raw($values, $this->get_fields_for($model, $values)));

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

    // Storage

    public function has_trash () : bool {

        return (bool) $this->get_post_type();

    }

    // Answers whether it landed: a site's wp_insert_post_data may keep the status after WordPress has changed the trash meta, which then goes back.
    public function move ($model, string $target) : bool {

        $id       = $model->get_id();
        $trashing = $target === 'trash';
        $meta     = array_map(fn ($key) => get_post_meta($id, $key, true), array_combine(Resources::TRASH_META, Resources::TRASH_META));
        $keep     = fn ($new_status, $post_id) => ((int) $post_id === (int) $id) ? $target : $new_status;

        if (!$trashing) add_filter('wp_untrash_post_status', $keep, PHP_INT_MAX, 2);

        try {

            $trashing ? wp_trash_post($id) : wp_untrash_post($id);

        } finally {

            if (!$trashing) remove_filter('wp_untrash_post_status', $keep, PHP_INT_MAX);

            $this->clear_record_cache($id);

            if (!$this->has_moved($model, $target)) foreach ($meta as $key => $value) $trashing ? delete_post_meta($id, $key) : (($value !== '') && update_post_meta($id, $key, $value));

        }

        return $this->has_moved($model, $target);

    }

    public function has_moved ($model, string $target) : bool {

        $status = get_post_status($model->get_id());

        return ($target === 'trash') ? ($status === 'trash') : ($status !== 'trash');

    }

    public function get_previous_status ($model) : string {

        return (string) get_post_meta($model->get_id(), Resources::TRASH_META[0], true) ?: 'draft';

    }

    // Where required fields start to apply; null for never.
    public function get_required_status () : ?string {

        return 'publish';

    }

    public function clear_record_cache ($id) : void {

        if ($this->get_post_type() && is_numeric($id)) clean_post_cache((int) $id);

    }

    public function get_acf_screen () : array {

        return ['post_type' => $this->get_post_type()];

    }

    public function owns_record ($id) : bool {

        return $this->get_post_type() && is_numeric($id) && (get_post_type((int) $id) === $this->get_post_type());

    }

    public function forget_set_key ($model) : void {

        if ($this->get_post_type()) delete_post_meta($model->get_id(), Resources::SET_KEY);

    }

    // The record a set's create step made. Directly, since post_status 'any' leaves out the Trash and exclude_from_search statuses.
    public function find_by_set_key (string $key) : ?object {

        global $wpdb;

        if (!$this->get_post_type()) return null;

        foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", Resources::SET_KEY, $key)) as $id) if ($model = $this->find((int) $id)) return $model;

        return null;

    }

    // What another request may have written since this one last looked.
    public function refresh ($model) : void {

        $this->clear_record_cache($model->get_id());

        if (method_exists($model, 'reload')) $model->reload();

        $this->forget_values($model);

    }

    // status, search, page, per_page
    public function query (array $args) : array {

        $statuses = ['draft', 'pending', 'publish', 'future', 'private'];
        $status   = (string) ($args['status'] ?? '');

        return (static::$model_class)::query(apply_filters('lattice.resources.query', array_filter([
            'post_status'    => in_array($status, $statuses, true) ? $status : $statuses,
            's'              => (string) ($args['search'] ?? ''),
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]), $this));

    }

    public function make_model (array $values) : object {

        return (static::$model_class)::create(['post_status' => 'draft', 'post_title' => (string) ($values['title'] ?? '')]);

    }

    // $meta goes in with the insert, so its save_post listeners already find it.
    public function insert_model ($model, array $meta = []) : object {

        $saved = $model->save(['post_author' => get_current_user_id()] + ($meta ? ['meta_input' => $meta] : []));

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

    // Named conditions a credential's rule may hold a record to, each asked of the record and the values it would hold. A trashed record is judged by the status it had before.
    public function get_conditions () : array {

        if (!$this->get_post_type()) return [];

        $status = function ($model, array $values) : string {

            $status = (string) ($values['status'] ?? ($model ? $this->get_status($model) : 'draft'));

            return (($status === 'trash') && $model) ? (string) get_post_meta($model->get_id(), '_wp_trash_meta_status', true) : $status;

        };

        return [
            'draft'     => fn ($model, array $values) => in_array($status($model, $values), ['draft', 'pending', 'auto-draft'], true),
            'published' => fn ($model, array $values) => $status($model, $values) === 'publish',
            'private'   => fn ($model, array $values) => $status($model, $values) === 'private',
        ];

    }

    // What a credential's rule asks of a record, as it is or as it would be with $values; a record being made is the account's own. $user_id: whose own it is asked for, the current account by default.
    public function get_facts ($model, array $values = [], ?int $user_id = null) : array {

        $conditions = $this->get_conditions();

        return [
            'id'         => $model?->get_id(),
            'own'        => $model ? ($this->get_post_type() && ((int) get_post_field('post_author', $model->get_id()) === ($user_id ?? get_current_user_id()))) : true,
            'conditions' => fn (string $name) => isset($conditions[$name]) && (bool) $conditions[$name]($model, $values),
        ];

    }

    public function get_edit_url ($model) : string {

        return admin_url('post.php?action=edit&post=' . $model->get_id());

    }

    public function get_states ($model) : array {

        return ['status' => $model->get_status()];

    }

    // Reached only by rules naming it, never by resource:* (SCOPED_CREDENTIALS.md A0): a resource over accounts.
    public function is_named_only () : bool {

        return false;

    }

    // Which field each part of a summary shows, so a summary leaves out what a credential may not read.
    public function get_summary_fields () : array {

        return ['title' => 'title', 'status' => 'status'];

    }

    // The record before a write, as a credential's rule asks it: by default as it is. $values: what the write sends.
    public function get_before_facts ($model, array $values) : array {

        return $this->get_facts($model);

    }

    // What a list's search reads, so a credential barred from one of them cannot search.
    public function get_search_fields () : array {

        return ['title', 'content', 'excerpt'];

    }

    public function get_proposable () : array {

        return ['update' => [], 'create' => []] + static::PROPOSABLE;

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
            $model && in_array($operation, ['trash', 'untrash'], true) => 'delete_post',
            $operation === 'create'             => $caps?->create_posts ?? 'edit_posts',
            $operation === 'publish'            => $caps?->publish_posts ?? 'publish_posts',
            default                             => $caps?->edit_posts ?? 'edit_posts',
        };

    }

    public function can (string $operation, $model = null) : bool {

        $meta = $model && in_array($operation, ['read', 'update', 'trash', 'untrash'], true);

        return current_user_can($this->get_capability($operation, $model), ...($meta ? [$model->get_id()] : []));

    }

    // Actions, views and consequences

    // name => ['label', 'capability', 'when' => fn ($model) => reason or '', 'input' => [name => type], 'validate' => fn ($model, array $input) => problems, 'sets' => [field => value], 'confirm', 'changes_record', 'undo'].
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

    // Before a write that will happen, after its refusals: a WP_Error refuses it. Each pairs with after_operation() only when this answered null.
    public function before_operation ($model, string $operation) : ?\WP_Error {

        return null;

    }

    public function after_operation ($model, string $operation) : void {}

    // What this resource's own rows carry beyond the journal's columns: the journal's marks.
    public function journal_marks ($model) : array {

        return [];

    }

}
