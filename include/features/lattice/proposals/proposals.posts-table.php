<?php

namespace Digitalis;

class Proposals_Table extends Posts_Table {

    protected $id = 'lattice-proposals';

    public function get_hooks () {

        return [
            'display_post_states' => 'add_states',
        ];

    }

    public function row_actions (&$actions, ?Post $post = null) {

        if (!$post || ($post->get_status() !== 'publish') || !($model = Proposal::find_model($post->get_id()))) return;

        $can_propose = current_user_can('propose_changes');
        $can_review  = current_user_can('approve_proposals');

        if (!$can_propose && !$can_review) return;

        if (!$open = $model->get_open_proposal()) {

            if ($can_propose) $actions['lattice_propose'] = '<a href="' . esc_url(Proposals_Admin::get_action_url('propose', $post->get_id())) . '">Propose changes</a>';

            return;

        }

        $actions['lattice_proposal'] = current_user_can('edit_post', $open->get_id())
            ? '<a href="' . esc_url(get_edit_post_link($open->get_id())) . '">' . esc_html(!$can_review ? 'View proposal' : ($open->is_submitted() ? 'Review proposed change' : 'View proposed change')) . '</a>'
            : '<span>Someone else is proposing a change</span>';

    }

    // data-parent lets proposals.js nest the row.
    public function add_states ($states, $wp_post) {

        if (!$model = Proposal::find_model($wp_post->ID)) return $states;

        if ($proposal = $model->get_proposal()) {

            $original = $proposal->get_original();
            $author   = $proposal->get_author();
            $tip      = ($original ? "Proposed change to: {$original->get_title()}" : 'Proposed change') . ($author ? " · by {$author->get_display_name()}" : '');

            $states['lattice_proposal'] = '<span class="lattice-proposal-flag is-proposal" title="' . esc_attr($tip) . '"' . ($original ? ' data-parent="' . $original->get_id() . '"' : '') . '>&#9998; Proposal</span>';

        } elseif (($model->get_status() === 'publish') && ($open = $model->get_open_proposal())) {

            $states['lattice_has_proposal'] = '<span class="lattice-proposal-flag has-proposal">' . ($open->is_submitted() ? '&#9719; Review pending' : '&#9998; Change drafting') . '</span>';

        }

        return $states;

    }

}
