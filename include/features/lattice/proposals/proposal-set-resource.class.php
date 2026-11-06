<?php

namespace Digitalis;

use WP_Error;

// The review workflow on a set, registered under the feature's own name, so the feature's hooks reach this one.
class Proposal_Set_Resource extends Change_Set_Resource {

    public function get_actions () : array {

        return parent::get_actions() + [
            'submit_proposal'   => ['label' => 'Submit it for review', 'capability' => 'propose_changes', 'when' => fn ($set) => $this->refuse_submitting($set), 'validate' => fn ($set, array $input) => $this->check_ready($set)],
            'rebase_proposal'   => ['label' => 'Start from what is live', 'when' => fn ($set) => $this->refuse_rebasing_proposal($set)],
            'approve_proposal'  => ['label' => 'Approve and apply it', 'capability' => 'approve_proposals', 'writes_through' => true, 'input' => ['seen' => '?json'], 'when' => fn ($set) => $this->refuse_reviewing($set)],
            'reject_proposal'   => ['label' => 'Send it back', 'capability' => 'approve_proposals', 'input' => ['note' => '?string'], 'when' => fn ($set) => $this->refuse_reviewing($set)],
            'withdraw_proposal' => ['label' => 'Withdraw it', 'when' => fn ($set) => $this->refuse_withdrawing($set)],
        ];

    }

    public function get_consequences (Resource_Change $change) : array {

        if ($change->operation !== 'rebase_proposal') return parent::get_consequences($change);

        $set = $change->before;

        return [...$this->prepare_rebase($set)['moved'], ...(($set->has_applied_step() || !$set->get_submitted_at()) ? [] : [['returns' => 'It goes back to its author as a draft, and leaves the review queue.']])];

    }

    public function get_edit_url ($set) : string {

        return add_query_arg('set', $set->get_id(), Proposal::get_queue_url());

    }

    public function get_views () : array {

        return ['review' => 'review_set'];

    }

    public function review_set ($set) : array {

        // Read before the steps, so a step list that moves meanwhile is stale and not taken as seen.
        $version  = $set->get_version();
        $reviewer = current_user_can('approve_proposals');
        $read     = array_combine(array_keys($set->get_steps()), $this->read_steps($set));

        $steps = match (true) {
            $set->is_closed() => $read,
            $reviewer         => $this->preview_steps($set, $read),
            default           => $this->check_steps($set, $read),
        };

        $records = [];
        $waiting = [];

        foreach ($steps as $position => $step) {

            $records[$step['resource'] . ':' . ($step['id'] ?? "new-{$position}")][] = $position;

            if (!empty($step['reached'])) $steps[$position]['after'] = $waiting;

            if (empty($step['done'])) $waiting[] = $position;

        }

        return [
            'as'           => $reviewer ? 'reviewer' : 'author',
            'version'      => $version,
            'state'        => $set->get_state(),
            'purpose'      => $set->get_purpose(),
            'author_id'    => $set->get_author_id(),
            'submitted_at' => $set->get_submitted_at(),
            'undone'       => Journal::inst()->has_undone_rows($set->get_key()),
            'unsettled'    => (bool) $this->refuse_unsettled($set),
            'closed'       => $set->is_closed(),
            'note'         => $set->get_note(),
            'reason'       => $set->get_reason(),
            'steps'        => $steps,
            'records'      => $records,
        ];

    }

    protected function preview_steps ($set, array $read) : array {

        $steps = [];

        foreach (Resources::inst()->preview_set($set) as $position => $preview) $steps[$position] = array_filter(['conflicts' => $this->label_conflicts($preview['refusal']['data']['conflicts'] ?? [])]) + $preview + $read[$position];

        return $steps;

    }

    protected function check_steps ($set, array $read) : array {

        $stored = $set->get_steps();
        $plan   = Resources::inst()->plan_steps($stored);
        $steps  = [];

        foreach ($read as $position => $step) {

            $held = $stored[$position];

            // What it would find depends on the steps before it, not on what is live now.
            $check = (($held['op'] === 'update') && !$plan[$position]['done'] && !$plan[$position]['reached']) ? $this->check_update_step($held) : ['checked' => false];

            $steps[$position] = $step + ['flags' => Resources::inst()->get_step_flags($held)] + $plan[$position] + $check;

        }

        return $steps;

    }

    protected function check_update_step (array $step) : array {

        $compared = Resources::inst()->compare_expected($step['resource'], $step['id'], $step['values'], $step['expected']);
        $problems = Resources::inst()->check_update($step['resource'], $step['id'], $step['values']);

        foreach ([$compared, $problems] as $answer) if (is_wp_error($answer)) return ['checked' => false, 'refusal' => $answer->get_error_message()];

        return ['checked' => true, 'conflicts' => $this->label_conflicts($compared['conflicts'])] + array_intersect_key($compared, ['applied' => 0, 'skipped' => 0, 'withheld' => 0]) + $problems;

    }

    // An account shown a repeater as one mask and another shown its cells disagree about a value neither moved (D64).
    protected function label_conflicts (array $conflicts) : array {

        return array_map(function ($conflict) {

            [$start, $live] = [$conflict['start'] ?? null, $conflict['live'] ?? null];

            return $conflict + (((Resource_Field::is_mask($start) && is_array($live)) || (is_array($start) && Resource_Field::is_mask($live))) ? ['masking' => true] : []);

        }, $conflicts);

    }

    public function query_queue () : array {

        global $wpdb;

        $mine = $wpdb->prepare('author_id = %d', get_current_user_id());
        $ids  = $wpdb->get_col('SELECT set_id FROM ' . Change_Set::table() . ' WHERE ' . $this->open_where(current_user_can('approve_proposals') ? "(submitted_at IS NOT NULL OR {$mine})" : $mine) . ' ORDER BY updated_at DESC, set_id DESC');

        return Change_Set::get_instances(array_map('intval', (array) $ids));

    }

    public function count_awaiting () : int {

        return $this->count_open('submitted_at IS NOT NULL');

    }

    public function count_sent_back_for (int $author_id) : int {

        global $wpdb;

        return $this->count_open($wpdb->prepare('author_id = %d AND note IS NOT NULL AND submitted_at IS NULL', $author_id));

    }

    protected function count_open (string $where) : int {

        global $wpdb;

        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Change_Set::table() . ' WHERE ' . $this->open_where($where));

    }

    protected function open_where (string $where) : string {

        return "state NOT IN ('" . implode("', '", Change_Set::CLOSED) . "') AND {$where}";

    }

    // Submitting

    protected function refuse_submitting ($set) : string {

        if ($set->get_author_id() !== get_current_user_id()) return 'Only its author submits it.';

        if (($set->get_state() !== Change_Set::DRAFT) && (($set->get_state() !== Change_Set::APPLIED_IN_PART) || $set->get_submitted_at())) return 'It cannot be submitted in this state.';

        if (Journal::inst()->has_undone_rows($set->get_key())) return 'Part of it has been undone, so what it says is no longer live: withdraw it.';

        return $this->refuse_unsettled($set);

    }

    protected function check_ready ($set) : array {

        return array_values(array_filter([
            (trim($set->get_purpose()) === '') ? 'Say what it is for before submitting it.' : '',
            $set->get_steps() ? '' : 'It holds no steps.',
        ]));

    }

    public function act_submit_proposal ($set, array $input, $change) {

        if ($problems = $this->check_ready($set)) return $this->invalid($problems);

        if (!$set->submit()) return new WP_Error('not_saved', 'It could not be submitted, and nothing was changed.');

        $this->announce('submitted', $set);

        return $set;

    }

    // Reviewing

    protected function refuse_reviewing ($set) : string {

        if (!$set->get_submitted_at() || !in_array($set->get_state(), [Change_Set::SUBMITTED, Change_Set::AWAITING_REVIEW, Change_Set::APPLIED_IN_PART], true)) return 'It is not waiting for a review.';

        return $this->refuse_unsettled($set);

    }

    public function act_approve_proposal ($set, array $input, string $via) {

        if (!empty($input['dry_run'])) return ['dry_run' => true, 'version' => $set->get_version(), 'steps' => Resources::inst()->preview_set($set)];

        $result = $this->walk_set($set, $input['seen'] ?? null, (string) ($input['why'] ?? ''), $via, 'approve_proposal');

        // A delete leaves it broken though every step landed.
        if (!is_wp_error($result) && ($result['outcome'] === 'applied') && ($result['state'] === Change_Set::APPLIED)) $this->announce('approved', $set, $result);

        return $result;

    }

    public function act_reject_proposal ($set, array $input, $change) {

        if (!$set->send_back($input['note'] ?? null)) return new WP_Error('not_saved', 'It could not be sent back, and nothing was changed.');

        $this->announce('rejected', $set);

        return $set;

    }

    // Its author's ways back

    protected function refuse_rebasing_proposal ($set) : string {

        return (($set->get_state() === Change_Set::SUBMITTED) && ($set->get_author_id() === get_current_user_id())) ? $this->refuse_unsettled($set) : $this->refuse_rebasing($set);

    }

    public function act_rebase_proposal ($set, array $input, $change) {

        return $this->act_rebase_set($set, $input, $change);

    }

    public function act_withdraw_proposal ($set, array $input, $change) {

        return $this->act_withdraw_set($set, $input, $change);

    }

}
