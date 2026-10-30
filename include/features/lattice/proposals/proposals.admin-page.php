<?php

namespace Digitalis;

class Proposals_Page extends Admin_Page {

    protected $slug       = 'lattice-proposals';
    protected $title      = 'Proposed Changes';
    protected $menu_title = 'Proposed Changes';
    protected $capability = 'edit_posts';
    protected $icon       = 'dashicons-feedback';
    protected $position   = 26;

    public function admin_menu () {

        $count = current_user_can('approve_proposals') ? Proposal::count_awaiting_review() : Proposal::count_sent_back_for(get_current_user_id());

        if ($count) $this->menu_title = "{$this->title} <span class=\"awaiting-mod\"><span class=\"pending-count\">{$count}</span></span>";

        parent::admin_menu();

    }

    public function callback () {

        echo new Proposals_Queue;

    }

}
