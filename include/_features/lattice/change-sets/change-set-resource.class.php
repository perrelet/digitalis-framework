<?php

namespace Lattice;

use WP_Error;

// A set as a resource: every write to it goes through the layer, and the lock is taken in before_operation().
class Change_Set_Resource extends Model_Resource {

    protected static $name        = 'change_set';
    protected static $model_class = Change_Set::class;
    protected static $label       = 'change set';

    const OPS       = ['create', 'update', 'act', 'trash', 'untrash'];
    const MAX_STEPS = 1000;

    protected array $announcing = [];

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
    // Another's set shows a scoped reader only the steps it may read.
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
        ], fn ($value) => !in_array($value, [null, '', [], false], true)), $this->get_readable_steps($set)));

    }

    public function get_readable_steps ($set) : array {

        return $set->is_mine() ? $set->get_steps() : array_filter($set->get_steps(), [Resources::inst(), 'may_read_step']);

    }

    protected function mask_step (array $step, array $values) : array {

        $target = Resources::inst()->get_resource($step['resource']);

        return $target ? $target->mask_held($values, is_null($step['id']) ? null : $target->find($step['id'])) : array_map([Resource_Field::class, 'mask_value'], $values);

    }

    public function get_locked_fields ($model) : array {

        if (!$model->is_mine()) return ['purpose' => 'Only its author changes what a set is for.'];
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

        if (!current_user_can('approve_proposals')) $where[] = Change_Set::where_mine();
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
            'read', 'update' => $model && ($reviews || $model->is_mine()),
            default          => false,
        };

    }

    protected function has_own_set () : bool {

        global $wpdb;

        return (bool) $wpdb->get_var('SELECT 1 FROM ' . Change_Set::table() . ' WHERE ' . Change_Set::where_mine() . ' LIMIT 1');

    }

    public function present_changes (array $before, array $after, $model = null) : array {

        $lines = [];

        foreach (['purpose' => 'Purpose', 'state' => 'State', 'reason' => 'Reason', 'note' => 'Note', 'submitted_at' => 'Submitted'] as $name => $label) if (($before[$name] ?? null) !== ($after[$name] ?? null)) $lines[] = ['field' => $name, 'label' => $label, 'before' => (string) ($before[$name] ?? ''), 'after' => (string) ($after[$name] ?? '')];

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
            'apply_set'    => ['label' => 'Apply it', 'writes_through' => true, 'input' => ['seen' => '?json'], 'when' => fn ($set) => $this->refuse_applying($set)],
            'undo_set'     => ['label' => 'Undo what it applied', 'writes_through' => true, 'input' => ['seen' => '?json']],
            'withdraw_set' => ['label' => 'Withdraw it', 'when' => fn ($set) => $this->refuse_withdrawing($set)],
            'settle_step'  => ['label' => 'Settle a step', 'input' => ['position' => 'int', 'outcome' => 'string'], 'when' => fn ($set) => $this->refuse_settling($set), 'validate' => fn ($set, array $input) => $this->check_settling($set, $input)],
            'rebase_set'   => ['label' => 'Start from what is live', 'when' => fn ($set) => $this->refuse_rebasing($set)],
        ];

    }

    public function get_consequences (Resource_Change $change) : array {

        if ($change->operation === 'rebase_set') return $this->prepare_rebase($change->before)['moved'];

        if ($change->operation !== 'set_steps') return [];

        return array_map(fn ($step) => array_filter(['op' => $step['op'], 'resource' => $step['resource'], 'id' => $step['id'], 'action' => $step['action'], 'input' => $step['input'], 'values' => $this->mask_step($step, $step['values']), 'expected' => $step['expected'], 'removed' => $step['removed'], 'wait' => $step['wait']]), $this->prepare_steps($change->before, $change->input['steps'])['steps']);

    }

    protected function refuse_holding ($set) : string {

        if (!$set->is_mine()) return 'Only its author changes its steps.';
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

        if ($refused = Resources::inst()->refuse_step($step) ?: Resources::inst()->refuse_proposing($step)) return [null, [$refused]];

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

    // Applying

    protected function refuse_applying ($set) : string {

        if (!$set->is_mine()) return 'Only its author applies a set of their own.';
        if ($set->get_submitted_at())                        return 'It is with a reviewer.';
        if ($set->is_closed())                               return 'It is closed.';
        if ($set->get_state() === Change_Set::BROKEN)        return 'A record it names has been deleted: withdraw it.';

        return '';

    }

    public function act_apply_set ($set, array $input, string $via) {

        if (trim($set->get_purpose()) === '') return $this->invalid(['Say what it is for before applying it.']);
        if (!$set->get_steps())               return $this->invalid(['It holds no steps.']);

        if (!empty($input['dry_run'])) return ['dry_run' => true, 'version' => $set->get_version(), 'steps' => Resources::inst()->preview_set($set)];

        return $this->walk_set($set, $input['seen'] ?? null, (string) ($input['why'] ?? ''), $via, 'apply_set');

    }

    // What seen must hold, whoever is walking it: $seen['version'] was the set's as they looked, $seen['steps'] each step's.
    public function walk_set ($set, $seen, string $why, string $via, string $action) {

        if ($refusal = $this->check_seen($set, $seen, 'steps')) return $refusal;

        $before  = $this->read($set);
        $version = $set->get_version();
        $result  = Resources::inst()->apply_set($set, $seen['steps'], $via);

        if (is_wp_error($result)) return $result;

        // Another request may hold it now, and the state is theirs to write.
        if (!$set->holds_lock()) return $result + ['state' => $set->reload()->get_state(), 'version' => $set->get_version()];

        $this->settle_walk($set, $result);

        $answer = $result + $this->finish_action($set, $action, $why, $before, $version, $via) + ['state' => $set->reload()->get_state()];

        if ($answer['version'] !== $version) $this->announce_walk($set, $result);

        return $answer;

    }

    // A through action raises the version and writes the row the layer writes for the others, when it did anything.
    protected function finish_action ($set, string $action, string $why, array $before, int $version, string $via) : array {

        if ($this->read($set->reload()) === $before) return ['version' => $set->get_version()];

        $set->raise_version();

        $row = $this->write_row($set, $action, $why, $before, $version, $via);

        return ['version' => $set->get_version()] + ($row ? [] : ['journaled' => false]);

    }

    protected function check_seen ($set, $seen, string $list) : ?WP_Error {

        if (!is_array($seen) || !is_numeric($seen['version'] ?? null) || !is_array($seen[$list] ?? null)) return $this->invalid(["Send seen as its dry run answered it: the set's version and its {$list}."]);

        if (!$set->holds_lock()) return $this->refuse('The lock was lost.');

        if ((int) $seen['version'] !== $set->get_version()) return new WP_Error('stale', 'It changed since you looked: run its dry run again.', ['status' => 409, 'version' => $set->get_version()]);

        return null;

    }

    // What a walk's outcome does to the set: CX1's table.
    protected function settle_walk ($set, array $result) : void {

        $applied   = $set->has_applied_step();
        $submitted = (bool) $set->get_submitted_at();
        $reason    = (string) ($result['reason'] ?? '');

        match ($result['outcome']) {
            'applied'       => $this->close($set, Change_Set::APPLIED) || error_log("Change sets: set {$set->get_id()} applied, and could not be closed."),
            'unknown'       => $set->move_to(Change_Set::UNKNOWN, $reason),
            'awaiting_look' => $set->move_to($submitted ? Change_Set::AWAITING_REVIEW : ($applied ? Change_Set::APPLIED_IN_PART : Change_Set::DRAFT), $reason),
            default         => $set->move_to($applied ? Change_Set::APPLIED_IN_PART : Change_Set::DRAFT, $reason),
        };

    }

    // Looking again is the author's own business on a set never offered: CX1's table.
    protected function announce_walk ($set, array $result) : void {

        $state = $set->get_state();

        if (($state === Change_Set::AWAITING_REVIEW) || (($state === Change_Set::APPLIED_IN_PART) && in_array($result['outcome'], ['refused', 'failed'], true))) $this->announce($state, $set);

    }

    // Heard once its row is written and its lock released, so a listener can read one and take the other.
    protected function announce (string $hook, ...$args) : void {

        $this->announcing[] = [$hook, $args];

    }

    protected function fire_announced () : void {

        $announcing = $this->announcing;

        $this->announcing = [];

        foreach ($announcing as [$hook, $args]) do_action("lattice.change_set.{$hook}", ...$args);

    }

    // A closed set keeps no secret it no longer needs (D44), and one that stayed broken has not closed. As mask_held() answers whoever closes it (D57).
    public function close ($set, string $state) : bool {

        $steps = array_map(function ($step) {

            $step['values'] = $this->mask_step($step, $step['values']);

            if (!is_null($step['expected'])) $step['expected'] = $this->mask_step($step, $step['expected']);

            return $step;

        }, $set->get_steps());

        // Together, or a set could close holding what it no longer needs, with nothing to come back for it.
        return Change_Set::transaction(fn () => $set->move_to($state) && (($set->get_state() !== $state) || $set->replace_steps(array_values($steps))));

    }

    public function act_undo_set ($set, array $input, string $via) {

        if (!empty($input['dry_run'])) return ['dry_run' => true, 'version' => $set->get_version(), 'rows' => Resources::inst()->preview_undo($set)];

        $seen = $input['seen'] ?? null;

        if ($refusal = $this->check_seen($set, $seen, 'rows')) return $refusal;

        $before  = $this->read($set);
        $version = $set->get_version();
        $result  = Resources::inst()->undo_set($set, $seen['rows'], $via);

        if (is_wp_error($result)) return $result;

        if (!$set->holds_lock() || !$result['undone']) return $result + ['version' => $set->get_version()];

        // Its state and steps are as they were, so what it did is only in its result.
        $set->raise_version();

        $row = $this->write_row($set, 'undo_set', (string) ($input['why'] ?? ''), $before, $version, $via);

        return $result + ['version' => $set->get_version()] + ($row ? [] : ['journaled' => false]);

    }

    protected function refuse_unsettled ($set) : string {

        return array_intersect(['started', 'unknown'], array_column($set->get_steps(), 'marker')) ? 'A step may or may not have landed: settle it first.' : '';

    }

    protected function refuse_withdrawing ($set) : string {

        if (!$set->is_mine()) return 'Only its author withdraws it.';

        return $set->is_closed() ? 'It is closed.' : $this->refuse_unsettled($set);

    }

    public function act_withdraw_set ($set, array $input, $change) {

        return $this->close($set, Change_Set::WITHDRAWN) ? $set : new WP_Error('not_saved', 'It could not be closed, and nothing was changed.');

    }

    // The cron's own: it has no account for the layer to ask, and writes its row itself. Under the lock it looks again, since a dead walk's state only settles there.
    public function withdraw_idle ($set, string $before, string $why) : void {

        if ($set->is_held_here() || !$set->take_lock()) return;

        try {

            $this->settle($set);

            $set->reload();

            if (!in_array($set->get_state(), [Change_Set::DRAFT, Change_Set::BROKEN], true) || $this->refuse_unsettled($set) || ($set->get_updated_at() >= $before)) return;

            $was     = $this->read($set);
            $version = $set->get_version();

            if (!$this->close($set, Change_Set::WITHDRAWN)) throw new \RuntimeException('It could not be closed.');

            $set->raise_version();

            $this->write_row($set, 'withdraw_set', $why, $was, $version, 'cron');

        } finally {

            $set->release_lock();

        }

    }

    protected function refuse_settling ($set) : string {

        return $this->refuse_unsettled($set) ? '' : 'No step is waiting to be settled.';

    }

    protected function check_settling ($set, array $input) : array {

        $problems = in_array($input['outcome'], ['applied', 'not_applied'], true) ? [] : ['outcome is applied or not_applied.'];

        return isset($set->get_steps()[(int) $input['position']]) ? $problems : [...$problems, 'It holds no such step.'];

    }

    public function act_settle_step ($set, array $input, $change) {

        $position = (int) $input['position'];
        $step     = $set->get_steps()[$position];

        if ($step['marker'] !== 'unknown') return $this->refuse('That step is not waiting to be settled.');

        global $wpdb;

        $target = Resources::inst()->get_resource($step['resource']);
        $record = null;

        if ($step['op'] === 'create') {

            $record = $target?->find_by_set_key(Resources::get_set_key($set->get_key(), $position));

            // A read that failed says nothing about whether a record exists, and being wrong makes a second.
            if ($wpdb->last_error !== '') return new WP_Error('not_saved', 'Its record could not be looked for, and nothing was changed.');

            $record ??= is_null($step['id']) ? null : $target?->find($step['id']);

        }

        $applied = $input['outcome'] === 'applied';

        if ($applied && ($step['op'] === 'create') && !$record) return $this->refuse('No record carries its key, so it did not land.');

        if (!$applied && $record && ($target->get_status($record) !== 'trash')) return $this->refuse('Its record exists, so applying again would make a second: trash it first.');

        $action = ($step['op'] === 'act') ? $step['action'] : $step['op'];
        $rows   = $applied ? array_filter(Journal::inst()->rows_for_set($set->get_key()), fn ($row) => ($row['set_step'] === $position) && is_null($row['undoes']) && ($row['action'] === $action) && (is_null($step['id']) || ($row['record_id'] === (string) $step['id']))) : [];
        $detail = $applied ? array_filter(['journal_id' => $rows ? end($rows)['id'] : null, 'record_id' => $record?->get_id()]) : (($step['op'] === 'create') ? ['record_id' => null] : []);

        $settled = Change_Set::transaction(function () use ($set, $position, $applied, $detail) {

            if (!$set->mark($position, $applied ? 'applied' : '', $detail)) return false;

            return in_array('unknown', array_column($set->get_steps(), 'marker'), true) || $set->move_to($set->has_applied_step() ? Change_Set::APPLIED_IN_PART : Change_Set::DRAFT);

        });

        if (!$settled) return new WP_Error('not_saved', 'It could not be settled, and nothing was changed.');

        // So it cannot settle a later attempt.
        if (!$applied && $record) $target->forget_set_key($record);

        return $set;

    }

    // Rebasing

    protected function refuse_rebasing ($set) : string {

        if (!$set->is_mine()) return 'Only its author rebases it.';

        if (!in_array($set->get_state(), [Change_Set::DRAFT, Change_Set::AWAITING_REVIEW, Change_Set::APPLIED_IN_PART], true)) return 'It cannot be rebased in this state.';

        return $this->refuse_unsettled($set);

    }

    // Each step not yet applied takes what is live as the start of a field that conflicts, and holds its values again as its author, since a value kept only because the record once held it is filtered if the record no longer does.
    protected function prepare_rebase ($set) : array {

        $steps = $set->get_steps();
        $moved = [];
        $left  = [];

        foreach ($steps as $position => $step) {

            if (in_array($step['marker'], ['applied', 'skipped'], true) || ($step['op'] !== 'update') || is_null($step['expected'])) continue;

            $target   = Resources::inst()->get_resource($step['resource']);
            $model    = $target?->find($step['id']);
            $compared = Resources::inst()->compare_expected($step['resource'], $step['id'], $step['values'], $step['expected']);

            if (is_wp_error($compared)) { $left[] = "Step {$position}: {$compared->get_error_message()}"; continue; }

            // A field this account is not shown stays as it is, which does not stop the ones beside it.
            $held = Resources::inst()->hold_values($step['resource'], $step['id'], array_intersect_key($step['values'], $target->get_fields_for($model, $step['values'])));

            if (is_wp_error($held)) { $left[] = "Step {$position}: {$held->get_error_message()}"; continue; }

            if ($held['problems']) { $left[] = "Step {$position}: " . implode(' ', $held['problems']); continue; }

            $held['values'] = array_replace($step['values'], $held['values']);

            foreach ($compared['withheld'] as $field) $left[] = "Step {$position}: {$field} is withheld from this account.";

            $expected = $step['expected'];

            foreach ($compared['conflicts'] as $conflict) {

                if (!empty($conflict['not_shown'])) { $left[] = "Step {$position}: {$conflict['field']} is not shown to this account."; continue; }

                $expected[$conflict['field']] = $this->mask_step($step, [$conflict['field'] => $conflict['live']])[$conflict['field']];

                $moved[] = ['step' => $position, 'field' => $conflict['field'], 'start' => $expected[$conflict['field']]];

            }

            $removed = array_intersect_key($held['removed'] + $step['removed'], $held['values']);
            $changed = wp_json_encode([$expected, $held['values']]) !== wp_json_encode([$step['expected'], $step['values']]);

            $steps[$position] = ['expected' => $expected, 'values' => $held['values'], 'removed' => $removed] + ($changed ? ['marker' => '', 'marked_at' => null, 'journal_id' => null, 'error' => null] : []) + $step;

        }

        return ['steps' => $steps, 'moved' => $moved, 'left' => $left];

    }

    public function act_rebase_set ($set, array $input, $change) {

        $rebase = $this->prepare_rebase($set);

        $state = array_filter($rebase['steps'], fn ($step) => $step['marker'] === 'applied') ? Change_Set::APPLIED_IN_PART : Change_Set::DRAFT;

        // Together, or a reviewer could still be offered what its author has rebased.
        if (!Change_Set::transaction(fn () => $set->replace_steps(array_values($rebase['steps'])) && $set->move_to($state, implode(' ', $rebase['left']) ?: null))) return new WP_Error('not_saved', 'The steps could not be stored, and nothing was changed.');

        return $set;

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

        $this->fire_announced();

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
