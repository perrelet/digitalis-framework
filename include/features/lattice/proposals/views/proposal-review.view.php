<?php

namespace Digitalis;

class Proposal_Review extends View {

    protected static $template      = 'proposal-review';
    protected static $template_path = __DIR__ . '/../templates/';

    protected static $defaults = [
        'wp_post'  => null,
        'proposal' => null,
    ];

    public function params (&$p) {

        $p['proposal'] ??= $p['wp_post'] ? Proposal::find($p['wp_post']->ID) : null;

        if ($proposal = $p['proposal']) {

            $resource = $proposal->get_resource();
            $original = $proposal->get_original();

            $p['author']   = $proposal->get_author()?->get_display_name();
            $p['created']  = $proposal->get_created() ? human_time_diff($proposal->get_created()) . ' ago' : '';
            $p['original'] = $original;
            $p['stale']    = $original && $proposal->is_stale();
            $p['removed']  = $original ? Proposal::describe_markup($proposal->get_markup_removed(), $resource) : '';
            $p['held']     = $original ? array_map(fn ($label, $why) => "{$label}: {$why}", $proposal->label_fields(array_keys($proposal->get_held_back())), $proposal->get_held_back()) : [];
            $p['lines']    = $original ? $proposal->present() : [];
            $p['preview']  = array_key_exists('content', $proposal->get_values()) ? get_preview_post_link($proposal->get_id()) : '';
            $p['reviewer'] = current_user_can('approve_proposals');

            $this->prepare_decision($p);

        }

        parent::params($p);

    }

    protected function prepare_decision (&$p) : void {

        $proposal = $p['proposal'];

        $p['decide']       = $p['reviewer'] && $p['original'] && $proposal->is_submitted();
        $p['refusal']      = '';
        $p['consequences'] = [];
        $p['approve_url']  = '';
        $p['reject_url']   = Proposals_Admin::get_action_url('reject', $proposal->get_id());

        if (!$p['decide'] || $p['stale']) return;

        if ($reason = $proposal->refuse_approval()) {

            $p['refusal'] = $reason;
            return;

        }

        $review = $proposal->review();

        if (is_wp_error($review)) {

            $p['refusal'] = $review->get_error_message();
            return;

        }

        $p['consequences'] = $review['consequences'];
        $p['approve_url']  = Proposals_Admin::get_action_url('approve', $proposal->get_id(), ['confirm' => $review['fingerprint']]);

    }

    public function condition () {

        return (bool) $this['proposal'];

    }

}
