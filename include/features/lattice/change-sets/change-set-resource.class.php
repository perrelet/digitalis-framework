<?php

namespace Digitalis;

use WP_Error;

// A set as a resource: every write to it goes through the layer, and the lock is taken in before_operation().
class Change_Set_Resource extends Model_Resource {

    protected static $name        = 'change_set';
    protected static $model_class = Change_Set::class;
    protected static $label       = 'change set';

    const OPS       = ['create', 'update', 'act', 'trash', 'untrash'];
    const MAX_STEPS = 1000;


    public function get_post_type () : string {

        return '';

    }

    public function get_model_fields () : array {

        return ['purpose' => ['type' => 'textarea', 'label' => 'Purpose', 'validate' => fn ($purpose, $path) => $this->check_purpose($purpose, $path)]];

    }

    protected function check_purpose ($purpose, string $path) : array {

        if (!is_string($purpose))                               return ["{$path} is text."];
        if (strlen($purpose) > Change_Set::TEXT_BYTES)          return ["{$path} is at most " . Change_Set::TEXT_BYTES . ' bytes.'];
        if (wp_check_invalid_utf8($purpose) !== $purpose)       return ["{$path} is valid text."];

        return [];

    }

    protected function get_type_groups () : array {

        return [];

    }

    public function get_fields_for ($model, array $values = []) : array {

        return $this->get_fields();

    }

    public function get_computed () : array {

        return [
            'steps'        => fn ($set) => $this->read_steps($set),
            'state'        => fn ($set) => $set->get_state(),
            'author_id'    => fn ($set) => $set->get_author_id(),
            'reason'       => fn ($set) => $set->get_reason(),
            'note'         => fn ($set) => $set->get_note(),
            'submitted_at' => fn ($set) => $set->get_submitted_at(),
        ];

    }

    // Each step as anyone may read it, its secrets masked for whoever is reading. A read sent back to set_steps changes nothing.
    public function read_steps ($set) : array {

        return array_values(array_map(fn ($step) => array_filter([
            'step_id'      => $step['step_id'],
            'op'           => $step['op'],
            'resource'     => $step['resource'],
            'id'           => $step['id'],
            'action'       => $step['action'],
            'input'        => $step['input'],
            'values'       => $this->mask_step($step, $step['values']),
            'expected'     => is_null($step['expected']) ? null : $this->mask_step($step, $step['expected']),
            'removed'      => $step['removed'],
            'workspace_id' => $step['workspace_id'],
            'wait'         => $step['wait'],
            'marker'       => $step['marker'],
            'journal_id'   => $step['journal_id'],
            'error'        => $step['error'],
        ], fn ($value) => !in_array($value, [null, '', [], false], true)), $set->get_steps()));

    }

    protected function mask_step (array $step, array $values) : array {

        $target = Resources::inst()->get_resource($step['resource']);

        return $target ? $target->mask_held($values, is_null($step['id']) ? null : $target->find($step['id'])) : array_map([Resource_Field::class, 'mask_value'], $values);

    }

    public function get_locked_fields ($model) : array {

        if ($model->get_author_id() !== get_current_user_id()) return ['purpose' => 'Only its author changes what a set is for.'];
        if ($model->get_state() !== Change_Set::DRAFT)         return ['purpose' => 'It changes only while the set is a draft.'];

        return [];

    }

    public function get_proposable () : array {

        return [];

    }

    // Records

    public function find ($id) : ?object {

        if (!is_scalar($id) || !ctype_digit((string) $id)) return null;

        // A model is kept for the request, and its row may have gone since.
        $set = Change_Set::get_instance((int) $id)?->reload();

        return $set?->get_row() ? $set : null;

    }

    public function query (array $args) : array {

        global $wpdb;

        $where = ['1 = 1'];
        $state = (string) ($args['state'] ?? $args['status'] ?? '');

        if (!current_user_can('approve_proposals')) $where[] = $wpdb->prepare('author_id = %d', get_current_user_id());
        if ($state !== '')                          $where[] = $wpdb->prepare('state = %s', $state);

        $ids = $wpdb->get_col($wpdb->prepare('SELECT set_id FROM ' . Change_Set::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, set_id DESC LIMIT %d OFFSET %d', (int) $args['per_page'], ((int) $args['page'] - 1) * (int) $args['per_page']));

        return Change_Set::get_instances(array_map('intval', (array) $ids));

    }

    public function make_model (array $values) : object {

        return Change_Set::create(['purpose' => (string) ($values['purpose'] ?? '')]);

    }

    public function insert_model ($model, array $meta = []) : object {

        return $model->insert();

    }

    public function get_status ($model) : string {

        return $model->get_state();

    }

    public function summarise ($model) : array {

        return [
            'title'    => wp_html_excerpt($model->get_purpose(), 80, '…') ?: "Set #{$model->get_id()}",
            'status'   => $model->get_state(),
            'modified' => gmdate('c', (int) strtotime($model->get_updated_at() . ' UTC')),
        ];

    }

    public function get_edit_url ($model) : string {

        return add_query_arg('set', $model->get_id(), Changes_Page::get_instance()->get_url());

    }

    // has_undone_rows() is what says a set cannot go on (D55): an undo's own outcome cannot.
    public function get_states ($model) : array {

        return ['state' => $model->get_state(), 'undone' => Journal::inst()->has_undone_rows($model->get_key())];

    }

    public function get_capability (string $operation, $model = null) : string {

        return ($operation === 'create') ? 'propose_changes' : 'approve_proposals';

    }

    public function can (string $operation, $model = null) : bool {

        $reviews = current_user_can('approve_proposals');
        $makes   = current_user_can('propose_changes') || current_user_can('edit_posts');

        return match ($operation) {
            'create'         => $makes,
            'list'           => $makes || $reviews || $this->has_own_set(),
            'read', 'update' => $model && ($reviews || ($model->get_author_id() === get_current_user_id())),
            default          => false,
        };

    }

    protected function has_own_set () : bool {

        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . Change_Set::table() . ' WHERE author_id = %d LIMIT 1', get_current_user_id()));

    }

    public function present_changes (array $before, array $after, $model = null) : array {

        $lines = [];

        foreach (['purpose' => 'Purpose', 'state' => 'State', 'reason' => 'Reason'] as $name => $label) if (($before[$name] ?? null) !== ($after[$name] ?? null)) $lines[] = ['field' => $name, 'label' => $label, 'before' => (string) ($before[$name] ?? ''), 'after' => (string) ($after[$name] ?? '')];

        if (($before['steps'] ?? []) !== ($after['steps'] ?? [])) $lines[] = ['field' => 'steps', 'label' => 'Steps', 'before' => count((array) ($before['steps'] ?? [])) . ' steps', 'after' => count((array) ($after['steps'] ?? [])) . ' steps'];

        return (array) apply_filters('lattice.resources.present', $lines, $this, $before, $after, $model);

    }

    public function journal_marks ($model) : array {

        return ['set_key' => $model->get_key(), 'proposer_id' => $model->get_author_id()];

    }

    // Actions

    public function get_actions () : array {

        return [
            'set_steps'    => ['label' => 'Change its steps', 'input' => ['steps' => 'json'], 'when' => fn ($set) => $this->refuse_holding($set), 'validate' => fn ($set, array $input) => $this->prepare_steps($set, $input['steps'])['problems']],
        ];

    }

    public function get_consequences (Resource_Change $change) : array {

        if ($change->operation !== 'set_steps') return [];

        return array_map(fn ($step) => array_filter(['op' => $step['op'], 'resource' => $step['resource'], 'id' => $step['id'], 'action' => $step['action'], 'input' => $step['input'], 'values' => $this->mask_step($step, $step['values']), 'expected' => $step['expected'], 'removed' => $step['removed'], 'wait' => $step['wait']]), $this->prepare_steps($change->before, $change->input['steps'])['steps']);

    }

    protected function refuse_holding ($set) : string {

        if ($set->get_author_id() !== get_current_user_id()) return 'Only its author changes its steps.';
        if ($set->get_state() !== Change_Set::DRAFT)         return 'Its steps change only while it is a draft.';

        foreach ($set->get_steps() as $step) {

            if (!is_null($step['workspace_id']))                                 return 'A step with a workspace is changed in its workspace.';
            if (in_array($step['marker'], ['started', 'unknown', 'applied'], true)) return 'A step of it has been applied, or may have been.';

        }

        return '';

    }

    public function act_set_steps ($set, array $input, $change) {

        $prepared = $this->prepare_steps($set, $input['steps']);

        if ($prepared['problems']) return $this->invalid($prepared['problems']);

        return $set->replace_steps($prepared['steps']) ? $set : new WP_Error('not_saved', 'The steps could not be stored, and nothing was changed.');

    }

    // Each step as it would be held, as the current account: what validate answers from, what its preview shows and what is written.
    protected function prepare_steps ($set, array $sent) : array {

        if (!array_is_list($sent))                return ['steps' => [], 'problems' => ['steps is a list.']];
        if (count($sent) > static::MAX_STEPS)     return ['steps' => [], 'problems' => ['A set holds at most ' . static::MAX_STEPS . ' steps.']];

        $stored   = array_column($set->get_steps(), null, 'step_id');
        $named    = [];
        $steps    = [];
        $problems = [];

        foreach ($sent as $position => $step) {

            $id = is_array($step) ? ($step['step_id'] ?? null) : null;

            if (!is_null($id) && (!is_scalar($id) || !ctype_digit((string) $id))) { $problems[] = "Step {$position}: its step_id is a number."; continue; }

            if (!is_null($id) && isset($named[(int) $id])) { $problems[] = "Step {$position} repeats step {$id}."; continue; }

            if (!is_null($id)) $named[(int) $id] = true;

            [$held, $found] = is_array($step) ? $this->prepare_step($step, is_null($id) ? null : ($stored[(int) $id] ?? false)) : [null, ['It is an object.']];

            array_push($problems, ...array_map(fn ($problem) => "Step {$position}: {$problem}", $found));

            if ($held) $steps[] = $held;

        }

        // What was found of a step was found after the steps before it, so once those change it says nothing: skipped most of all, which a walk takes as done.
        $moved = false;

        foreach ($steps as $position => $step) {

            $moved = $moved || !isset($step['marker']) || (array_search($step['step_id'] ?? null, array_keys($stored), true) !== $position);

            if ($moved) $steps[$position] = array_diff_key($step, ['marker' => 0, 'marked_at' => 0, 'journal_id' => 0, 'error' => 0]);

        }

        return ['steps' => $steps, 'problems' => $problems];

    }

    protected function prepare_step (array $sent, $kept) : array {

        if ($kept === false) return [null, ['No such step in this set.']];

        foreach (['op', 'resource', 'action'] as $key) if ($kept && array_key_exists($key, $sent) && ($sent[$key] !== $kept[$key])) return [null, ["A step keeps its {$key}: send a new step."]];

        if ($kept && array_key_exists('id', $sent) && ((string) $sent['id'] !== (string) $kept['id'])) return [null, ['A step keeps its record: send a new step.']];

        $op       = $kept ? $kept['op'] : ($sent['op'] ?? null);
        $name     = $kept ? $kept['resource'] : ($sent['resource'] ?? null);
        $id       = $kept ? $kept['id'] : ($sent['id'] ?? null);
        $action   = $kept ? $kept['action'] : ($sent['action'] ?? null);
        $values   = $sent['values'] ?? ($kept['values'] ?? []);
        $input    = $sent['input'] ?? ($kept['input'] ?? []);
        $expected = $sent['expected'] ?? null;

        if (!in_array($op, static::OPS, true))                       return [null, ['Its op is one of ' . implode(', ', static::OPS) . '.']];
        if (!is_string($name))                                       return [null, ['It names a resource.']];
        if (($op === 'create') !== is_null($id))                     return [null, [($op === 'create') ? 'A create names no record.' : 'It names a record.']];
        if (!is_null($id) && (!is_scalar($id) || ((string) $id === ''))) return [null, ['It names a record.']];
        if (($op === 'act') !== is_string($action))                  return [null, [($op === 'act') ? 'It names an action.' : 'Only an act names an action.']];
        if (!is_array($values) || !is_array($input))                 return [null, ['Its values and input are objects.']];
        if ($values && !in_array($op, ['create', 'update'], true))   return [null, ['Only a create or an update holds values.']];
        if (($op === 'update') && !$values)                          return [null, ['An update holds values.']];
        if ($input && ($op !== 'act'))                               return [null, ['Only an act holds input.']];
        if (!is_null($expected) && (!is_array($expected) || ($op !== 'update'))) return [null, ['Only an update holds expected, as an object.']];

        $step = ['op' => $op, 'resource' => $name, 'id' => $id, 'action' => $action, 'input' => $input, 'values' => $values, 'expected' => null, 'wait' => array_key_exists('wait', $sent) ? !empty($sent['wait']) : !empty($kept['wait'])];

        if ($refused = Resources::inst()->refuse_step($step)) return [null, [$refused]];

        $target = Resources::inst()->get_resource($name);

        if ($kept) foreach ($values as $field => $value) if (array_key_exists($field, $kept['values'])) $values[$field] = $this->unmask_held($target, $field, $value, $kept['values'][$field]);

        $held = Resources::inst()->hold_values($name, $id, $values);

        if (is_wp_error($held)) return [null, [$held->get_error_message()]];
        if ($held['problems'])  return [null, $held['problems']];

        // A value sent back as held was filtered when it was held, and filtering it again against what is live now would change it. What was removed stays with it.
        foreach ($values as $field => $value) if ($kept && array_key_exists($field, $kept['values']) && ($value === $kept['values'][$field])) {

            $held['values'][$field] = $value;

            unset($held['removed'][$field]);

            if (isset($kept['removed'][$field])) $held['removed'][$field] = $kept['removed'][$field];

        }

        $step['values']  = $held['values'];
        $step['removed'] = array_intersect_key($held['removed'], $held['values']);

        if (!is_null($id)) $step['id'] = $id = $target->find($id)->get_id();

        // Holding a value answers its mask, so an account that may not write the record could test guesses at the secret it holds (CS17).
        if (!is_null($id) && !$target->can('update', $target->find($id)) && array_filter(array_intersect_key($target->get_fields(), $values), fn ($field) => $field->holds_secret())) return [null, ['An account that may not write this record cannot hold a value for a secret.']];

        // An action's input runs later as whoever applies it.
        if (($input !== ($kept['input'] ?? null)) && !current_user_can('unfiltered_html')) {

            $step['input'] = map_deep($input, fn ($leaf) => is_string($leaf) ? wp_kses_post($leaf) : $leaf);

            if ($lost = Markup::subtract(Markup::count($input), Markup::count($step['input']))) $step['removed']['input'] = $lost;

        } elseif ($input && isset($kept['removed']['input'])) {

            $step['removed']['input'] = $kept['removed']['input'];

        }

        if ($op === 'update') {

            [$step['expected'], $problem] = $this->prepare_expected($target, $id, $held['values'], $expected, $kept);

            if ($problem) return [null, [$problem]];

        }

        if (!$kept) return [$step, []];

        $step += ['step_id' => $kept['step_id'], 'workspace_id' => $kept['workspace_id']];

        // A kept step that changed is checked again: anything it was marked with was about what it held before.
        $changed = wp_json_encode([$step['values'], $step['expected'], $step['input'], $step['wait']]) !== wp_json_encode([$kept['values'], $kept['expected'], $kept['input'], $kept['wait']]);

        return [$step + ($changed ? [] : array_intersect_key($kept, ['marker' => 0, 'marked_at' => 0, 'journal_id' => 0, 'error' => 0])), []];

    }

    // Held masked: every comparison masks a start first, so no start needs to hold a secret. The field a kept step gains takes its sent start, or else what is live.
    protected function prepare_expected (Model_Resource $target, $id, array $values, ?array $sent, $kept) : array {

        $base = (array) ($kept['expected'] ?? []);

        if (!is_null($sent)) {

            if (array_diff_key($sent, $values, $base)) return [null, 'expected names a field the step does not hold.'];

            $missing = array_diff_key($values, $sent);

            if ($kept ? array_intersect_key($missing, $base) : $missing) return [null, 'expected names every field the step holds, or none.'];

        }

        $start = array_intersect_key((array) $sent, $values) + array_intersect_key($base, $values);

        if (array_diff_key($values, $start)) {

            $model = $target->find($id);

            $target->refresh($model);

            // A field shown only through the terms this step brings has no live value to start from, and every field needs a start.
            $start += array_intersect_key($target->read($model), $values) + array_fill_keys(array_keys($values), null);

        }

        return [$target->mask_held(array_merge(array_intersect_key($values, $start), $start), is_null($id) ? null : $target->find($id)), null];

    }

    // A mask stands for the value this step holds, and for no other. mask_held() masks some values whole, which a field's unmask() passes straight through.
    protected function unmask_held (Model_Resource $target, string $field, $sent, $raw) {

        if (Resource_Field::is_mask($sent)) return (Resource_Field::mask_value($raw) === $sent) ? $raw : $sent;

        $definition = $target->get_field($field);

        return $definition ? $definition->unmask($sent, $raw) : $sent;

    }

    protected function invalid (array $problems) : WP_Error {

        return new WP_Error('invalid', 'Nothing was changed: ' . implode(' ', $problems), ['status' => 400, 'problems' => $problems]);

    }

    // The lock

    public function before_operation ($model, string $operation) : ?WP_Error {

        if ($model->is_held_here()) return $this->refuse('This request is already changing it.');
        if (!$model->take_lock())   return $this->refuse('Another request is changing it.');

        try {

            $this->settle($model);

            $reason = $this->refuse_under_lock($model, $operation);

        } catch (\Throwable $e) {

            // The layer pairs after_operation() only with a null from here.
            $model->release_lock();

            throw $e;

        }

        if (!$reason) return null;

        $model->release_lock();

        return $this->refuse($reason);

    }

    public function after_operation ($model, string $operation) : void {

        $model->release_lock();

    }

    // A request that died left its markers and not its state, and nobody could say so until now.
    protected function settle ($model) : void {

        Resources::inst()->settle_started($model);

        $model->settle_state();

    }

    // Refusals ran unlocked, and settling may just have changed the answer. An operation with no action of its own is a write of the purpose.
    protected function refuse_under_lock ($model, string $operation) : string {

        $model->reload();

        // A marker that could not be written just now leaves a step nobody can answer for.
        if (in_array('started', array_column($model->get_steps(), 'marker'), true)) return 'A step of it could not be settled.';

        $actions = $this->get_actions();

        return isset($actions[$operation]) ? (string) (($actions[$operation]['when'] ?? null)?->__invoke($model)) : (string) ($this->get_locked_fields($model)['purpose'] ?? '');

    }

    protected function refuse (string $reason) : WP_Error {

        return new WP_Error('locked', $reason, ['status' => 409, 'reason' => $reason]);

    }

    // A row the layer does not write for it: an action through the service, the cron, a delete. It carries its own marks, so a walk under way never claims it.
    public function write_row ($model, string $action, string $why, array $before, int $version_before, string $via) : int {

        return Journal::inst()->write([
            'via'            => $via,
            'resource'       => $this->get_name(),
            'record_id'      => $model->get_id(),
            'action'         => $action,
            'why'            => $why,
            'before'         => $before,
            'after'          => $this->read($model->reload()),
            'version_before' => $version_before,
            'version_after'  => $model->get_version(),
        ] + $this->journal_marks($model));

    }

    // A delete cannot wait for the lock, and move_to() keeps whatever a walk ends as from overwriting this.
    public function mark_broken ($set, string $why) : void {

        $before  = $this->read($set);
        $version = $set->get_version();

        if (!$set->move_to(Change_Set::BROKEN, $why)) throw new \RuntimeException('Its state could not be written.');

        $set->raise_version();

        $this->write_row($set, 'broken', $why, $before, $version, is_admin() ? 'admin' : 'other');

        do_action('lattice.change_set.broken', $set);

    }

}
