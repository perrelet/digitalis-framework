<?php

namespace Digitalis;

use WP_Error;

// The review workflow on a set, registered under the feature's own name, so the feature's hooks reach this one.
class Proposal_Set_Resource extends Change_Set_Resource {

    public function get_actions () : array {

        return parent::get_actions() + [
            'submit_proposal'   => ['label' => 'Submit it for review', 'capability' => 'propose_changes', 'when' => fn ($set) => $this->refuse_submitting($set), 'validate' => fn ($set, array $input) => $this->check_ready($set)],
            'rebase_proposal'   => ['label' => 'Start from what is live', 'when' => fn ($set) => $this->refuse_rebasing_proposal($set)],
            'reject_proposal'   => ['label' => 'Send it back', 'capability' => 'approve_proposals', 'input' => ['note' => '?string'], 'when' => fn ($set) => $this->refuse_reviewing($set)],
            'withdraw_proposal' => ['label' => 'Withdraw it', 'when' => fn ($set) => $this->refuse_withdrawing($set)],
        ];

    }

    public function get_consequences (Resource_Change $change) : array {

        if ($change->operation !== 'rebase_proposal') return parent::get_consequences($change);

        $set = $change->before;

        return [...$this->prepare_rebase($set)['moved'], ...(($set->has_applied_step() || !$set->get_submitted_at()) ? [] : [['returns' => 'It goes back to its author as a draft, and leaves the review queue.']])];

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
