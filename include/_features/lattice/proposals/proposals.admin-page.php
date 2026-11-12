<?php

declare(strict_types=1);

namespace Lattice;

class Proposals_Page extends Admin_Page {

    protected $slug       = 'lattice-proposals';
    protected $title      = 'Proposed Changes';
    protected $menu_title = 'Proposed Changes';
    protected $capability = 'edit_posts';
    protected $icon       = 'dashicons-feedback';
    protected $position   = 26;

    public function admin_menu () {

        $sets  = Resources::inst()->get_resource('change_set');
        $count = current_user_can('approve_proposals') ? Proposal::count_awaiting_review() + (int) $sets?->count_awaiting() : Proposal::count_sent_back_for(get_current_user_id()) + (int) $sets?->count_sent_back_for(get_current_user_id());

        if ($count) $this->menu_title = "{$this->title} <span class=\"awaiting-mod\"><span class=\"pending-count\">{$count}</span></span>";

        parent::admin_menu();

    }

    public function callback () {

        $set_id = absint($_GET['set'] ?? 0);

        echo $set_id ? new Proposal_Set_Review(['set_id' => $set_id]) : new Proposals_Queue;

    }

}
