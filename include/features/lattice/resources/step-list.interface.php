<?php

namespace Digitalis;

// What the service needs of a held list of its own calls, so it never names the feature that keeps them. An empty list applies as nothing: refusing one is the keeper's.
interface Step_List {

    public function get_key () : int;

    // Each step's why: what the list is for.
    public function get_why () : string;

    public function get_proposer_id () : int;

    /** @return array[] by position, as held and always current: op (create, update, act, trash, untrash), resource, id, values, expected, action, input, wait, marker. Secrets raw: this is what is written, so masking belongs to whoever shows them. */
    public function get_steps () : array;

    // $detail holds any of journal_id, error, record_id. A new marker clears the error and journal_id it does not bring; record_id stays until it is replaced. False only when it could not be recorded: writing what is already there is not a failure.
    public function mark (int $position, string $marker, array $detail = []) : bool;

    // False whenever it cannot say: a query that failed is no lock held.
    public function holds_lock () : bool;

}
