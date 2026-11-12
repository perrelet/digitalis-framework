<?php

declare(strict_types=1);

namespace Lattice;

// What Proposals asks of a moderated model: Has_Proposals and Has_Version are the implementation. Runtime detection stays
// the trait (Proposal::is_moderated()), so a model without `implements Takes_Proposals` is still moderated; strict throws for it.
interface Takes_Proposals {

    public static function make_proposal_meta (Post $original, User $author) : array;

    public function get_version () : int;

    public function is_proposal () : bool;

    public function get_proposal () : ?Proposal;

    public function get_proposed_for_id () : int;

    public function get_proposal_author_id () : int;

    public function get_proposal_created () : int;

    public function get_proposal_base_version () : int;

    public function get_proposal_note () : ?string;

    public function set_proposal_note (string $note) : void;

    public function clear_proposal_note () : void;

    public function get_proposal_saved_by () : int;

    public function set_proposal_saved_by (int $user_id) : void;

    public function get_open_proposal_id () : int;

    public function get_open_proposal () : ?Proposal;

    public function set_open_proposal_id (int $draft_id) : void;

    public function release_open_proposal (int $draft_id) : void;

}
