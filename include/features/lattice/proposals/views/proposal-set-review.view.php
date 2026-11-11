<?php

namespace Digitalis;

class Proposal_Set_Review extends View {

    protected static $template      = 'proposal-set-review';
    protected static $template_path = __DIR__ . '/../templates/';

    protected static $defaults = [
        'set_id' => 0,
    ];

    // Text this long or on several lines is compared word by word.
    const LONG_TEXT = 120;

    public function params (&$p) {

        $review = Resources::inst()->view('change_set', $p['set_id'], 'review');

        $p['refusal'] = is_wp_error($review) ? $review->get_error_message() : '';
        $p['review']  = $review;
        $p['forms']   = [];
        $p['reject']  = '';
        $p['undo']    = [];
        $p['refused'] = [];
        $p['status']  = [];
        $p['byline']  = [];
        $p['steps']   = [];

        if (!$p['refusal']) {

            $this->prepare_forms($p);

            $set = Resources::inst()->get_resource('change_set')->find($p['set_id']);

            $journal     = Journal::inst();
            $rows        = array_column($journal->rows_for_set($set->get_key()), null, 'id');
            $undone      = array_flip(array_column($journal->undoing(array_column($review['steps'], 'journal_id')), 'undoes'));
            $p['steps']  = array_map(fn ($position) => $this->present_step($position, $review['steps'][$position], $review, $rows, $undone), array_keys($review['steps']));
            $p['status'] = $this->describe_status($review, $p['steps'], (bool) array_filter($p['undo'], fn ($row) => !empty($row['can_undo'])));
            $p['byline'] = $this->describe_byline($set, $review);
            $p['undo']   = array_map(fn ($row) => $this->describe_undo_row($row), $p['undo']);

        }

        parent::params($p);

    }

    protected function prepare_forms (&$p) : void {

        $api    = Resources::inst();
        $set    = $api->get_resource('change_set')->find($p['set_id']);
        $record = $api->get('change_set', $p['set_id']);
        $review = $p['review'];
        $usable = fn (string $action) => !empty($record['actions'][$action]['usable']);
        $url    = fn (string $action) => Proposals_Admin::get_set_action_url($action, $set);
        $seen   = fn (array $looks, array $keys) => array_map(fn ($look) => array_intersect_key($look, $keys), $looks);

        foreach (['submit_proposal' => 'Submit for review', 'rebase_proposal' => 'Start from what is live'] as $action => $label) if ($usable($action)) $p['forms'][] = ['label' => $label, 'url' => $url($action), 'primary' => $action === 'submit_proposal'];

        // Refused whole, it would go back to its author with nothing to tell them: sending it back says why.
        $p['refused'] = array_filter($review['steps'], fn ($step) => empty($step['reached']) && empty($step['done']) && !in_array($step['refusal']['code'] ?? 'stale', ['stale'], true));

        // Undone, it could only be refused (D55).
        if ($usable('approve_proposal') && !$review['undone'] && !$p['refused']) $p['forms'][] = ['label' => 'Approve and apply', 'url' => $url('approve_proposal'), 'primary' => true, 'fields' => ['seen' => wp_json_encode(['version' => $review['version'], 'steps' => $seen($review['steps'], ['fingerprint' => 0, 'version' => 0, 'step_hash' => 0])])], 'confirm' => 'Apply these changes to what is live now?'];

        if ($usable('reject_proposal')) $p['reject'] = $url('reject_proposal');

        if ($usable('withdraw_set')) $p['forms'][] = ['label' => 'Withdraw', 'url' => $url('withdraw_set'), 'confirm' => 'Withdraw this set? What it applied stays.'];

        if ($usable('settle_step')) foreach ($set->get_steps() as $position => $step) if ($step['marker'] === 'unknown') foreach (['applied' => 'landed', 'not_applied' => 'did not land'] as $outcome => $words) $p['forms'][] = ['label' => 'Change ' . ($position + 1) . " {$words}", 'url' => $url('settle_step'), 'fields' => ['position' => $position, 'outcome' => $outcome]];

        $undo = $usable('undo_set') ? $api->act('change_set', $p['set_id'], 'undo_set', ['dry_run' => true], 'admin') : null;

        if (!is_array($undo) || empty($undo['rows'])) return;

        $p['undo'] = $undo['rows'];

        if (array_filter($undo['rows'], fn ($row) => !empty($row['can_undo']))) $p['forms'][] = ['label' => 'Undo what it applied', 'url' => $url('undo_set'), 'fields' => ['seen' => wp_json_encode(['version' => $undo['version'], 'rows' => $seen($undo['rows'], ['fingerprint' => 0, 'version' => 0])])], 'confirm' => 'Undo what this set applied, newest first?'];

    }

    // The set

    protected function describe_status (array $review, array $steps, bool $may_undo) : array {

        $total    = count($steps);
        $live     = count(array_filter($steps, fn ($step) => $step['live'] && !$step['undone']));
        $count    = fn (int $n) => sprintf(_n('%d change', '%d changes', $n), $n);
        $reviewer = $review['as'] === 'reviewer';

        // D55: undone at all, it cannot go on.
        $ended = $review['closed'] ? '' : ' It cannot go on: withdraw it.';

        if ($review['undone']) return $live
            ? ['tone' => 'paused', 'label' => $may_undo ? 'Paused while undoing' : 'Partly undone', 'message' => "{$count($live)} still live." . ($may_undo ? ' Each was checked only once the ones after it were undone: press Undo what it applied again to finish.' : '') . $ended]
            : ['tone' => 'neutral', 'label' => 'Undone', 'message' => 'Every change it made is undone.' . $ended];

        $waiting = ['tone' => 'waiting', 'label' => $reviewer ? 'Waiting for your review' : 'Waiting for review', 'message' => $live ? "{$live} of {$count($total)} live; the rest are offered again." : "{$count($total)}, none live yet."];

        return match ($review['state']) {
            Change_Set::DRAFT           => ['tone' => 'neutral', 'label' => 'Draft', 'message' => $review['note'] ? 'Sent back with a note, below.' : 'Not yet offered for review.'],
            Change_Set::SUBMITTED       => $waiting,
            Change_Set::AWAITING_REVIEW => ['tone' => 'paused', 'label' => 'Paused', 'message' => "{$live} of {$count($total)} live. What is left could only be checked once those landed: " . ($reviewer ? 'look at it below, then approve again.' : 'a reviewer looks at it next.')],
            Change_Set::APPLIED         => ['tone' => 'done', 'label' => 'Applied', 'message' => 'Every change is live.'],
            Change_Set::APPLIED_IN_PART => $review['submitted_at'] ? $waiting : ['tone' => 'stopped', 'label' => 'Partly applied', 'message' => "{$live} of {$count($total)} live; the rest were refused or failed, below."],
            Change_Set::UNKNOWN         => ['tone' => 'stopped', 'label' => 'Unclear', 'message' => 'A change may or may not have landed: check its record, then say which below.'],
            Change_Set::BROKEN          => ['tone' => 'stopped', 'label' => 'Broken', 'message' => 'A record it changes has gone, so it cannot go on: withdraw it.'],
            Change_Set::WITHDRAWN       => ['tone' => 'neutral', 'label' => 'Withdrawn', 'message' => 'Its author withdrew it. What it applied stays.'],
            default                     => ['tone' => 'neutral', 'label' => ucfirst(str_replace('_', ' ', $review['state'])), 'message' => ''],
        };

    }

    protected function describe_byline ($set, array $review) : array {

        $author   = get_userdata($review['author_id']);
        $password = ($uuid = $set->get_credential()) ? (\WP_Application_Passwords::get_user_application_password($review['author_id'], $uuid)['name'] ?? 'a password since revoked') : '';
        $at       = $review['submitted_at'] ? strtotime($review['submitted_at'] . ' UTC') : null;

        return [
            'author'   => $author ? $author->display_name : 'An account since removed',
            'password' => $password,
            'when'     => $at ? wp_date('j M Y, H:i', $at) : '',
            'ago'      => $at ? human_time_diff($at) . ' ago' : '',
        ];

    }

    // Steps

    protected function present_step (int $position, array $step, array $review, array $rows, array $undone) : array {

        $resource  = Resources::inst()->get_resource($step['resource']);
        $found     = (!empty($step['id']) && $resource) ? $resource->find($step['id']) : null;
        $model     = ($found && !Resources::inst()->refuse_read($resource, $found)) ? $found : null;
        $live      = $this->is_live($step);
        $is_undone = $live && isset($undone[$step['journal_id'] ?? 0]);
        $row       = !empty($step['journal_id']) ? ($rows[$step['journal_id']] ?? Journal::inst()->find((int) $step['journal_id'])) : null;

        return [
            'number'       => $position + 1,
            'type'         => $resource?->get_label() ?? $step['resource'],
            'record'       => $model ? ($resource->summarise($model)['title'] ?: "#{$step['id']}") : ($found ? "#{$step['id']}, which you may not read" : (($step['op'] === 'create') ? 'A new one' : "#{$step['id']}, since gone")),
            'url'          => $model ? $resource->get_edit_url($model) : '',
            'does'         => $this->describe_step($resource, $step),
            'live'         => $live,
            'undone'       => $is_undone,
            'state'        => $this->describe_step_state($step, $review, $live, $is_undone),
            'lines'        => ($found && !$model) ? [] : $this->present_lines($resource, $model, $step, $row),
            'consequences' => array_map(fn ($consequence) => $this->describe_consequence($consequence), (array) ($step['preview']['consequences'] ?? [])),
            'notes'        => $this->list_step_notes($resource, $step),
            'details'      => static::show($step),
        ];

    }

    protected function describe_step (?Model_Resource $resource, array $step) : string {

        $fields = $resource ? $resource->get_fields() : [];
        $labels = array_map(fn ($name) => $this->label_field($fields, $name), array_keys((array) ($step['values'] ?? [])));

        return match ($step['op']) {
            'update'  => 'Change ' . ($labels ? $this->join_words($labels) : 'what it may not show you'),
            'create'  => 'Create',
            'trash'   => 'Move to the Trash',
            'untrash' => 'Restore from the Trash',
            'act'     => $this->label_action($resource, (string) $step['action']),
            default   => ucfirst($step['op']),
        };

    }

    // As present_change() names a field.
    protected function label_field (array $fields, string $name) : string {

        return isset($fields[$name]) ? (($fields[$name]->acf['label'] ?? '') ?: $name) : $name;

    }

    protected function label_action (?Model_Resource $resource, string $action) : string {

        return $resource?->get_actions()[$action]['label'] ?? $action;

    }

    protected function is_live (array $step) : bool {

        return !empty($step['done']) || in_array($step['marker'] ?? '', ['applied', 'skipped'], true);

    }

    protected function describe_step_state (array $step, array $review, bool $live, bool $undone) : array {

        $after = array_map('strval', array_map(fn ($position) => $position + 1, (array) ($step['after'] ?? [])));

        return match (true) {
            ($step['marker'] ?? '') === 'unknown'      => ['tone' => 'stopped', 'label' => 'May not have landed'],
            $undone                                    => ['tone' => 'neutral', 'label' => 'Undone'],
            $live && $review['undone']                 => ['tone' => 'done', 'label' => 'Live, still to undo'],
            $live                                      => ['tone' => 'done', 'label' => 'Live'],
            !empty($step['refusal'])                   => ['tone' => 'stopped', 'label' => 'Refused'],
            !empty($step['conflicts'])                 => ['tone' => 'paused', 'label' => 'Changed since'],
            !empty($step['reached'])                   => ['tone' => 'waiting', 'label' => 'Checked once ' . $this->join_words($after) . (count($after) > 1 ? ' are' : ' is') . ' live'],
            $review['closed']                          => ['tone' => 'neutral', 'label' => 'Not applied'],
            $review['as'] === 'reviewer'               => ['tone' => 'waiting', 'label' => 'Will apply'],
            default                                    => ['tone' => 'neutral', 'label' => 'Proposed'],
        };

    }

    protected function join_words (array $words) : string {

        return (count($words) > 1) ? implode(', ', array_slice($words, 0, -1)) . ' and ' . end($words) : implode('', $words);

    }

    // Once applied, from its journal row; before, what it holds against what is live.
    protected function present_lines (?Model_Resource $resource, $model, array $step, ?array $row) : array {

        if (!$resource) return [];

        [$before, $after] = match (true) {
            (bool) $row                                                                  => [(array) ($row['before'] ?? []), (array) ($row['after'] ?? [])],
            in_array($step['op'], ['update', 'create'], true) && !empty($step['values']) => [$model ? array_intersect_key($resource->read($model), $step['values']) : [], $step['values']],
            default                                                                      => [[], []],
        };

        // Computed by the record's own save, not proposed; withheld, not for this reader.
        $fields   = $resource->get_fields();
        $withheld = array_flip((array) ($step['withheld'] ?? []));
        $kept     = fn (array $values) => array_filter($values, fn ($name) => empty($fields[$name]?->computed) && !isset($withheld[$name]), ARRAY_FILTER_USE_KEY);
        [$before, $after] = [$kept($before), $kept($after)];

        // present_change() shortens long text, which a word diff needs whole.
        return array_map(function ($line) use ($before, $after) {

            [$was, $will] = [$this->find_path($before, (string) ($line['field'] ?? '')), $this->find_path($after, (string) ($line['field'] ?? ''))];

            $text = (is_string($was) || is_null($was)) && (is_string($will) || is_null($will));

            return ($text && ($this->is_long((string) $was) || $this->is_long((string) $will))) ? ['before' => (string) $was, 'after' => (string) $will, 'long' => true] + $line : $line + ['long' => false];

        }, array_values(array_filter($resource->present_changes($before, $after, $model), 'is_array')));

    }

    protected function find_path (array $values, string $path) : mixed {

        foreach (explode('.', $path) as $key) {

            if (!is_array($values) || !array_key_exists($key, $values)) return null;

            $values = $values[$key];

        }

        return $values;

    }

    protected function is_long (string $text) : bool {

        return (mb_strlen($text) > static::LONG_TEXT) || str_contains($text, "\n");

    }

    protected function describe_consequence (array $consequence) : array {

        $items  = array_map(fn ($item) => is_scalar($item) ? (string) $item : (string) ($item['title'] ?? $item['label'] ?? ''), (array) ($consequence['items'] ?? []));
        $extras = array_map(fn ($key) => is_scalar($consequence[$key] ?? null) ? (string) $consequence[$key] : '', (array) ($consequence['volatile'] ?? []));

        return ['label' => (string) ($consequence['label'] ?? ''), 'items' => array_values(array_filter($items, 'strlen')), 'extras' => array_values(array_filter($extras, 'strlen'))];

    }

    protected function list_step_notes (?Model_Resource $resource, array $step) : array {

        $fields = $resource ? $resource->get_fields() : [];
        $label  = fn ($path) => $this->label_field($fields, strtok((string) $path, '.'));
        $names  = fn ($list) => implode(', ', array_map($label, $list));
        $notes  = [];

        if (!empty($step['refusal'])) $notes[] = ['type' => 'error', 'message' => is_array($step['refusal']) ? (string) ($step['refusal']['message'] ?? '') : (string) $step['refusal']];

        foreach ((array) ($step['conflicts'] ?? []) as $conflict) $notes[] = ['type' => 'warning', 'message' => $label($conflict['field']) . ': ' . (!empty($conflict['masking']) ? 'this may be how a secret is masked, not someone\'s change.' : 'it no longer holds what this started from.')];

        foreach (['problems' => 'error', 'locked' => 'warning'] as $key => $type) foreach ((array) ($step[$key] ?? []) as $field => $message) $notes[] = ['type' => $type, 'message' => (is_string($field) ? $label($field) . ': ' : '') . (is_scalar($message) ? $message : static::show($message))];

        if (!empty($step['withheld']))           $notes[] = ['type' => 'info', 'message' => 'Not shown to you: ' . $names((array) $step['withheld']) . '.'];
        if (!empty($step['skipped']))            $notes[] = ['type' => 'info', 'message' => 'Already live: ' . $names((array) $step['skipped']) . '.'];
        if (!empty($step['removed']))            $notes[] = ['type' => 'info', 'message' => 'Filtering removed what its author may not keep: ' . $names(array_keys((array) $step['removed'])) . '.'];
        if (!empty($step['preview']['removed'])) $notes[] = ['type' => 'info', 'message' => 'Applying as you would remove what you may not keep: ' . $names(array_keys((array) $step['preview']['removed'])) . '.'];
        if (!empty($step['error']))              $notes[] = ['type' => 'error', 'message' => (string) $step['error']];

        foreach ((array) ($step['flags'] ?? []) as $flag) $notes[] = ['type' => 'warning', 'message' => ucfirst(str_replace('_', ' ', (string) $flag)) . '.'];

        if (($step['checked'] ?? null) === false && empty($step['reached']) && empty($step['done'])) $notes[] = ['type' => 'info', 'message' => 'Not checked for you: a reviewer sees each change\'s preview.'];

        return $notes;

    }

    protected function describe_undo_row (array $row) : array {

        $resource = Resources::inst()->get_resource($row['resource']);
        $model    = $resource?->find($row['record_id']);
        $title    = ($model && !Resources::inst()->refuse_read($resource, $model)) ? ($resource->summarise($model)['title'] ?: "#{$row['record_id']}") : "#{$row['record_id']}";
        $undoes   = match ($row['action']) { 'update', 'restore' => 'put its fields back', 'trash' => 'restore it', 'untrash' => 'move it back to the Trash', 'create' => 'move it to the Trash', default => 'reverse ' . lcfirst($this->label_action($resource, (string) $row['action'])) };

        return [
            'number' => is_null($row['step'] ?? null) ? null : $row['step'] + 1,
            'what'   => ($resource?->get_label() ?? $row['resource']) . " \u{201C}{$title}\u{201D}: {$undoes}",
            'note'   => !empty($row['passed_over']) ? 'nothing to reverse' : (empty($row['can_undo']) ? 'cannot be undone' : (!empty($row['reached']) ? 'checked once the ones after it are undone' : '')),
        ];

    }

    public static function show ($value) : string {

        return is_scalar($value) ? (string) $value : (string) wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    }

    public static function show_cell ($value) : string {

        return ((string) $value === '') ? '∅' : (string) $value;

    }

}
