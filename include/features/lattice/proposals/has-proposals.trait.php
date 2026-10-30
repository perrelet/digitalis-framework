<?php

namespace Digitalis;

trait Has_Proposals {

    public static function get_proposal_meta_keys () : array {

        return array_values(Proposal::META);

    }

    public static function make_proposal_meta (Post $original, User $author) : array {

        return [
            Proposal::META['FOR']          => $original->get_id(),
            Proposal::META['AUTHOR']       => $author->get_id(),
            Proposal::META['CREATED']      => time(),
            Proposal::META['BASE_VERSION'] => $original->get_version(),
        ];

    }

    // The draft

    public function is_proposal () : bool {

        return (bool) $this->get_proposed_for_id();

    }

    public function get_proposal () : ?Proposal {

        return $this->is_proposal() ? new Proposal($this) : null;

    }

    public function get_proposed_for_id () : int {

        return (int) $this->get_meta(Proposal::META['FOR']);

    }

    public function get_proposal_author_id () : int {

        return (int) $this->get_meta(Proposal::META['AUTHOR']);

    }

    public function get_proposal_created () : int {

        return (int) $this->get_meta(Proposal::META['CREATED']);

    }

    // -1 when unknown, which reads as stale.
    public function get_proposal_base_version () : int {

        $version = $this->get_meta(Proposal::META['BASE_VERSION']);

        return is_numeric($version) ? (int) $version : -1;

    }

    // Null unless sent back; a reviewer may send it back without a note.
    public function get_proposal_note () : ?string {

        return metadata_exists('post', $this->get_id(), Proposal::META['NOTE']) ? (string) $this->get_meta(Proposal::META['NOTE']) : null;

    }

    public function set_proposal_note (string $note) : void {

        $this->update_meta(Proposal::META['NOTE'], $note);

    }

    public function clear_proposal_note () : void {

        $this->delete_meta(Proposal::META['NOTE']);

    }

    public function get_proposal_saved_by () : int {

        return (int) $this->get_meta(Proposal::META['SAVED_BY']);

    }

    public function set_proposal_saved_by (int $user_id) : void {

        $this->update_meta(Proposal::META['SAVED_BY'], $user_id);

    }

    // The live record

    public function get_open_proposal_id () : int {

        return (int) $this->get_meta(Proposal::META['HAS_PROPOSAL']);

    }

    public function get_open_proposal () : ?Proposal {

        $draft_id = $this->get_open_proposal_id();

        return ($draft_id && !in_array(get_post_status($draft_id), [false, 'trash'], true)) ? static::get_instance($draft_id)?->get_proposal() : null;

    }

    public function set_open_proposal_id (int $draft_id) : void {

        $this->update_meta(Proposal::META['HAS_PROPOSAL'], $draft_id);

    }

    public function release_open_proposal (int $draft_id) : void {

        if ($this->get_open_proposal_id() === $draft_id) $this->delete_meta(Proposal::META['HAS_PROPOSAL']);

    }

}
