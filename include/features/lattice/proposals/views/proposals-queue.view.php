<?php

namespace Digitalis;

class Proposals_Queue extends View {

    protected static $template      = 'proposals-queue';
    protected static $template_path = __DIR__ . '/../templates/';

    protected static $defaults = [
        'reviewer' => null,
        'rows'     => [],
    ];

    const STATUSES = [
        'awaiting'  => 'Awaiting review',
        'sent-back' => 'Sent back',
        'writing'   => 'Being written',
    ];

    public function params (&$p) {

        $p['reviewer'] ??= current_user_can('approve_proposals');

        $proposals = Proposal::query_open($p['reviewer'] ? [] : ['author' => get_current_user_id()]);

        usort($proposals, fn ($a, $b) => $this->rank_proposal($a) <=> $this->rank_proposal($b));

        $p['rows'] = array_map(fn ($proposal) => $this->describe_proposal($proposal, $p['reviewer']), $proposals);

        parent::params($p);

    }

    protected function rank_proposal (Proposal $proposal) : int {

        return array_search($this->get_status($proposal), array_keys(static::STATUSES), true);

    }

    protected function get_status (Proposal $proposal) : string {

        return $proposal->is_submitted() ? 'awaiting' : ($proposal->was_sent_back() ? 'sent-back' : 'writing');

    }

    protected function describe_proposal (Proposal $proposal, bool $reviewer) : array {

        $draft    = $proposal->get_draft();
        $original = $proposal->get_original();
        $id       = $proposal->get_id();
        $status   = $this->get_status($proposal);

        return [
            'title'      => ($original ? $original->get_title() : $draft->get_title()) ?: '(untitled)',
            'review_url' => Proposals_Admin::get_review_url($id),
            'live_url'   => $original?->get_edit_url(),
            'type'       => get_post_type_object($draft->get_post_type())?->labels->singular_name ?? $draft->get_post_type(),
            'author'     => $proposal->get_author()?->get_display_name(),
            'status'     => $status,
            'stale'      => $original && $proposal->is_stale(),
            'changes'    => $original ? $proposal->get_changed_labels() : null,
            'activity'   => human_time_diff($draft->get_post_timestamp('modified')) . ' ago',
            'open_label' => ($reviewer && ($status === 'awaiting')) ? 'Review' : 'Open',
            'reject_url' => ($reviewer && ($status === 'awaiting')) ? Proposals_Admin::get_action_url('reject', $id, ['return' => 'queue']) : '',
            'delete_url' => current_user_can('delete_post', $id) ? Proposals_Admin::get_action_url('delete', $id) : '',
        ];

    }

}
