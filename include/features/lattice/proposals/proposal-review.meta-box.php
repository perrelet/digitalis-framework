<?php

namespace Digitalis;

class Proposal_Review_Box extends Meta_Box {

    const ID = 'lattice-proposal-review';

    protected $id       = self::ID;
    protected $title    = 'Proposed Changes';
    protected $context  = 'normal';
    protected $priority = 'high';
    protected $view     = Proposal_Review::class;

    public function get_screen () {

        return Proposal::get_post_types();

    }

    public function condition () {

        return ($wp_post = get_post()) && Proposal::find_model($wp_post->ID)?->is_proposal();

    }

}
