<?php

declare(strict_types=1);

namespace Lattice;

class Proposals_Queue extends View {

    protected static $template      = 'proposals-queue';
    protected static $template_path = __DIR__ . '/../templates/';

    protected static $defaults = [
        'reviewer' => null,
        'rows'     => [],
    ];

    const STATUSES = [
        'attention' => 'Needs a person',
        'in-part'   => 'Part applied',
        'awaiting'  => 'Awaiting review',
        'sent-back' => 'Sent back',
        'writing'   => 'Being written',
    ];

    public function params (&$p) {

        $p['reviewer'] ??= current_user_can('approve_proposals');

        $proposals = Proposal::query_open($p['reviewer'] ? [] : ['author' => get_current_user_id()]);

        $sets = Resources::inst()->get_resource('change_set')?->query_queue() ?? [];

        $p['rows'] = [...array_map(fn ($proposal) => $this->describe_proposal($proposal, $p['reviewer']), $proposals), ...array_map(fn ($set) => $this->describe_set($set, $p['reviewer']), $sets)];

        usort($p['rows'], fn ($a, $b) => array_search($a['status'], array_keys(static::STATUSES), true) <=> array_search($b['status'], array_keys(static::STATUSES), true));

        parent::params($p);

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
            'gone'       => !$original,
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

    protected function describe_set (Change_Set $set, bool $reviewer) : array {

        $status = match (true) {
            in_array($set->get_state(), [Change_Set::BROKEN, Change_Set::UNKNOWN], true) => 'attention',
            (bool) $set->get_submitted_at()                                              => 'awaiting',
            !is_null($set->get_note())                                                   => 'sent-back',
            $set->get_state() === Change_Set::APPLIED_IN_PART                            => 'in-part',
            default                                                                      => 'writing',
        };

        return [
            'title'      => wp_html_excerpt($set->get_purpose(), 80, '…') ?: "Set #{$set->get_id()}",
            'review_url' => Resources::inst()->get_resource('change_set')->get_edit_url($set),
            'live_url'   => null,
            'gone'       => false,
            'type'       => 'Change set',
            'author'     => get_userdata($set->get_author_id())->display_name ?? null,
            'status'     => $status,
            'stale'      => false,
            'changes'    => array_values(array_map(fn ($step) => trim("{$step['op']} {$step['resource']} " . ($step['id'] ?? '')), $set->get_steps())),
            'activity'   => human_time_diff((int) strtotime($set->get_updated_at() . ' UTC')) . ' ago',
            'open_label' => ($reviewer && ($status === 'awaiting')) ? 'Review' : 'Open',
            'reject_url' => '',
            'delete_url' => '',
        ];

    }

}
