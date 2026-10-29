<?php

namespace Lattice;

use WP_Error;

class Resources extends Service {

    const CONTROL  = ['version', 'why', 'dry_run', 'confirm'];
    const VIAS     = ['rest', 'cli', 'admin', 'other'];
    const PER_PAGE = 50;
    const RESERVED = ['guide', 'journal', 'undo', 'dev'];
    const STATUSES = ['invalid' => 400, 'forbidden' => 403, 'not_found' => 404, 'stale' => 409, 'locked' => 409];

    protected array $resources = [];
    protected array $writing   = [];

    public function register (Model_Resource $resource) : void {

        $class = $resource->get_model_class();
        $name  = $resource->get_name();

        if (!$name || in_array($name, static::RESERVED, true)) return;

        if (!method_exists($class, 'get_unsaved_copy') || !method_exists($class, 'claim_version')) {

            _doing_it_wrong(static::class . '::register', esc_html("{$class} needs Has_Unsaved_Copies and Has_Version to be a resource."), '');
            return;

        }

        $this->resources[$name] = $resource;

    }

    public function get_resource (string $name) : ?Model_Resource {

        return $this->resources[$name] ?? null;

    }

    /** @return Model_Resource[] by name */
    public function get_resources () : array {

        return $this->resources;

    }

    public function get_resource_for_post_type (string $post_type) : ?Model_Resource {

        foreach ($this->get_resources() as $resource) if ($post_type && ($resource->get_post_type() === $post_type)) return $resource;

        return null;

    }

    public function get_resource_for_page (string $slug) : ?Options_Page_Resource {

        foreach ($this->get_resources() as $resource) if (($resource instanceof Options_Page_Resource) && ($resource->get_page_slug() === $slug)) return $resource;

        return null;

    }

    // The save hooks stand aside while this holds; no id means any record.
    public function is_writing ($id = null) : bool {

        return is_null($id) ? (bool) $this->writing : isset($this->writing[(string) $id]);

    }

    // Reading

    public function guide () : array {

        return [
            'framework' => LATTICE_VERSION,
            'features'  => ['dry_run', 'journal', 'undo'],
            'resources' => array_values(array_map([$this, 'describe_resource'], array_filter(array_map([$this, 'get_resource'], array_keys($this->resources)), fn ($resource) => $resource && $this->may($resource, 'list')))),
        ];

    }

    protected function describe_resource (Model_Resource $resource) : array {

        return [
            'name'      => $resource->get_name(),
            'label'     => $resource->get_label(),
            'post_type' => $resource->get_post_type() ?: null,
            'fields'    => array_values(array_map(fn ($field) => $field->describe(), $resource->get_fields())),
            'actions'   => array_map(fn ($action) => array_filter([
                'label'          => $action['label'] ?? '',
                'input'          => $action['input'] ?? [],
                'confirm'        => !empty($action['confirm']),
                'changes_record' => $action['changes_record'] ?? true,
                'undo'           => $action['undo'] ?? null,
            ], fn ($value) => !in_array($value, ['', [], null, false], true)), $resource->get_actions()),
            'views'     => array_keys($resource->get_views()),
        ];

    }

    public function list (string $name, array $args = []) : array|WP_Error {

        if (!$resource = $this->get_resource($name)) return $this->error('not_found', "No resource {$name}.", 404);
        if (!$this->may($resource, 'list'))           return $this->forbidden($resource->get_capability('list'));

        $models = $resource->query(['per_page' => min(static::PER_PAGE, max(1, (int) ($args['per_page'] ?? static::PER_PAGE))), 'page' => max(1, (int) ($args['page'] ?? 1))] + $args);

        return array_map(fn ($model) => $this->summarise($resource, $model), $models);

    }

    public function get (string $name, $id) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;
        if ($refusal = $this->refuse_read($resource, $model)) return $refusal;

        return $this->describe_record($resource, $model);

    }

    public function view (string $name, $id, string $view) : mixed {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;
        if ($refusal = $this->refuse_read($resource, $model)) return $refusal;

        if (!$method = $resource->get_views()[$view] ?? null) return $this->error('not_found', "No view {$view}.", 404);

        return is_callable($method) ? $method($model) : $resource->$method($model);

    }

    public function journal (array $args = []) : array {

        $readable = array_values(array_filter(array_keys($this->resources), fn ($name) => ($resource = $this->get_resource($name)) && $this->may($resource, 'list')));

        if (!$readable) return [];

        return array_values(array_filter(Journal::inst()->query(['resources' => $readable] + $args), fn ($row) => !($model = $this->get_resource($row['resource'])->find($row['record_id'])) || !$this->refuse_read($this->get_resource($row['resource']), $model)));

    }

    protected function summarise (Model_Resource $resource, $model) : array {

        return ['id' => $model->get_id()] + $resource->summarise($model) + [
            'states'   => $resource->get_states($model),
            'version'  => $model->get_version(),
            'edit_url' => $resource->get_edit_url($model),
        ];

    }

    protected function describe_record (Model_Resource $resource, $model) : array {

        $actions = [];

        foreach ($resource->get_actions() as $name => $action) $actions[$name] = $this->refuse_action($resource, $model, $action);

        return $this->summarise($resource, $model) + [
            'fields'  => $resource->read($model),
            'actions' => array_map(fn ($reason) => $reason ? ['usable' => false, 'reason' => $reason] : ['usable' => true], $actions),
        ];

    }

    // Writing

    public function create_record (string $name, array $input, string $via) : array|WP_Error {

        if (!$resource = $this->get_resource($name)) return $this->error('not_found', "No resource {$name}.", 404);
        if (!$this->may($resource, 'create'))         return $this->forbidden($resource->get_capability('create'));

        [$values, $problems] = $this->take_values($resource, $input, null);

        array_push($problems, ...$this->check_why($input, $via), ...$this->check_required($resource, null, $values, 'draft'));

        if ($problems) return $this->invalid($problems);

        if ($refusal = $this->refuse_publish($resource, null, $values)) return $refusal;

        $model    = $resource->make_model($values);
        $change   = $this->build($resource, 'create', null, $resource->get_unsaved_copy($model, $values), $values, [], $input, $via);

        if (!empty($input['dry_run'])) return $change->describe();

        $this->writing['new'] = true;

        $model = $resource->insert_model($model);

        if (is_wp_error($model)) {

            unset($this->writing['new']);

            return $this->error('not_saved', 'It could not be created: ' . $model->get_error_message(), 500);

        }

        $result = $this->commit($resource, $model, $values, $change->why, $via, 'create', $model->get_version(), null);

        unset($this->writing['new']);

        return is_wp_error($result) ? $result : ['created' => true] + $result;

    }

    public function update (string $name, $id, array $input, string $via) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error)                       return $resource;
        if ($refusal = $this->refuse_update($resource, $model))  return $refusal;
        if ($refusal = $this->check_version($model, $input))     return $refusal;

        [$values, $problems] = $this->take_values($resource, $input, $model);

        array_push($problems, ...$this->check_why($input, $via), ...$this->check_required($resource, $model, $values + $resource->read($model), $resource->get_status($model)));

        if ($problems) return $this->invalid($problems);

        return $this->write_values($resource, $model, $values, $input, $via, 'update', (int) $input['version'], null);

    }

    public function act (string $name, $id, string $action, array $input, string $via, ?int $undoes = null) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;

        if (!($definition = $resource->get_actions()[$action] ?? null) || !method_exists($resource, "act_{$action}")) return $this->error('not_found', "No action {$action}.", 404);

        $changes_record = $definition['changes_record'] ?? true;

        if ($refusal = $changes_record ? $this->refuse_update($resource, $model) : $this->refuse_read($resource, $model)) return $refusal;

        if ($refusal = $this->refuse_record($resource, $model, $action)) return $refusal;

        if (isset($definition['capability']) && !current_user_can($definition['capability'])) return $this->forbidden($definition['capability']);
        if (!$this->may($resource, $action, $model))                                            return $this->forbidden($action);

        if ($reason = $this->refuse_action($resource, $model, $definition)) return $this->error('locked', $reason, 409, ['reason' => $reason]);

        if ($changes_record && ($refusal = $this->check_version($model, $input))) return $refusal;

        $values   = array_intersect_key($input, (array) ($definition['input'] ?? []));
        $problems = $this->check_action_input((array) ($definition['input'] ?? []), $values);

        if ($unknown = array_diff(array_keys($input), [...static::CONTROL, ...array_keys((array) ($definition['input'] ?? []))])) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';

        array_push($problems, ...$this->check_why($input, $via));

        if ($problems) return $this->invalid($problems);

        $sets   = (array) ($definition['sets'] ?? []);
        $change = $this->build($resource, $action, $model, $resource->get_unsaved_copy($model, $sets), $sets, $values, $input, $via);

        if (!empty($input['dry_run'])) return $change->describe();

        if (!empty($definition['confirm'])) {

            if (empty($input['confirm']))                                  return $this->invalid(['Send the fingerprint of its dry run as confirm.']);
            if (!hash_equals($change->fingerprint, (string) $input['confirm'])) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        }

        if ($changes_record && !$model->claim_version((int) $input['version'])) return $this->stale($model);

        $this->writing[(string) $model->get_id()] = true;

        $resource->forget_values($model);

        $before         = $resource->read($model);
        $version_before = (int) ($changes_record ? $input['version'] : $model->get_version());

        try {

            $result = $resource->{"act_{$action}"}($model, $values, $change);

        } catch (\Throwable $e) {

            $result = new WP_Error('not_saved', $e->getMessage());

        }

        if ($changes_record) $model->finish_version();

        $resource->forget_values($model);

        $made = (!is_wp_error($result) && is_object($result) && method_exists($result, 'get_id') && $result->get_id() !== $model->get_id()) ? $result : null;

        if ($made) $resource->forget_values($made);

        $after = $resource->read($made ?? $model);

        if (is_wp_error($result) && !$made && ($after === $before)) {

            unset($this->writing[(string) $model->get_id()]);

            $code = isset(static::STATUSES[$result->get_error_code()]) ? $result->get_error_code() : 'not_saved';

            return $this->error($code, $result->get_error_message(), static::STATUSES[$code] ?? 500);

        }

        $journaled = !$this->is_journaled($resource, $made ?? $model) ? null : Journal::inst()->write([
            'via'            => $via,
            'resource'       => $resource->get_name(),
            'record_id'      => ($made ?? $model)->get_id(),
            'action'         => $action,
            'why'            => $change->why,
            'before'         => $made ? null : $before,
            'after'          => $after,
            'version_before' => $made ? 0 : $version_before,
            'version_after'  => ($made ?? $model)->get_version(),
            'undoes'         => $undoes,
        ]);

        unset($this->writing[(string) $model->get_id()]);

        if (is_wp_error($result)) return $this->error('not_saved', 'It was not done: ' . $result->get_error_message() . ' Read it again before the next change.', 500);

        return ($made ? ['created' => true] : []) + $this->describe_record($resource, $made ?? $model) + ['changed' => true] + $this->report_journal($journaled);

    }

    public function undo (int $row_id, array $input, string $via) : array|WP_Error {

        if (!$row = Journal::inst()->find($row_id)) return $this->error('not_found', "No journal row {$row_id}.", 404);

        [$resource, $model] = $this->find($row['resource'], $row['record_id']);

        if ($resource instanceof WP_Error)          return $resource;
        if ($refusal = $this->refuse_read($resource, $model)) return $refusal;

        $moved = fn (string $since) => $this->stale($model, "{$since} changed after that row: undo the rows after it first.", ['since' => Journal::inst()->query(['resource' => $row['resource'], 'record_id' => $row['record_id'], 'since' => $row_id])]);

        // Any save raises the version, so an update row compares the fields it restores.
        if (in_array($row['action'], ['update', 'undo'], true) && is_array($row['before'])) {

            if ($refusal = $this->refuse_update($resource, $model)) return $refusal;

            $changed = array_filter($row['before'], fn ($value, $name) => $value !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH);
            $values  = $this->get_writable_values($resource, $model, $changed);

            if ($changed && !$values) return $this->error('locked', 'Only secrets changed, and the journal keeps none to restore.', 409, ['reason' => 'Secrets are not kept.']);

            $current = $resource->read($model);

            if ($changed = array_keys(array_filter($values, fn ($value, $name) => ($current[$name] ?? null) !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH))) return $moved(implode(', ', $changed));

            $problems = $this->check_why($input, $via);

            $fields = $resource->get_fields_for($model, $values);

            foreach ($values as $name => $value) if (!in_array($value, [null, '', []], true) || $resource->is_shown($fields[$name], $values + $current)) array_push($problems, ...$fields[$name]->check($value, $name, null, true));

            if ($problems) return $this->invalid($problems);

            return $this->write_values($resource, $model, $values, ['version' => $model->get_version()] + $input, $via, 'undo', $model->get_version(), $row_id);

        }

        if ($model->get_version() !== $row['version_after']) return $moved('It');

        if ($inverse = $resource->get_actions()[$row['action']]['undo'] ?? null) return $this->act($resource->get_name(), $model->get_id(), $inverse, ['version' => $model->get_version()] + array_intersect_key($input, array_flip([...static::CONTROL, ...array_keys((array) ($resource->get_actions()[$inverse]['input'] ?? []))])), $via, $row_id);

        return $this->error('locked', 'Nothing reverses this.', 409, ['reason' => 'Nothing reverses this.']);

    }

    // The steps of a write

    protected function write_values (Model_Resource $resource, $model, array $values, array $input, string $via, string $operation, int $seen, ?int $undoes) : array|WP_Error {

        if ($refusal = $this->refuse_locked($resource, $model, $values)) return $refusal;

        $change = $this->build($resource, $operation, $model, $resource->get_unsaved_copy($model, $values), $values, [], $input, $via);

        if (!empty($input['dry_run'])) return $change->describe();
        if (!$change->changes)         return $this->describe_record($resource, $model) + ['changed' => false];

        if ($refusal = $this->refuse_publish($resource, $model, $values)) return $refusal;

        if (!empty($input['confirm']) && !hash_equals($change->fingerprint, (string) $input['confirm'])) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        if (!$model->claim_version($seen)) return $this->stale($model);

        return $this->commit($resource, $model, $values, $change->why, $via, $operation, $seen, $undoes, true);

    }

    protected function commit (Model_Resource $resource, $model, array $values, string $why, string $via, string $operation, int $seen, ?int $undoes, bool $claimed = false) : array|WP_Error {

        if (!$claimed && !$model->claim_version($seen)) return $this->stale($model);

        $this->writing[(string) $model->get_id()] = true;

        $resource->forget_values($model);

        $before = ($operation === 'create') ? null : $resource->read($model);
        $error  = null;

        try {

            $snapshot = $resource->before_write($model);

            $fields = $resource->get_fields_for($model, $values);

            // By key: finding a field by name asks the saved record's groups, which a term in this write may change.
            foreach ($values as $name => $value) if ($fields[$name]->source === 'acf') $model->update_field($fields[$name]->key ?: $name, $value);

            $resource->stage_model_values($model, $values);

            if (is_wp_error($saved = $model->save())) throw new \RuntimeException($saved->get_error_message());

            $resource->after_write($model, $snapshot, $why);

        } catch (\Throwable $e) {

            $error = $e;

        }

        $model->finish_version();
        $resource->forget_values($model);

        $journaled = !$this->is_journaled($resource, $model) ? null : Journal::inst()->write([
            'via'            => $via,
            'resource'       => $resource->get_name(),
            'record_id'      => $model->get_id(),
            'action'         => $operation,
            'why'            => $why,
            'before'         => $before,
            'after'          => $resource->read($model),
            'version_before' => $seen,
            'version_after'  => $model->get_version(),
            'undoes'         => $undoes,
        ]);

        unset($this->writing[(string) $model->get_id()]);

        if ($error) return $this->error('not_saved', 'Part was written: ' . $error->getMessage() . ' Read it again before the next change.', 500);

        return $this->describe_record($resource, $model) + ['changed' => true] + $this->report_journal($journaled);

    }

    public function is_journaled (Model_Resource $resource, $model) : bool {

        return (bool) apply_filters('lattice.resources.journal', true, $resource, $model);

    }

    protected function report_journal (?int $row_id) : array {

        if ($row_id !== 0) return [];

        error_log('Lattice resources: a change was saved without its journal row.');

        return ['journaled' => false];

    }

    protected function build (Model_Resource $resource, string $operation, ?object $before, object $after, array $values, array $action_input, array $input, string $via) : Resource_Change {

        $change = new Resource_Change($resource, $operation, $before, $after, $values, $action_input, trim((string) ($input['why'] ?? '')), $via);

        $was = $before ? array_intersect_key($resource->read($before), $values) : [];

        $change->changes      = $this->diff($was, $resource->mask($values, $resource->get_fields_for($before, $values)));
        $change->consequences = $resource->get_consequences($change);
        $change->fingerprint  = md5((string) wp_json_encode([$change->changes, $change->consequences]));

        return $change;

    }

    public function diff (array $before, array $after) : array {

        $old = $this->flatten($before);
        $new = $this->flatten($after);

        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $path) if (($old[$path] ?? '') !== ($new[$path] ?? '')) $changes[] = ['field' => $path, 'before' => $old[$path] ?? null, 'after' => $new[$path] ?? null];

        return $changes;

    }

    protected function flatten (array $values, string $prefix = '') : array {

        $flat = [];

        foreach ($values as $key => $value) {

            $path = $prefix . $key;

            if (is_array($value) && $value) $flat += $this->flatten($value, "{$path}.");
            else $flat[$path] = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? '' : (string) $value);

        }

        return $flat;

    }

    // Checks

    protected function find (string $name, $id) : array {

        if (!$resource = $this->get_resource($name)) return [$this->error('not_found', "No resource {$name}.", 404), null];
        if (!$model = $resource->find($id))          return [$this->error('not_found', "No {$resource->get_label()} {$id}.", 404), null];

        return [$resource, $model];

    }

    protected function may (Model_Resource $resource, string $operation, $model = null) : bool {

        if (!get_current_user_id()) return false;

        if (in_array($operation, ['list', 'read', 'create', 'update', 'publish'], true) && !$resource->can($operation, $model)) return false;

        return (bool) apply_filters('lattice.resources.allow', true, wp_get_current_user(), $resource->get_name(), $operation);

    }

    protected function refuse_update (Model_Resource $resource, $model) : ?WP_Error {

        if (!$this->may($resource, 'update', $model)) return $this->forbidden($resource->get_capability('update', $model));

        if (($lock = $resource->get_lock($model)) && !current_user_can($lock)) return $this->forbidden($lock, 'In this state it changes only for an account with ' . $lock . '.');

        return $this->refuse_record($resource, $model, 'update');

    }

    // Another feature's say over one record: a reason refuses it.
    protected function refuse_record (Model_Resource $resource, $model, string $operation) : ?WP_Error {

        $reason = (string) apply_filters('lattice.resources.refuse', '', $resource, $model, $operation);

        return $reason ? $this->error('locked', $reason, 409, ['reason' => $reason]) : null;

    }

    protected function refuse_locked (Model_Resource $resource, $model, array $values) : ?WP_Error {

        if (!$locked = array_intersect_key($resource->get_locked_fields($model), $values)) return null;

        $reason = implode(' ', array_map(fn ($name, $why) => "{$name}: {$why}", array_keys($locked), $locked));

        return $this->error('locked', $reason, 409, ['reason' => $reason, 'fields' => array_keys($locked)]);

    }

    protected function refuse_read (Model_Resource $resource, $model) : ?WP_Error {

        return $this->may($resource, 'read', $model) ? $this->refuse_record($resource, $model, 'read') : $this->forbidden($resource->get_capability('read', $model));

    }

    protected function refuse_publish (Model_Resource $resource, $model, array $values) : ?WP_Error {

        if (($values['status'] ?? null) !== 'publish' || $resource->get_status($model) === 'publish') return null;

        return $this->may($resource, 'publish') ? null : $this->forbidden($resource->get_capability('publish'));

    }

    protected function refuse_action (Model_Resource $resource, $model, array $action) : string {

        if (isset($action['capability']) && !current_user_can($action['capability'])) return "This account lacks {$action['capability']}.";

        return isset($action['when']) ? (string) ($action['when'])($model) : '';

    }

    protected function check_version ($model, array $input) : ?WP_Error {

        if (!isset($input['version']) || !is_numeric($input['version'])) return $this->invalid(['Send the version you last read.']);

        return ((int) $input['version'] === $model->get_version()) ? null : $this->stale($model);

    }

    protected function check_why (array $input, string $via) : array {

        return (empty($input['dry_run']) && in_array($via, ['rest', 'cli'], true) && trim((string) ($input['why'] ?? '')) === '') ? ['Say why: every change through the API carries a why.'] : [];

    }

    protected function take_values (Model_Resource $resource, array $input, $model) : array {

        $values   = array_diff_key($input, array_flip(static::CONTROL));
        $problems = [];
        $current  = $model ? $resource->read($model, false) : [];
        $shown    = $resource->get_fields_for($model, $values);
        $known    = $resource->get_fields() + $shown;

        if ($unknown = array_diff(array_keys($values), array_keys($known))) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';
        if ($hidden = array_diff(array_keys(array_intersect_key($values, $known)), array_keys($shown))) $problems[] = 'Not shown for this record (see applies_when): ' . implode(', ', $hidden) . '.';

        foreach (array_intersect_key($values, $shown) as $name => $value) $values[$name] = $shown[$name]->unmask($value, $current[$name] ?? null);

        foreach (array_intersect_key($values, $shown) as $name => $value) {

            $field = $shown[$name];

            if (in_array($value, [null, '', []], true) && !$resource->is_shown($field, $values + $current)) continue;

            array_push($problems, ...$field->check($value, $name, $current[$name] ?? null));

        }

        return [array_intersect_key($values, $shown), $problems];

    }

    // ACF lets drafts go without their required fields.
    protected function check_required (Model_Resource $resource, $model, array $record, string $status) : array {

        if (($record['status'] ?? $status) !== 'publish') return [];

        // A new record has no terms, so no term-gated group applies to it yet.
        $missing = array_filter($resource->get_fields_for($model, $record), fn ($field) => ($model || empty($field->acf['applies_when'])) && $field->required && !$field->computed && !$field->action_only && in_array($record[$field->name] ?? null, [null, '', []], true) && $resource->is_shown($field, $record));

        return array_map(fn ($name) => "{$name} is required to publish.", array_keys($missing));

    }

    // Repeaters keep their computed sub-fields, so undo restores row keys.
    protected function get_writable_values (Model_Resource $resource, $model, array $values) : array {

        $fields = $resource->get_fields_for($model, $values);

        return array_filter($values, fn ($value, $name) => ($field = $fields[$name] ?? null) && !$field->computed && !$field->action_only && !$field->is_secret(), ARRAY_FILTER_USE_BOTH);

    }

    protected function check_action_input (array $types, array $values) : array {

        $problems = [];

        foreach ($types as $name => $type) {

            $value = $values[$name] ?? null;

            if (in_array($value, [null, ''], true)) $problems[] = "{$name} is required.";
            elseif ($type === 'email' && !is_email((string) $value)) $problems[] = "{$name} is an email address.";
            elseif ($type === 'int' && !is_numeric($value))          $problems[] = "{$name} is a number.";
            elseif ($type === 'string' && !is_string($value))        $problems[] = "{$name} is text.";

        }

        return $problems;

    }

    // Refusals

    protected function error (string $code, string $message, int $status, array $data = []) : WP_Error {

        return new WP_Error($code, $message, ['status' => $status] + $data);

    }

    protected function invalid (array $problems) : WP_Error {

        return $this->error('invalid', 'Nothing was changed: ' . implode(' ', $problems), 400, ['problems' => array_values($problems)]);

    }

    protected function forbidden (string $needs, string $message = 'This account may not do that.') : WP_Error {

        return $this->error('forbidden', $message, 403, ['needs' => $needs]);

    }

    protected function stale ($model, string $message = 'It changed since you read it: read it again, then send the change with its new version.', array $data = []) : WP_Error {

        return $this->error('stale', $message, 409, ['version' => $model->get_version()] + $data);

    }

}
