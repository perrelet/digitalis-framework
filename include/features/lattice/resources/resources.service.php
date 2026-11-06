<?php

namespace Digitalis;

use WP_Error;

class Resources extends Service {

    const CONTROL  = ['version', 'why', 'dry_run', 'confirm'];
    const VIAS     = ['rest', 'cli', 'admin', 'other', 'cron'];
    const PER_PAGE = 50;
    const RESERVED = ['guide', 'journal', 'undo', 'dev', 'jobs'];
    const TRASH_META = ['_wp_trash_meta_status', '_wp_trash_meta_time'];
    const SET_KEY  = '_lattice_set_key';
    const WHY_BYTES = 65535;
    // A post column and the kses context its own save filters it with.
    const COLUMNS  = ['title' => 'title_save_pre', 'content' => 'post', 'excerpt' => 'post'];
    const STATUSES = ['invalid' => 400, 'forbidden' => 403, 'not_found' => 404, 'stale' => 409, 'locked' => 409];

    protected array $resources = [];
    protected array $writing   = [];
    protected ?array $walk     = null;

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

    // The save hooks stand aside while this holds: an id is a post's, and no id means any record.
    public function is_writing ($id = null) : bool {

        return is_null($id) ? (bool) $this->writing : isset($this->writing[(string) $id]);

    }

    // A record with no post type shares no post's hooks, though it may share its number.
    protected function writing_key (Model_Resource $resource, $model) : string {

        return $resource->get_post_type() ? (string) $model->get_id() : "{$resource->get_name()}:{$model->get_id()}";

    }

    // Reading

    public function guide () : array {

        return [
            'framework' => DIGITALIS_FRAMEWORK_VERSION,
            'features'  => ['dry_run', 'journal', 'undo', 'trash', 'expected'],
            'resources' => array_values(array_map([$this, 'describe_resource'], array_filter(array_map([$this, 'get_resource'], array_keys($this->resources)), fn ($resource) => $resource && $this->may($resource, 'list')))),
        ];

    }

    protected function describe_resource (Model_Resource $resource) : array {

        return apply_filters('lattice.resources.describe', [
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
        ], $resource);

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

        // A feature may answer for the rows of one of its own sets, as "resource:id" for each record they may be on.
        $named = isset($args['set']) ? array_flip((array) apply_filters('lattice.resources.journal_set', [], (int) $args['set'])) : [];

        if (!$readable && !$named) return [];

        $rows = Journal::inst()->query(($named ? [] : ['resources' => $readable]) + $args);

        return array_values(array_filter($rows, function ($row) use ($readable, $named) {

            if (isset($named["{$row['resource']}:{$row['record_id']}"])) return true;

            if (!in_array($row['resource'], $readable, true)) return false;

            $resource = $this->get_resource($row['resource']);

            // A post that has gone can still be read about. A record with no post type that does not read may only have failed to.
            if (!$model = $resource->find($row['record_id'])) return (bool) $resource->get_post_type();

            return !$this->refuse_read($resource, $model);

        }));

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

        foreach ($resource->get_actions() as $name => $action) $actions[$name] = $this->may($resource, $name, $model) ? $this->refuse_action($resource, $model, $action) : 'This account may not do that.';

        return $this->summarise($resource, $model) + [
            'fields'  => $resource->read($model),
            'actions' => array_map(fn ($reason) => $reason ? ['usable' => false, 'reason' => $reason] : ['usable' => true], $actions),
        ];

    }

    // Writing

    public function create_record (string $name, array $input, string $via) : array|WP_Error {

        if (!$resource = $this->get_resource($name)) return $this->error('not_found', "No resource {$name}.", 404);
        if (!$this->may($resource, 'create'))         return $this->forbidden($resource->get_capability('create'));

        [$values, $problems, $removed] = $this->take_values($resource, $input, null);

        array_push($problems, ...$this->check_why($input, $via), ...$this->check_required($resource, null, $values, 'draft'));

        if ($problems) return $this->invalid($problems);

        if ($refusal = $this->refuse_locked($resource, null, $values)) return $refusal;
        if ($refusal = $this->refuse_publish($resource, null, $values)) return $refusal;

        $model    = $resource->make_model($values);
        $change   = $this->build($resource, 'create', null, $resource->get_unsaved_copy($model, $values), $values, [], $input, $via, $removed);

        if (!empty($input['dry_run'])) return $change->describe();

        if ($this->misses_confirm($change, $input)) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        $this->writing['new'] = true;

        try {

            $model = $resource->insert_model($model, $this->take_create_meta());

            if (is_wp_error($model)) return $this->error('not_saved', 'It could not be created: ' . $model->get_error_message(), 500);

            $result = $this->commit($resource, $model, $values, $change->why, $via, 'create', $model->get_version(), null);

        } catch (\Throwable $e) {

            // A listener may throw after the row exists, so running it again could make a second.
            $result = new WP_Error('not_saved', $e->getMessage());

        } finally {

            unset($this->writing['new']);

            if (!is_wp_error($model) && $model->get_id()) unset($this->writing[$this->writing_key($resource, $model)]);

        }

        // The record exists by now, whatever went wrong after: running it again would make a second.
        if (is_wp_error($result)) return $this->error('not_saved', 'Part was written: ' . $result->get_error_message() . ' Read it again before the next change.', 500, array_filter(['partial' => true, 'id' => $model->get_id()]));

        return ['created' => true] + $result + array_filter(['removed' => $removed]);

    }

    public function update (string $name, $id, array $input, string $via) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error)                       return $resource;
        if ($refusal = $this->refuse_update($resource, $model))  return $refusal;
        if ($refusal = $this->refuse_trashed($resource, $model)) return $refusal;

        $expected = $input['expected'] ?? null;

        unset($input['expected']);

        if ((is_null($expected) || isset($input['version'])) && ($refusal = $this->check_version($model, $input))) return $refusal;

        // Before any value is read: a rival write landing after this makes the claim fail.
        $seen = (int) ($input['version'] ?? $model->get_version());

        if (!is_null($expected)) {

            if (method_exists($model, 'reload')) $model->reload();

            $resource->forget_values($model);

        }

        [$values, $problems, $removed] = $this->take_values($resource, $input, $model);

        array_push($problems, ...$this->check_why($input, $via), ...$this->check_required($resource, $model, $values + $resource->read($model), $resource->get_status($model)));

        if (!is_null($expected)) array_push($problems, ...$this->check_expected($expected, $input));

        if ($problems) return $this->invalid($problems);

        if (!is_null($expected)) return $this->write_expected($resource, $model, $values, $expected, $input, $via, $removed, $seen);

        return $this->write_values($resource, $model, $values, $input, $via, 'update', (int) $input['version'], null, $removed);

    }

    public function act (string $name, $id, string $action, array $input, string $via, ?int $undoes = null) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;

        if (in_array($action, ['trash', 'untrash'], true) || !($definition = $resource->get_actions()[$action] ?? null) || !method_exists($resource, "act_{$action}")) return $this->error('not_found', "No action {$action}.", 404);

        $through        = !empty($definition['writes_through']);
        $changes_record = $definition['changes_record'] ?? true;

        if ($refusal = ($changes_record || $through) ? $this->refuse_update($resource, $model, $action) : $this->refuse_read($resource, $model)) return $refusal;

        if (!$this->may($resource, $action, $model)) return $this->forbidden($action);

        if ($refusal = $this->refuse_record($resource, $model, $action)) return $refusal;

        if ($refusal = $this->refuse_trashed($resource, $model)) return $refusal;

        if (isset($definition['capability']) && !current_user_can($definition['capability'])) return $this->forbidden($definition['capability']);

        if ($reason = $this->refuse_action($resource, $model, $definition)) return $this->error('locked', $reason, 409, ['reason' => $reason]);

        if ($changes_record && !$through && ($refusal = $this->check_version($model, $input))) return $refusal;

        $types    = (array) ($definition['input'] ?? []);
        $input    = $this->decode_action_input($types, $input);
        $values   = array_intersect_key($input, $types);
        $problems = $this->check_action_input($types, $values);

        if ($unknown = array_diff(array_keys($input), [...static::CONTROL, ...array_keys($types)])) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';

        array_push($problems, ...$this->check_why($input, $via));

        if (!$problems && isset($definition['validate'])) array_push($problems, ...$this->check_action_rule($definition['validate'], $model, $values));

        if ($problems) return $this->invalid($problems);

        if ($through) return $this->operate($resource, $model, $action, $input, fn () => $this->act_through($resource, $model, $action, $input, $via));

        $sets   = (array) ($definition['sets'] ?? []);
        $change = $this->build($resource, $action, $model, $resource->get_unsaved_copy($model, $sets), $sets, $values, $input, $via);

        if (!empty($input['dry_run'])) return $change->describe();

        if (!empty($definition['confirm'])) {

            if (empty($input['confirm'])) return $this->invalid(['Send the fingerprint of its dry run as confirm.']);

        }

        if ($this->misses_confirm($change, $input)) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        return $this->operate($resource, $model, $action, $input, fn () => $this->run_action($resource, $model, $action, $values, $input, $via, $undoes, $changes_record, $change));

    }

    protected function run_action (Model_Resource $resource, $model, string $action, array $values, array $input, string $via, ?int $undoes, bool $changes_record, Resource_Change $change) : array|WP_Error {

        if ($changes_record && !$model->claim_version((int) $input['version'])) return $this->stale($model);

        $this->writing[$this->writing_key($resource, $model)] = true;

        $resource->forget_values($model);

        $before         = $resource->read($model);
        $version_before = (int) ($changes_record ? $input['version'] : $model->get_version());

        try {

            $result = $resource->{"act_{$action}"}($model, $values, $change);

        } catch (\Throwable $e) {

            $result = new WP_Error('not_saved', $e->getMessage());
            $threw  = true;

        }

        if ($changes_record) $model->finish_version();

        // Its status may have moved behind the model's back, and the read below decides whether anything changed.
        if (is_wp_error($result) && method_exists($model, 'reload')) $model->reload();

        $resource->forget_values($model);

        $made = (!is_wp_error($result) && is_object($result) && method_exists($result, 'get_id') && $result->get_id() !== $model->get_id()) ? $result : null;

        if ($made) $resource->forget_values($made);

        $after = $resource->read($made ?? $model);

        // What threw may have done work a read does not show, so it keeps its claim and leaves a row saying so.
        if (is_wp_error($result) && !$made && ($after === $before) && empty($threw)) {

            unset($this->writing[$this->writing_key($resource, $model)]);

            // The action refused and nothing changed, so a later undo must still find the version it knows.
            if ($changes_record) $model->restore_version((int) $input['version']);

            $code = isset(static::STATUSES[$result->get_error_code()]) ? $result->get_error_code() : 'not_saved';

            return $this->error($code, $result->get_error_message(), static::STATUSES[$code] ?? 500, $this->get_error_data($result));

        }

        $journaled = !$this->is_journaled($resource, $made ?? $model) ? null : Journal::inst()->write([
            'via'            => $via,
            'resource'       => $resource->get_name(),
            'record_id'      => ($made ?? $model)->get_id(),
            'action'         => $action,
            'why'            => $change->why,
            'before'         => $made ? null : $before,
            'after'          => (is_wp_error($result) ? ['_partial' => true] : []) + $after,
            'version_before' => $made ? 0 : $version_before,
            'version_after'  => ($made ?? $model)->get_version(),
            'undoes'         => $undoes,
        ] + $this->get_marks($resource, $made ?? $model));

        unset($this->writing[$this->writing_key($resource, $model)]);

        if (is_wp_error($result)) return $this->error('not_saved', 'Part was done: ' . $result->get_error_message() . ' Read it again before the next change.', 500, ['partial' => true] + $this->get_error_data($result));

        return ($made ? ['created' => true] : []) + $this->describe_record($resource, $made ?? $model) + ['changed' => true] + $this->report_journal($journaled);

    }

    public function trash (string $name, $id, array $input, string $via, ?int $undoes = null) : array|WP_Error {

        return $this->move_to($name, $id, 'trash', $input, $via, $undoes);

    }

    public function untrash (string $name, $id, array $input, string $via, ?int $undoes = null) : array|WP_Error {

        return $this->move_to($name, $id, 'untrash', $input, $via, $undoes);

    }

    protected function move_to (string $name, $id, string $operation, array $input, string $via, ?int $undoes) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;

        if (!$resource->get_post_type()) return $this->refuse_state("A {$resource->get_label()} has no Trash.");

        if ($refusal = $this->refuse_update($resource, $model, $operation, $operation)) return $refusal;

        $trashing = $operation === 'trash';
        $post_id  = $model->get_id();
        $trashed  = $this->is_trashed($resource, $model);

        if ($trashing && $trashed)        return $this->refuse_state('It is already in the Trash.');
        if (!$trashing && !$trashed)      return $this->refuse_state('It is not in the Trash.');
        if ($trashing && !EMPTY_TRASH_DAYS) return $this->refuse_state('This site deletes for good instead of trashing, which nothing could undo.');

        $target = $trashing ? 'trash' : ((string) get_post_meta($post_id, static::TRASH_META[0], true) ?: 'draft');

        if (!$trashing && in_array($target, ['publish', 'future', 'private'], true) && !$this->may($resource, 'publish', $model)) return $this->forbidden($resource->get_capability('publish'), "It was {$target}, and this account may not publish.");

        if ($refusal = $this->check_version($model, $input)) return $refusal;

        if ($problems = $this->check_why($input, $via)) return $this->invalid($problems);

        $sets   = ['status' => $target];
        $change = $this->build($resource, $operation, $model, $resource->get_unsaved_copy($model, $sets), $sets, [], $input, $via);

        if (!empty($input['dry_run'])) return $change->describe();

        if (!empty($resource->get_proposable()[$operation]['confirm']) && empty($input['confirm'])) return $this->invalid(['Send the fingerprint of its dry run as confirm.']);

        if ($this->misses_confirm($change, $input)) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        return $this->operate($resource, $model, $operation, $input, fn () => $this->run_move($resource, $model, $operation, $target, $input, $via, $undoes, $change));

    }

    protected function run_move (Model_Resource $resource, $model, string $operation, string $target, array $input, string $via, ?int $undoes, Resource_Change $change) : array|WP_Error {

        $trashing = $operation === 'trash';
        $post_id  = $model->get_id();
        $seen     = (int) $input['version'];

        if (!$model->claim_version($seen)) return $this->stale($model);

        $this->writing[(string) $post_id] = true;

        $resource->forget_values($model);

        $before = $resource->read($model);
        $meta   = array_map(fn ($key) => get_post_meta($post_id, $key, true), array_combine(static::TRASH_META, static::TRASH_META));
        $keep   = fn ($new_status, $id) => ((int) $id === (int) $post_id) ? $target : $new_status;

        if (!$trashing) add_filter('wp_untrash_post_status', $keep, PHP_INT_MAX, 2);

        try {

            $trashing ? wp_trash_post($post_id) : wp_untrash_post($post_id);
            $error = null;

        } catch (\Throwable $e) {

            $error = $e->getMessage();

        }

        if (!$trashing) remove_filter('wp_untrash_post_status', $keep, PHP_INT_MAX);

        clean_post_cache($post_id);

        $status = get_post_status($post_id);
        $landed = $trashing ? ($status === 'trash') : ($status !== 'trash');

        // A site's wp_insert_post_data may have kept the status, after WordPress changed the trash meta.
        if (!$landed) foreach ($meta as $key => $value) $trashing ? delete_post_meta($post_id, $key) : (($value !== '') && update_post_meta($post_id, $key, $value));

        $model->finish_version();

        if (!$landed && !$error) $model->restore_version($seen);

        $model->reload();
        $resource->forget_values($model);

        if (!$landed) {

            unset($this->writing[(string) $post_id]);

            return $error ? $this->error('not_saved', "It was not done: {$error} Read it again before the next change.", 500, ['partial' => true]) : $this->refuse_state('A guard refused it: it stays as it was.');

        }

        $journaled = !$this->is_journaled($resource, $model) ? null : Journal::inst()->write([
            'via'            => $via,
            'resource'       => $resource->get_name(),
            'record_id'      => $post_id,
            'action'         => $operation,
            'why'            => $change->why,
            'before'         => $before,
            'after'          => $resource->read($model),
            'version_before' => $seen,
            'version_after'  => $model->get_version(),
            'undoes'         => $undoes,
        ]);

        unset($this->writing[(string) $post_id]);

        if ($error) return $this->error('not_saved', "Part was written: {$error} Read it again before the next change.", 500, ['partial' => true]);

        return $this->describe_record($resource, $model) + ['changed' => true] + $this->report_journal($journaled);

    }

    // Applying a set

    // Each step as the current account would meet it now, nothing written.
    public function preview_set (Step_List $set) : array {

        $steps = $set->get_steps();
        $plan  = $this->plan_steps($steps);
        $seen  = [];

        foreach ($steps as $position => $step) {

            $refused = $plan[$position]['done'] ? '' : $this->refuse_step($step);
            $look    = ($plan[$position]['done'] || $refused) ? ['result' => $refused ? $this->refuse_state($refused) : null, 'version' => null] : $this->look_at_step($step, $this->get_step_why($set, $position));
            $res     = $look['result'];

            $seen[$position] = array_filter([
                'op'          => $step['op'],
                'resource'    => $step['resource'],
                'id'          => $step['id'] ?? null,
                'action'      => $step['action'] ?? null,
                'marker'      => $step['marker'] ?? '',
                'done'        => $plan[$position]['done'],
                'reached'     => $plan[$position]['reached'],
                'flags'       => $this->get_step_flags($step),
                'step_hash'   => $this->get_step_hash($step),
                'version'     => $look['version'],
                'fingerprint' => is_array($res) ? ($res['fingerprint'] ?? null) : null,
                'preview'     => is_array($res) ? $res : null,
                'refusal'     => is_wp_error($res) ? ['code' => $res->get_error_code(), 'message' => $res->get_error_message(), 'data' => $res->get_error_data()] : null,
            ], fn ($value) => !is_null($value));

        }

        return $seen;

    }

    // Walks what is not yet done, each step through this service as the current account, stopping at the first that fails or no longer matches what was seen. $seen, by position: fingerprint, version, step_hash.
    public function apply_set (Step_List $set, array $seen, string $via) : array|WP_Error {

        if (!Journal_Schema::is_current()) return $this->refuse_state('The journal is not up to date, so its rows would lose their marks.');
        if ($this->walk)                   return $this->refuse_state('Another set is being walked in this request.');
        if (Journal::inst()->has_undone_rows($set->get_key())) return $this->refuse_state('Part of it has been undone, so what it says is no longer live: withdraw it.');

        $steps   = $set->get_steps();
        $seen    = array_map(fn ($each) => is_array($each) ? $each : null, $seen);
        $results = [];
        $answer  = function (string $outcome, string $reason = '', array $more = []) use ($set, &$results) { return ['outcome' => $outcome, 'reason' => $reason, 'markers' => array_map(fn ($step) => $step['marker'] ?? '', $set->get_steps()), 'results' => $results] + $more; };

        foreach ($steps as $position => $step) if (in_array($step['marker'] ?? '', ['started', 'unknown'], true)) return $answer('unknown', "A person settles step {$position} first.");

        $plan    = $this->plan_steps($steps);
        $refused = '';
        $look    = '';
        $wait    = [];

        foreach ($steps as $position => $step) {

            if ($plan[$position]['done']) continue;

            if ($reason = $this->refuse_step($step)) { $refused = $refused ?: "Step {$position}: {$reason}"; continue; }

            $current = $this->look_at_step($step, $this->get_step_why($set, $position));
            $result  = $current['result'];

            if ($plan[$position]['reached']) {

                if (is_wp_error($result) && in_array($result->get_error_code(), ['forbidden', 'not_found'], true)) $refused = $refused ?: "Step {$position}: {$result->get_error_message()}";

                continue;

            }

            if (is_wp_error($result)) {

                if ($result->get_error_code() === 'stale') { $look = $look ?: "Step {$position}: {$result->get_error_message()}"; continue; }

                if (($result->get_error_code() === 'locked') && array_filter(array_slice($plan, 0, array_search($position, array_keys($plan), true), true), fn ($each) => !$each['done'])) $wait[] = $position;

                $refused = $refused ?: "Step {$position}: {$result->get_error_message()}";

                continue;

            }

            if (!$this->matches_seen($step, $current, $seen[$position] ?? null)) $look = $look ?: "Step {$position} has changed since it was seen.";

        }

        if ($refused) return $answer('refused', $refused, $wait ? ['wait' => $wait] : []);
        if ($look)    return $answer('awaiting_look', $look);

        $this->walk = ['key' => $set->get_key(), 'step' => null, 'proposer' => $set->get_proposer_id(), 'rows' => [], 'create' => false];

        register_shutdown_function(function () use ($set) { if ((($this->walk['key'] ?? null) === $set->get_key()) && $set->holds_lock()) $this->settle_started($set); });

        try {

            foreach ($steps as $position => $step) {

                if ($plan[$position]['done']) continue;

                if ($stop = $this->walk_step($set, $position, $step, $plan[$position]['reached'], $seen[$position] ?? null, $via, $results)) return $answer($stop[0], $stop[1]);

            }

        } finally {

            $this->walk = null;

        }

        return $answer('applied');

    }

    // A step left started, by a request that died: a create landed whole if its record has its key and its unmarked row. Nothing else can say.
    public function settle_started (Step_List $set) : void {

        foreach ($set->get_steps() as $position => $step) {

            if (($step['marker'] ?? '') !== 'started') continue;

            $resource = ($step['op'] === 'create') ? $this->get_resource($step['resource']) : null;
            $record   = $resource?->find_by_set_key(static::get_set_key($set->get_key(), $position));
            $row      = null;

            if ($record) foreach (Journal::inst()->rows_for_set($set->get_key()) as $each) if (($each['action'] === 'create') && ($each['set_step'] === $position) && ((string) $each['record_id'] === (string) $record->get_id()) && empty($each['after']['_partial'])) $row = $each;

            $row ? $set->mark($position, 'applied', ['journal_id' => $row['id'], 'record_id' => $record->get_id()]) : $set->mark($position, 'unknown', $record ? ['record_id' => $record->get_id()] : []);

        }

    }

    // Each row a set's steps wrote and not yet undone, newest first, as the current account would meet its undo now. Nothing written.
    public function preview_undo (Step_List $set) : array {

        $seen    = [];
        $blocked = (bool) array_filter($set->get_steps(), fn ($step) => in_array($step['marker'] ?? '', ['started', 'unknown'], true));

        foreach ($this->plan_undo($set) as $row_id => $plan) {

            $row  = $plan['row'];
            $look = $plan['passed_over'] ? ['result' => null, 'version' => null] : $this->look_at_undo($set, $plan);
            $res  = $look['result'];

            // Named before anyone starts: a row reached later is still known not to reverse, and nothing goes past one that does not.
            $can     = $plan['passed_over'] ? null : (!$blocked && $this->row_has_inverse($row) && !(is_wp_error($res) && !$plan['reached']));
            $behind  = $blocked;
            $blocked = $blocked || ($can === false);

            $seen[$row_id] = array_filter([
                'row_id'      => $row_id,
                'step'        => $row['set_step'],
                'action'      => $row['action'],
                'resource'    => $row['resource'],
                'record_id'   => $row['record_id'],
                'passed_over' => $plan['passed_over'],
                'reached'     => $plan['reached'],
                'can_undo'    => $can,
                'blocked'     => $behind ?: null,
                'secrets'     => $plan['secrets'] ?: null,
                'version'     => $look['version'],
                'fingerprint' => is_array($res) ? ($res['fingerprint'] ?? null) : null,
                'preview'     => is_array($res) ? $res : null,
                'refusal'     => is_wp_error($res) ? ['code' => $res->get_error_code(), 'message' => $res->get_error_message()] : null,
            ], fn ($value) => !is_null($value));

        }

        return $seen;

    }

    // Newest first, each inverse against what was seen of it. $seen, by row id: fingerprint, version. There is no refusing it whole: it stops at the first that cannot go on, what is undone staying undone, and leaves the set's markers and state as they are.
    public function undo_set (Step_List $set, array $seen, string $via) : array|WP_Error {

        if (!Journal_Schema::is_current()) return $this->refuse_state('The journal is not up to date, so its rows would lose their marks.');
        if ($this->walk)                   return $this->refuse_state('Another set is being walked in this request.');

        foreach ($set->get_steps() as $position => $step) if (in_array($step['marker'] ?? '', ['started', 'unknown'], true)) return $this->refuse_state("A person settles step {$position} first.");

        $plans  = $this->plan_undo($set);
        $seen   = array_map(fn ($each) => is_array($each) ? $each : null, $seen);
        $undone = [];
        $live   = $this->get_steps_without_rows($set);
        $answer = function (string $outcome, string $reason = '', ?int $row_id = null, bool $partial = false) use (&$undone, $live) { return ['outcome' => $outcome, 'reason' => $reason, 'undone' => $undone] + ($row_id ? ['row' => $row_id] : []) + ($partial ? ['partial' => true] : []) + ($live ? ['no_row' => $live] : []); };

        $this->walk = ['key' => $set->get_key(), 'step' => null, 'proposer' => $set->get_proposer_id(), 'rows' => [], 'create' => false, 'undoing' => true];

        try {

            foreach ($plans as $row_id => $plan) {

                if ($plan['passed_over']) continue;

                if (!$set->holds_lock()) return $answer('stopped', 'The lock was lost.', $row_id);

                if ($plan['reached']) return $answer('awaiting_look', "Row {$row_id} is previewed once the rows after it are undone: look again.", $row_id);

                $this->walk['step'] = $plan['row']['set_step'];

                $look = $this->look_at_undo($set, $plan);
                $was  = $seen[$row_id] ?? null;

                if (is_wp_error($look['result'])) return $answer(($look['result']->get_error_code() === 'stale') ? 'awaiting_look' : 'stopped', "Row {$row_id}: {$look['result']->get_error_message()}", $row_id);

                if (!$was || !is_string($was['fingerprint'] ?? null) || !hash_equals($was['fingerprint'], (string) ($look['result']['fingerprint'] ?? '')) || !is_numeric($was['version'] ?? null) || ((int) $was['version'] !== $look['version'])) return $answer('awaiting_look', "Row {$row_id} has changed since it was seen.", $row_id);

                try {

                    $result = $this->undo($row_id, ['why' => $this->get_step_why($set, $plan['row']['set_step']), 'confirm' => $was['fingerprint']], $via, $plan['accepted']);

                } catch (\Throwable $e) {

                    return $answer('stopped', "Row {$row_id}: {$e->getMessage()}", $row_id, true);

                }

                if (is_wp_error($result)) return $answer(($result->get_error_code() === 'stale') ? 'awaiting_look' : 'stopped', "Row {$row_id}: {$result->get_error_message()}", $row_id, !empty($result->get_error_data()['partial']));

                // No row says it was undone, so claiming so would never converge.
                if (array_key_exists('changed', $result) && !$result['changed']) return $answer('stopped', "Row {$row_id}: its undo found nothing to write.", $row_id);

                if (($result['journaled'] ?? true) === false) return $answer('stopped', "Row {$row_id} was undone, and its row could not be written.", $row_id, true);

                $undone[] = $row_id;

                if (!$set->holds_lock()) return $answer('stopped', 'The lock was lost during that row.', $row_id);

            }

        } finally {

            $this->walk = null;

        }

        return $answer('undone');

    }

    protected function plan_undo (Step_List $set) : array {

        $journal = Journal::inst();
        $rows    = $journal->rows_for_set($set->get_key());
        $own     = array_filter($rows, fn ($row) => !is_null($row['set_step']) && is_null($row['undoes']));
        $undoing = $journal->undoing(array_column($own, 'id'));

        // A failed undo wrote a row too: unless the values it restores landed, it did not undo what it names, and can be tried again.
        $by_id   = array_column($own, null, 'id');
        $done    = array_flip(array_column(array_filter($undoing, fn ($row) => empty($row['after']['_partial']) || $this->undo_landed($by_id[$row['undoes']] ?? [], $row)), 'undoes'));
        $newest  = [];

        foreach ([...$rows, ...$undoing] as $row) if (!is_null($row['set_step']) || !is_null($row['undoes'])) {

            $key = "{$row['resource']}:{$row['record_id']}";

            if (($newest[$key]['id'] ?? 0) < $row['id']) $newest[$key] = ['id' => $row['id'], 'version' => $row['version_after']];

        }

        $plans = [];
        $ahead = [];
        $mixed = [];

        foreach ($own as $row) if (!isset($done[$row['id']]) && !$this->row_passes_over($row) && !in_array($row['action'], ['update', 'undo', 'restore'], true)) $mixed["{$row['resource']}:{$row['record_id']}"] = true;

        foreach (array_reverse($own) as $row) {

            if (isset($done[$row['id']])) continue;

            $key        = "{$row['resource']}:{$row['record_id']}";
            $resource   = $this->get_resource($row['resource']);
            $passed     = $this->row_passes_over($row);
            $fields     = $resource ? $resource->get_fields() : [];

            $plans[$row['id']] = [
                'row'         => $row,
                'passed_over' => $passed,
                'reached'     => !$passed && isset($ahead[$key]),
                'accepted'    => $newest[$key]['version'] ?? null,
                'strict'      => !$passed && !isset($ahead[$key]) && isset($mixed[$key]),
                'secrets'     => array_keys(array_filter((array) $row['before'], fn ($value, $name) => ($value !== ($row['after'][$name] ?? null)) && (in_array($name, $resource ? $resource::SECRET : [], true) || ($fields[$name] ?? null)?->holds_secret()), ARRAY_FILTER_USE_BOTH)),
            ];

            if (!$passed) $ahead[$key] = true;

        }

        return $plans;

    }

    // Wrote nothing to its record that a read shows: an action declaring so, or an update whose read came back the same.
    protected function row_passes_over (array $row) : bool {

        if (!is_array($row['before']) || ($row['before'] !== $row['after'])) return false;

        if (in_array($row['action'], ['update', 'undo', 'restore'], true)) return true;

        $definition = $this->get_resource($row['resource'])?->get_actions()[$row['action']] ?? [];

        return $definition && (($definition['changes_record'] ?? true) === false);

    }

    // Whether anything could reverse this row, whatever else has happened since.
    protected function row_has_inverse (array $row) : bool {

        if (!$resource = $this->get_resource($row['resource'])) return false;

        if (!$resource->find($row['record_id'])) return false;

        if (in_array($row['action'], ['trash', 'untrash', 'create'], true)) return true;

        if (in_array($row['action'], ['update', 'undo', 'restore'], true)) {

            if (!is_array($row['before'])) return false;

            $changed = array_filter($row['before'], fn ($value, $name) => $value !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH);

            return !$changed || (bool) $this->get_writable_values($resource, $resource->find($row['record_id']), $changed);

        }

        return !empty($resource->get_actions()[$row['action']]['undo']);

    }

    // A failed undo whose fields landed before it failed, a listener after the save having thrown.
    protected function undo_landed (array $row, array $undoing) : bool {

        if (!is_array($row['before'] ?? null)) return false;

        $changed = array_filter($row['before'], fn ($value, $name) => $value !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH);

        return (bool) $changed && (array_intersect_key((array) $undoing['after'], $changed) == $changed);

    }

    // Applied, with nothing to undo it from: the row could not be written, a filter kept it off, or it was pruned.
    protected function get_steps_without_rows (Step_List $set) : array {

        $rowed = array_flip(array_map('intval', array_column(array_filter(Journal::inst()->rows_for_set($set->get_key()), fn ($row) => !is_null($row['set_step'])), 'set_step')));

        return array_keys(array_filter($set->get_steps(), fn ($step, $position) => (($step['marker'] ?? '') === 'applied') && !isset($rowed[$position]), ARRAY_FILTER_USE_BOTH));

    }

    protected function look_at_undo (Step_List $set, array $plan) : array {

        $row = $plan['row'];

        try {

            if ($this->get_resource($row['resource'])?->get_post_type() && is_numeric($row['record_id'])) clean_post_cache((int) $row['record_id']);

            [$resource, $model] = $this->find($row['resource'], $row['record_id']);

            if ($resource instanceof WP_Error) return ['result' => $resource, 'version' => null];

            $resource->refresh($model);

            $version = $model->get_version();

            // The first inverse on a record with more than updates: anything but the set's own newest row having moved it is an outside write, which the version an inverse accepts must not cover.
            if ($plan['strict'] && !is_null($plan['accepted']) && ($version !== $plan['accepted'])) return ['result' => $this->stale($model, 'It changed outside the set after its last row: read it again.'), 'version' => $version];

            return ['result' => $this->undo($row['id'], ['dry_run' => true, 'why' => $this->get_step_why($set, (int) $row['set_step'])], 'other', $plan['accepted']), 'version' => $version];

        } catch (\Throwable $e) {

            error_log('Lattice resources: looking at an undo threw: ' . $e->getMessage());

            return ['result' => $this->refuse_state('It could not be read as held.'), 'version' => null];

        }

    }

    // What a create step's record carries, so a request that died can find it.
    public static function get_set_key (int $key, int $position) : string {

        return "{$key}:{$position}";

    }

    // For Journal::write(): a row of this walk's step names it.
    public function get_held_marks (array $row = []) : array {

        if (!$this->walk) return [];

        // A listener's write during an undo names the set, and no step: it is nothing the undo should go on to undo.
        $step = (!empty($this->walk['undoing']) && empty($row['undoes'])) ? null : $this->walk['step'];

        return ['set_key' => $this->walk['key'], 'set_step' => $step, 'proposer_id' => $this->walk['proposer']];

    }

    public function note_row (int $id, string $action, string $resource, string $record_id) : void {

        if ($this->walk && $id) $this->walk['rows'][] = ['id' => $id, 'action' => $action, 'resource' => $resource, 'record_id' => $record_id];

    }

    protected function take_create_meta () : array {

        if (!($this->walk['create'] ?? false)) return [];

        $this->walk['create'] = false;

        return [static::SET_KEY => static::get_set_key($this->walk['key'], $this->walk['step'])];

    }

    // The walk is in order and stops at such a step, so waiting for all before it says all that waiting for one would.
    protected function plan_steps (array $steps) : array {

        $plan    = [];
        $writes  = [];
        $waiting = false;

        foreach ($steps as $position => $step) {

            $done = in_array($step['marker'] ?? '', ['applied', 'skipped'], true);
            $key  = isset($step['id']) ? $step['resource'] . ':' . (is_numeric($step['id']) ? (int) $step['id'] : $step['id']) : null;

            $plan[$position] = ['done' => $done, 'reached' => !$done && (($key && isset($writes[$key])) || (!empty($step['wait']) && $waiting))];

            if ($done) continue;

            $waiting = true;

            if ($key && $this->step_writes($step)) $writes[$key] = true;

        }

        return $plan;

    }

    protected function step_writes (array $step) : bool {

        if ($step['op'] !== 'act') return true;

        return $this->get_resource($step['resource'])?->get_actions()[$step['action'] ?? '']['changes_record'] ?? true;

    }

    // The set's own business: what its resource takes.
    public function refuse_step (array $step) : string {

        if (!$resource = $this->get_resource($step['resource'])) return "No resource {$step['resource']}.";

        // A call's control keys are the walk's: held in a step, one could turn a preview into a write.
        if ($held = array_intersect_key((array) ($step['values'] ?? []) + (array) ($step['input'] ?? []), array_flip([...static::CONTROL, 'expected']))) return 'A step cannot hold ' . implode(', ', array_keys($held)) . '.';

        $proposable = $resource->get_proposable();

        if ($step['op'] !== 'act') return isset($proposable[$step['op']]) ? '' : "{$resource->get_label()} does not take {$step['op']} in a set.";

        $action = (string) ($step['action'] ?? '');

        if (in_array($action, ['create', 'update', 'trash', 'untrash'], true) || !isset($proposable[$action], $resource->get_actions()[$action])) return "{$resource->get_label()} does not take {$action} in a set.";

        return empty($resource->get_actions()[$action]['writes_through']) ? '' : "{$action} writes through the service, so it cannot be a step.";

    }

    protected function get_step_hash (array $step) : string {

        return wp_hash((string) wp_json_encode([$step['op'], $step['resource'], isset($step['id']) ? (string) $step['id'] : null, $step['action'] ?? null, $step['input'] ?? [], $step['values'] ?? [], $step['expected'] ?? null, !empty($step['wait'])]));

    }

    protected function get_step_why (Step_List $set, int $position) : string {

        return $set->get_why() . " (set {$set->get_key()}, step {$position})";

    }

    // What a reviewer should know beforehand. Undoing's own dry run says exactly what it can do.
    protected function get_step_flags (array $step) : array {

        $resource = $this->get_resource($step['resource']);
        $action   = ($step['op'] === 'act') ? ($resource?->get_actions()[$step['action'] ?? ''] ?? []) : [];
        $fields   = (array) ($step['values'] ?? []);
        $secrets  = ($resource && $fields && ($step['op'] === 'update')) ? array_filter(array_keys($fields), fn ($name) => in_array($name, $resource::SECRET, true) || $resource->get_field($name)?->is_secret()) : [];
        $no_undo  = (($step['op'] === 'act') && empty($action['undo'])) || (($step['op'] === 'create') && $resource && !$resource->get_post_type()) || ($secrets && (count($secrets) === count($fields)));

        return array_values(array_filter([
            in_array($step['op'], ['trash', 'untrash', 'act'], true) ? $step['op'] : null,
            ($resource && !$resource->get_post_type()) ? 'no_post_type' : null,
            $no_undo ? 'cannot_be_undone' : null,
            !empty($step['wait']) ? 'wait' : null,
        ]));

    }

    // At the record's current version, so a refusal here is about the step, and comparing with what was seen is apart from it.
    protected function look_at_step (array $step, string $why) : array {

        try {

            if ($step['op'] === 'create') return ['result' => $this->call_step($step, ['dry_run' => true, 'why' => $why], 'other'), 'version' => null];

            // A record deleted by another request is still in this one's cache.
            if ($this->get_resource($step['resource'])?->get_post_type() && is_numeric($step['id'] ?? null)) clean_post_cache((int) $step['id']);

            [$resource, $model] = $this->find($step['resource'], $step['id'] ?? 0);

            if ($resource instanceof WP_Error) return ['result' => $resource, 'version' => null];

            $resource->refresh($model);

            $version = $model->get_version();

            return ['result' => $this->call_step($step, ['dry_run' => true, 'version' => $version, 'why' => $why], 'other'), 'version' => $version];

        } catch (\Throwable $e) {

            error_log('Lattice resources: looking at a step threw: ' . $e->getMessage());

            // A look writes nothing, so this says nothing about what was written.
            return ['result' => $this->error('locked', 'It could not be read as held.', 409, ['reason' => 'It could not be read as held.']), 'version' => null];

        }

    }

    protected function call_step (array $step, array $control, string $via) : array|WP_Error {

        // The walk's control keys win, whatever a step holds: refuse_step() says so to whoever holds one.
        $own    = array_flip([...static::CONTROL, 'expected']);
        $values = array_diff_key((array) ($step['values'] ?? []), $own);
        $input  = array_diff_key((array) ($step['input'] ?? []), $own);

        return match ($step['op']) {
            'create'  => $this->create_record($step['resource'], $values + $control, $via),
            'update'  => $this->update($step['resource'], $step['id'], $values + (isset($step['expected']) ? ['expected' => $step['expected']] : []) + $control, $via),
            'act'     => $this->act($step['resource'], $step['id'], (string) ($step['action'] ?? ''), $input + $control, $via),
            'trash'   => $this->trash($step['resource'], $step['id'], $control, $via),
            'untrash' => $this->untrash($step['resource'], $step['id'], $control, $via),
            default   => $this->invalid(["No operation {$step['op']}."]),
        };

    }

    protected function matches_seen (array $step, array $current, ?array $seen) : bool {

        if (!$seen || !is_string($seen['step_hash'] ?? null) || !is_string($seen['fingerprint'] ?? null) || !isset($current['result']['fingerprint'])) return false;

        if (!hash_equals($seen['step_hash'], $this->get_step_hash($step)) || !hash_equals($seen['fingerprint'], (string) $current['result']['fingerprint'])) return false;

        return is_null($current['version']) || (is_numeric($seen['version'] ?? null) && ((int) $seen['version'] === $current['version']));

    }

    // What stops the walk, as [outcome, reason], or null to go on.
    protected function walk_step (Step_List $set, int $position, array $step, bool $reached, ?array $seen, string $via, array &$results) : ?array {

        $stop = fn (string $marker, string $outcome, string $reason, array $detail = []) => $set->mark($position, $marker, $detail) ? [$outcome, $reason] : ['unknown', "Step {$position}'s marker could not be written: {$reason}"];

        if (!$set->holds_lock()) return ['unknown', 'The lock was lost.'];

        if ($reached) return $stop('stopped', 'awaiting_look', "Step {$position} is previewed once the steps before it are applied: look again.");

        $this->walk['step'] = $position;

        $current = $this->look_at_step($step, $this->get_step_why($set, $position));
        $result  = $current['result'];

        if (is_wp_error($result)) return ($result->get_error_code() === 'stale') ? $stop('stopped', 'awaiting_look', "Step {$position}: {$result->get_error_message()}") : $stop('failed', 'failed', "Step {$position}: {$result->get_error_message()}", ['error' => $result->get_error_message()]);

        if (!$this->matches_seen($step, $current, $seen)) return $stop('stopped', 'awaiting_look', "Step {$position} has changed since it was seen.");

        if (!$set->mark($position, 'started')) return ['unknown', "Step {$position}'s marker could not be written."];

        $this->walk['rows']   = [];
        $this->walk['create'] = $step['op'] === 'create';

        try {

            $result = $this->call_step($step, array_filter(['version' => $current['version'], 'confirm' => $seen['fingerprint'], 'why' => $this->get_step_why($set, $position)], fn ($value) => !is_null($value)), $via);

        } catch (\Throwable $e) {

            $result = new WP_Error('not_saved', $e->getMessage(), ['partial' => true]);

        } finally {

            $this->walk['create'] = false;

        }

        // Another request may have settled this step by now.
        if (!$set->holds_lock()) return ['unknown', 'The lock was lost during that step.'];

        if (is_wp_error($result)) {

            $data    = (array) $result->get_error_data();
            $message = "Step {$position}: {$result->get_error_message()}";

            if (!empty($data['partial']))               return $stop('unknown', 'unknown', $message, array_filter(['error' => $result->get_error_message(), 'record_id' => $data['id'] ?? null, 'journal_id' => $this->find_walk_row($step)]));
            if ($result->get_error_code() === 'stale')  return $stop('stopped', 'awaiting_look', $message);

            return $stop('failed', 'failed', $message, ['error' => $result->get_error_message()]);

        }

        $results[$position] = $result;

        if (array_key_exists('changed', $result) && !$result['changed']) return $set->mark($position, 'skipped') ? null : ['unknown', "Step {$position}'s marker could not be written."];

        $detail = array_filter(['journal_id' => $this->find_walk_row($step), 'record_id' => ($step['op'] === 'create') ? ($result['id'] ?? null) : null]);

        if (($result['journaled'] ?? true) === false) return $stop('applied', 'failed', "Step {$position} was applied, and its row could not be written.", $detail);

        return $set->mark($position, 'applied', $detail) ? null : ['unknown', "Step {$position}'s marker could not be written."];

    }

    // A listener's own write through the service carries the step too: the step's row is the last one for its operation.
    protected function find_walk_row (array $step) : ?int {

        $action = ($step['op'] === 'act') ? ($step['action'] ?? '') : $step['op'];

        foreach (array_reverse($this->walk['rows'] ?? []) as $row) if (($row['action'] === $action) && ($row['resource'] === $step['resource']) && (!isset($step['id']) || ($row['record_id'] === (string) $step['id']))) return $row['id'];

        return null;

    }

    // A resource's say over a write that will happen. A dry run asks nothing: an action writing through the service tests dry_run the same way, or it writes unasked.
    protected function operate (Model_Resource $resource, $model, string $operation, array $input, callable $write) : array|WP_Error {

        if (!empty($input['dry_run'])) return $write();

        try {

            $refusal = $resource->before_operation($model, $operation);

        } catch (\Throwable $e) {

            error_log('Lattice resources: before_operation threw: ' . $e->getMessage());

            return $this->error('not_saved', 'It could not begin, and nothing was written.', 500);

        }

        if ($refusal) {

            $code = isset(static::STATUSES[$refusal->get_error_code()]) ? $refusal->get_error_code() : 'locked';

            return $this->error($code, $refusal->get_error_message(), static::STATUSES[$code], $this->get_error_data($refusal));

        }

        try {

            return $write();

        } catch (\Throwable $e) {

            // What a write leaves when it throws outside its own try: nothing else in this request would clear it.
            unset($this->writing[$this->writing_key($resource, $model)]);

            $model->finish_version();

            throw $e;

        } finally {

            // The write has landed by now, and its answer stands.
            try {

                $resource->after_operation($model, $operation);

            } catch (\Throwable $e) {

                error_log('Lattice resources: after_operation threw: ' . $e->getMessage());

            }

        }

    }

    // The write it makes through this service checks, previews, claims and journals: the action only answers that write's result.
    protected function act_through (Model_Resource $resource, $model, string $action, array $input, string $via) : array|WP_Error {

        try {

            $result = $resource->{"act_{$action}"}($model, $input, $via);

            if (is_wp_error($result)) return isset(((array) $result->get_error_data())['status']) ? $result : $this->error($result->get_error_code(), $result->get_error_message(), static::STATUSES[$result->get_error_code()] ?? 500, $this->get_error_data($result));

            return is_array($result) ? $result : $this->error('not_saved', "{$action} answered nothing: read it again before the next change.", 500, ['partial' => true]);

        } catch (\Throwable $e) {

            return $this->error('not_saved', 'It may be partly done: ' . $e->getMessage() . ' Read it again before the next change.', 500, ['partial' => true]);

        }

    }

    // $accepted: a version an action's or a move's strict check also takes, since a set's own later rows moved it. Only undo_set() passes one: never from input, so no route reaches it.
    public function undo (int $row_id, array $input, string $via, ?int $accepted = null) : array|WP_Error {

        if (!$row = Journal::inst()->find($row_id)) return $this->error('not_found', "No journal row {$row_id}.", 404);

        [$resource, $model] = $this->find($row['resource'], $row['record_id']);

        if ($resource instanceof WP_Error)          return $resource;
        if ($refusal = $this->refuse_read($resource, $model)) return $refusal;

        if (!$this->may($resource, 'undo', $model)) return $this->forbidden('undo');

        $at_row = fn () => in_array($model->get_version(), [$row['version_after'], $accepted], true);

        $moved = fn (string $since) => $this->stale($model, "{$since} changed after that row: undo the rows after it first.", ['since' => Journal::inst()->query(['resource' => $row['resource'], 'record_id' => $row['record_id'], 'since' => $row_id])]);

        $inverse = ['version' => $model->get_version()] + array_intersect_key($input, array_flip(static::CONTROL));
        $trashed = $this->is_trashed($resource, $model);
        $to_bin  = in_array($row['action'], ['update', 'undo'], true) && (($row['after']['status'] ?? '') === 'trash') && (($row['before']['status'] ?? '') !== 'trash');

        if ($row['action'] === 'trash' || $to_bin) {

            if (!$trashed || (!$to_bin && !$at_row())) return $moved('It');

            return $this->untrash($row['resource'], $row['record_id'], $inverse, $via, $row_id);

        }

        if ($refusal = $this->refuse_trashed($resource, $model)) return $refusal;

        if (in_array($row['action'], ['untrash', 'create'], true)) {

            // Nothing else in a set can name a record it created, so a create has no version to accept.
            if ((($row['action'] === 'create') ? $model->get_version() !== $row['version_after'] : !$at_row())) return $moved('It');

            return $this->trash($row['resource'], $row['record_id'], $inverse, $via, $row_id);

        }

        // Any save raises the version, so an update row compares the fields it restores.
        if (in_array($row['action'], ['update', 'undo', 'restore'], true) && is_array($row['before'])) {

            if ($refusal = $this->refuse_update($resource, $model)) return $refusal;

            $changed = array_filter($row['before'], fn ($value, $name) => $value !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH);
            $values  = $this->get_writable_values($resource, $model, $changed);

            if ($changed && !$values) return $this->error('locked', 'Only secrets changed, and the journal keeps none to restore.', 409, ['reason' => 'Secrets are not kept.']);

            $current = $resource->read($model);

            if ($changed = array_keys(array_filter($values, fn ($value, $name) => ($current[$name] ?? null) !== ($row['after'][$name] ?? null), ARRAY_FILTER_USE_BOTH))) return $moved(implode(', ', $changed));

            $problems = $this->check_why($input, $via);

            $fields = $resource->get_fields_for($model, $values);

            [$values, $removed] = $this->filter_values($values, $fields, $resource->read($model, false));

            foreach ($values as $name => $value) if (!in_array($value, [null, '', []], true) || $resource->is_shown($fields[$name], $values + $current)) array_push($problems, ...$fields[$name]->check($value, $name, null, true));

            if ($problems) return $this->invalid($problems);

            return $this->write_values($resource, $model, $values, ['version' => $model->get_version()] + $input, $via, 'undo', $model->get_version(), $row_id, $removed);

        }

        if (!$at_row()) return $moved('It');

        if ($inverse = $resource->get_actions()[$row['action']]['undo'] ?? null) return $this->act($resource->get_name(), $model->get_id(), $inverse, ['version' => $model->get_version()] + array_intersect_key($input, array_flip([...static::CONTROL, ...array_keys((array) ($resource->get_actions()[$inverse]['input'] ?? []))])), $via, $row_id);

        return $this->error('locked', 'Nothing reverses this.', 409, ['reason' => 'Nothing reverses this.']);

    }

    // What would stop this update, as an account that may read the record, without its right to make it: what an author reads before a reviewer approves. Writes nothing and answers no diff.
    public function check_update (string $name, $id, array $values) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error) return $resource;

        if (!$this->may($resource, 'read', $model)) return $this->forbidden($resource->get_capability('read', $model));

        [$values, $problems] = $this->take_values($resource, $values, $model);

        array_push($problems, ...$this->check_required($resource, $model, $values + $resource->read($model), $resource->get_status($model)));

        $lock = $resource->get_lock($model);

        return array_filter([
            'problems' => $problems,
            'locked'   => $this->get_locked($resource, $model, $values),
            'needs'    => ($lock && !current_user_can($lock)) ? $lock : null,
        ]);

    }

    // Values held for a later write, as the current account would send them now: filtered, checked, written nowhere. A held column is filtered here too, since the save that stores it runs as whoever applies it. No id for a record not yet made. A mask is refused: it stands for what a record holds, and answering that would hand a secret to whoever only read its mask.
    public function hold_values (string $name, $id, array $values) : array|WP_Error {

        if (!$resource = $this->get_resource($name)) return $this->error('not_found', "No resource {$name}.", 404);

        $model = null;

        if (is_null($id)) {

            if (!$this->may($resource, 'hold')) return $this->forbidden('hold');

        } else {

            if (!is_scalar($id)) return $this->error('not_found', "No {$resource->get_label()} with that id.", 404);

            [$resource, $model] = $this->find($name, $id);

            if ($resource instanceof WP_Error) return $resource;
            if ($refusal = $this->refuse_read($resource, $model)) return $refusal;

        }

        [$values, $problems, $removed] = $this->take_values($resource, $values, $model, false);
        [$values, $lost]               = $this->filter_columns($resource, $values, $model);

        foreach ($values as $field => $value) {

            $leaves = [$value];

            array_walk_recursive($leaves, function ($leaf) use (&$problems, $field) { if (Resource_Field::is_mask($leaf)) $problems[] = "{$field} holds a mask: send the value it stands for."; });

        }

        return ['values' => $values, 'removed' => $removed + $lost, 'problems' => $problems];

    }

    // How each field compares, for an account that may only read the record: what a write would refuse is the write's to say. Without the right to write, a field holding a secret is withheld, so reading it never tests a guess at one.
    public function compare_expected (string $name, $id, array $values, $expected) : array|WP_Error {

        [$resource, $model] = $this->find($name, $id);

        if ($resource instanceof WP_Error)                    return $resource;
        if ($refusal = $this->refuse_read($resource, $model)) return $refusal;
        if ($problems = $this->check_expected($expected, $values)) return $this->invalid($problems);

        if (method_exists($model, 'reload')) $model->reload();

        $resource->forget_values($model);

        $values   = array_diff_key($values, array_flip(static::CONTROL));
        $withheld = $this->may($resource, 'update', $model) ? [] : array_filter($resource->get_fields_for($model, $values), fn ($field, $field_name) => isset($values[$field_name]) && $field->holds_secret(), ARRAY_FILTER_USE_BOTH);

        [$applied, $skipped, $conflicts] = $this->compare_fields($resource, $model, array_diff_key($values, $withheld), $expected);

        return ['applied' => array_keys($applied), 'skipped' => $skipped, 'conflicts' => $conflicts, 'withheld' => array_keys($withheld)];

    }

    // The steps of a write

    // Each field is measured from where its caller started, so a change elsewhere stops nothing: applied while the record still holds the start, skipped where it already holds the proposal, a conflict otherwise.
    protected function compare_fields (Model_Resource $resource, $model, array $values, array $expected) : array {

        $live   = $resource->read($model, false);
        $shown  = $resource->read($model);
        $fields = $resource->get_fields_for($model, $values);

        $applied = $skipped = $conflicts = [];

        foreach ($values as $name => $proposed) {

            if (!$field = $fields[$name] ?? null) {

                $conflicts[] = ['field' => $name, 'not_shown' => true, 'start' => Resource_Field::mask_value($expected[$name] ?? null), 'live' => null, 'proposed' => Resource_Field::mask_value($proposed)];

                continue;

            }

            $same  = fn ($one, $other) => !$this->diff(['value' => $field->comparable($one, $proposed)], ['value' => $field->comparable($other, $proposed)]);
            $start = $field->mask($expected[$name] ?? null);

            // WordPress changes a column at save, so only the same text says it is already there.
            $there = (($field->source === 'model') && is_string($proposed) && is_string($live[$name] ?? null)) ? ($live[$name] === $proposed) : $same($live[$name] ?? null, $proposed);

            if ($there)                                 $skipped[]      = $name;
            elseif ($same($shown[$name] ?? null, $start)) $applied[$name] = $proposed;
            else                                        $conflicts[]    = ['field' => $name, 'start' => $start, 'live' => $shown[$name] ?? null, 'proposed' => $field->mask($proposed)];

        }

        return [$applied, $skipped, $conflicts];

    }

    protected function write_expected (Model_Resource $resource, $model, array $values, array $expected, array $input, string $via, array $removed, int $seen) : array|WP_Error {

        [$applied, $skipped, $conflicts] = $this->compare_fields($resource, $model, $values, $expected);

        if ($conflicts) return $this->stale($model, 'Someone changed what this changes: read it again and start from there.', ['conflicts' => $conflicts]);

        $result = $this->write_values($resource, $model, $applied, $input, $via, 'update', $seen, null, $removed);

        return is_wp_error($result) ? $result : $result + ['applied' => array_keys($applied), 'skipped' => $skipped];

    }

    protected function write_values (Model_Resource $resource, $model, array $values, array $input, string $via, string $operation, int $seen, ?int $undoes, array $removed = []) : array|WP_Error {

        if ($refusal = $this->refuse_locked($resource, $model, $values)) return $refusal;

        $change = $this->build($resource, $operation, $model, $resource->get_unsaved_copy($model, $values), $values, [], $input, $via, $removed);

        if (!$change->changes && $removed) return $this->error('locked', 'Filtering for this account removes the whole change: ' . implode(', ', array_keys($removed)) . '.', 409, ['reason' => 'Nothing is left to write.', 'removed' => $removed]);

        // Before the dry run, as a create and a trash have it: a preview that passes must not then refuse.
        if ($refusal = $this->refuse_publish($resource, $model, $values)) return $refusal;

        if (!empty($input['dry_run'])) return $change->describe();
        if (!$change->changes)         return $this->describe_record($resource, $model) + ['changed' => false] + array_filter(['removed' => $removed]);

        if ($this->misses_confirm($change, $input)) return $this->stale($model, 'What it would do has changed since its dry run: run it again.');

        return $this->operate($resource, $model, $operation, $input, function () use ($resource, $model, $values, $change, $via, $operation, $seen, $undoes, $removed) {

            if (!$model->claim_version($seen)) return $this->stale($model);

            $result = $this->commit($resource, $model, $values, $change->why, $via, $operation, $seen, $undoes, true);

            return is_wp_error($result) ? $result : $result + array_filter(['removed' => $removed]);

        });

    }

    protected function commit (Model_Resource $resource, $model, array $values, string $why, string $via, string $operation, int $seen, ?int $undoes, bool $claimed = false) : array|WP_Error {

        if (!$claimed && !$model->claim_version($seen)) return $this->stale($model);

        $this->writing[$this->writing_key($resource, $model)] = true;

        $resource->forget_values($model);

        $before = ($operation === 'create') ? null : $resource->read($model);
        $error  = null;

        try {

            $snapshot = $resource->before_write($model);

            $fields = $resource->get_fields_for($model, $values);

            // By key: finding a field by name asks the saved record's groups, which a term in this write may change.
            foreach ($values as $name => $value) if ($fields[$name]->source === 'acf') $model->update_field($fields[$name]->key ?: $name, wp_slash($fields[$name]->to_raw($value)));

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
            // A failed write's row says so: a create's row settles its step only when it landed whole.
            'after'          => ($error ? ['_partial' => true] : []) + $resource->read($model),
            'version_before' => $seen,
            'version_after'  => $model->get_version(),
            'undoes'         => $undoes,
        ] + $this->get_marks($resource, $model));

        unset($this->writing[$this->writing_key($resource, $model)]);

        if ($error) return $this->error('not_saved', 'Part was written: ' . $error->getMessage() . ' Read it again before the next change.', 500, ['partial' => true]);

        return $this->describe_record($resource, $model) + ['changed' => true] + $this->report_journal($journaled);

    }

    // The write has landed by now: a row without its marks is better than none.
    protected function get_marks (Model_Resource $resource, $model) : array {

        try {

            return $resource->journal_marks($model);

        } catch (\Throwable $e) {

            error_log('Lattice resources: a row lost its marks: ' . $e->getMessage());

            return [];

        }

    }

    public function is_journaled (Model_Resource $resource, $model) : bool {

        return (bool) apply_filters('lattice.resources.journal', true, $resource, $model);

    }

    protected function report_journal (?int $row_id) : array {

        if ($row_id !== 0) return [];

        error_log('Lattice resources: a change was saved without its journal row.');

        return ['journaled' => false];

    }

    protected function build (Model_Resource $resource, string $operation, ?object $before, object $after, array $values, array $action_input, array $input, string $via, array $removed = []) : Resource_Change {

        $change = new Resource_Change($resource, $operation, $before, $after, $values, $action_input, trim((string) ($input['why'] ?? '')), $via);

        $was = $before ? array_intersect_key($resource->read($before), $values) : [];

        $change->changes      = $this->diff($was, $resource->mask($values, $resource->get_fields_for($before, $values)));
        $change->consequences = $resource->get_consequences($change);
        $change->fingerprint  = md5((string) wp_json_encode([$change->changes, $this->drop_volatile($change->consequences)]));
        $change->removed      = $removed;

        return $change;

    }

    // A consequence's own top-level keys it names as changing by themselves: shown, but a busy record must not stop whoever looked.
    protected function drop_volatile (array $consequences) : array {

        return array_map(fn ($item) => (is_array($item) && !empty($item['volatile'])) ? array_diff_key($item, array_flip([...(array) $item['volatile'], 'volatile'])) : $item, $consequences);

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

        if (in_array($operation, ['list', 'read', 'create', 'update', 'publish', 'trash', 'untrash'], true) && !$resource->can($operation, $model)) return false;

        return (bool) apply_filters('lattice.resources.allow', true, wp_get_current_user(), $resource->get_name(), $operation);

    }

    protected function refuse_update (Model_Resource $resource, $model, string $operation = 'update', string $right = 'update') : ?WP_Error {

        $data = fn (string $needs) => (array) apply_filters('lattice.resources.forbidden', ['needs' => $needs], $resource, $model, $operation);

        if (!$this->may($resource, $right, $model)) return $this->forbidden($needs = $resource->get_capability($right, $model), data: $data($needs));

        if (($lock = $resource->get_lock($model)) && !current_user_can($lock)) return $this->forbidden($lock, 'In this state it changes only for an account with ' . $lock . '.', $data($lock));

        return $this->refuse_record($resource, $model, $right);

    }

    protected function is_trashed (Model_Resource $resource, $model) : bool {

        return $resource->get_post_type() && ($resource->get_status($model) === 'trash');

    }

    protected function refuse_trashed (Model_Resource $resource, $model) : ?WP_Error {

        return $this->is_trashed($resource, $model) ? $this->refuse_state('It is in the Trash: take it out first.') : null;

    }

    protected function refuse_state (string $reason) : WP_Error {

        return $this->error('locked', $reason, 409, ['reason' => $reason]);

    }

    // Another feature's say over one record: a reason refuses it.
    protected function refuse_record (Model_Resource $resource, $model, string $operation) : ?WP_Error {

        $reason = (string) apply_filters('lattice.resources.refuse', '', $resource, $model, $operation);

        return $reason ? $this->error('locked', $reason, 409, ['reason' => $reason]) : null;

    }

    protected function get_locked (Model_Resource $resource, $model, array $values) : array {

        $nested = array_map(fn () => 'it holds a flexible field the layer cannot yet write without losing its rows.', array_filter($resource->get_fields_for($model, $values), fn ($field) => $field->holds_flexible()));

        return array_intersect_key(($model ? $resource->get_locked_fields($model) : []) + $nested, $values);

    }

    protected function refuse_locked (Model_Resource $resource, $model, array $values) : ?WP_Error {

        if (!$locked = $this->get_locked($resource, $model, $values)) return null;

        $reason = implode(' ', array_map(fn ($name, $why) => "{$name}: {$why}", array_keys($locked), $locked));

        return $this->error('locked', $reason, 409, ['reason' => $reason, 'fields' => array_keys($locked)]);

    }

    public function refuse_read (Model_Resource $resource, $model) : ?WP_Error {

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

    // Sent and no longer what it would do. A confirm is a fingerprint, so anything else sent as one fails to match it.
    protected function misses_confirm (Resource_Change $change, array $input) : bool {

        $confirm = $input['confirm'] ?? null;

        if (in_array($confirm, [null, '', false], true)) return false;

        return !is_string($confirm) || !hash_equals($change->fingerprint, $confirm);

    }

    // wpdb drops a row it would have to change, silently and after the write: a why that would be is refused first.
    protected function check_why (array $input, string $via) : array {

        $why = $input['why'] ?? '';

        if (!is_string($why) && !is_numeric($why)) return ['A why is text.'];

        $why = (string) $why;

        if (strlen($why) > static::WHY_BYTES)        return ['A why is at most ' . static::WHY_BYTES . ' bytes.'];
        if (wp_check_invalid_utf8($why) !== $why)    return ['A why is valid text.'];

        return (empty($input['dry_run']) && in_array($via, ['rest', 'cli'], true) && trim($why) === '') ? ['Say why: every change through the API carries a why.'] : [];

    }

    protected function take_values (Model_Resource $resource, array $input, $model, bool $unmask = true) : array {

        $values   = array_diff_key($input, array_flip(static::CONTROL));
        $problems = [];
        $current  = $model ? $resource->read($model, false) : [];
        $shown    = $resource->get_fields_for($model, $values);
        $known    = $resource->get_fields() + $shown;

        if ($unknown = array_diff(array_keys($values), array_keys($known))) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';
        if ($hidden = array_diff(array_keys(array_intersect_key($values, $known)), array_keys($shown))) $problems[] = 'Not shown for this record (see applies_when): ' . implode(', ', $hidden) . '.';

        if ($unmask) foreach (array_intersect_key($values, $shown) as $name => $value) $values[$name] = $shown[$name]->unmask($value, $current[$name] ?? null);

        [$values, $removed] = $this->filter_values($values, $shown, $current);

        foreach (array_intersect_key($values, $shown) as $name => $value) {

            $field = $shown[$name];

            if (in_array($value, [null, '', []], true) && !$resource->is_shown($field, $values + $current)) continue;

            array_push($problems, ...$field->check($value, $name, $current[$name] ?? null));

        }

        return [array_intersect_key($values, $shown), $problems, $removed];

    }

    // ACF's values as the acting account, before anything checks or previews them; a value sent back as the record holds it keeps its markup. Columns are WordPress's to filter.
    protected function filter_values (array $values, array $fields, array $current) : array {

        $removed = [];

        foreach (array_intersect_key($values, $fields) as $name => $sent) {

            if (($fields[$name]->source !== 'acf') || acf_allow_unfiltered_html()) continue;

            $values[$name] = Markup::keep_value($fields[$name]->filter($sent), $sent, [$current[$name] ?? null]);

            if ($lost = Markup::subtract(Markup::count($sent), Markup::count($values[$name]))) $removed[$name] = $lost;

        }

        return [$values, $removed];

    }

    // Only what the column's own save runs for an account without unfiltered_html, and none of the rest of its chain: the save still runs that, once, when the value is written.
    protected function filter_columns (Model_Resource $resource, array $values, $model) : array {

        if (!$resource->get_post_type() || current_user_can('unfiltered_html')) return [$values, []];

        $current = $model ? $resource->read($model, false) : [];
        $removed = [];

        foreach (array_intersect_key($values, static::COLUMNS) as $name => $sent) {

            if (!is_string($sent) || ($resource->get_field($name)?->source !== 'model')) continue;

            $values[$name] = Markup::keep_value(wp_kses($sent, static::COLUMNS[$name]), $sent, [$current[$name] ?? null]);

            if ($lost = Markup::subtract(Markup::count($sent), Markup::count($values[$name]))) $removed[$name] = $lost;

        }

        return [$values, $removed];

    }

    protected function check_expected ($expected, array $input) : array {

        if (!is_array($expected)) return ['expected is an object of each field sent and the value it started from.'];

        $sent = array_diff_key($input, array_flip(static::CONTROL));

        return (array_diff_key($sent, $expected) || array_diff_key($expected, $sent)) ? ['expected names every field sent and no other.'] : [];

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

        return array_filter($values, fn ($value, $name) => ($field = $fields[$name] ?? null) && !$field->computed && !$field->action_only && !$field->holds_secret(), ARRAY_FILTER_USE_BOTH);

    }

    // A JSON string becomes what it holds; what holds no array stays as sent, for the check to answer.
    protected function decode_action_input (array $types, array $input) : array {

        foreach ($types as $name => $type) {

            if ((ltrim($type, '?') !== 'json') || !is_string($input[$name] ?? null)) continue;

            if ($input[$name] === '') { unset($input[$name]); continue; }

            $decoded = json_decode($input[$name], true);

            if (is_array($decoded)) $input[$name] = $decoded;

        }

        return $input;

    }

    // A client chooses this input's shape, so a rule that cannot read it refuses instead of ending the request.
    protected function check_action_rule (callable $rule, $model, array $values) : array {

        try {

            $problems = $rule($model, $values);

        } catch (\Throwable $e) {

            error_log('Lattice resources: an action\'s validate threw: ' . $e->getMessage());

            return ['It could not be checked as sent.'];

        }

        return is_array($problems) ? array_values($problems) : ($problems ? ['It was refused as sent.'] : []);

    }

    protected function check_action_input (array $types, array $values) : array {

        $problems = [];

        foreach ($types as $name => $type) {

            $value    = $values[$name] ?? null;
            $optional = str_starts_with($type, '?');
            $type     = ltrim($type, '?');

            if (in_array($value, [null, ''], true)) { if (!$optional) $problems[] = "{$name} is required."; }
            elseif ($type === 'email' && !is_email((string) $value)) $problems[] = "{$name} is an email address.";
            elseif ($type === 'int' && !is_numeric($value))          $problems[] = "{$name} is a number.";
            elseif ($type === 'string' && !is_string($value))        $problems[] = "{$name} is text.";
            elseif ($type === 'json' && !is_array($value))           $problems[] = "{$name} is an array, or a JSON string holding one.";

        }

        return $problems;

    }

    // Refusals

    protected function error (string $code, string $message, int $status, array $data = []) : WP_Error {

        return new WP_Error($code, $message, ['status' => $status] + $data);

    }

    protected function get_error_data (WP_Error $error) : array {

        // WordPress's own errors carry the database's text here, as a string.
        $data = $error->get_error_data();

        return is_array($data) ? array_diff_key($data, ['status' => 0]) : [];

    }

    protected function invalid (array $problems) : WP_Error {

        return $this->error('invalid', 'Nothing was changed: ' . implode(' ', $problems), 400, ['problems' => array_values($problems)]);

    }

    protected function forbidden (string $needs, string $message = 'This account may not do that.', array $data = []) : WP_Error {

        return $this->error('forbidden', $message, 403, ['needs' => $needs] + $data);

    }

    protected function stale ($model, string $message = 'It changed since you read it: read it again, then send the change with its new version.', array $data = []) : WP_Error {

        return $this->error('stale', $message, 409, ['version' => $model->get_version()] + $data);

    }

}
