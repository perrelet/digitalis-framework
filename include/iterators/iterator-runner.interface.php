<?php

declare(strict_types=1);

namespace Lattice;

// The background driver of an Iterator's run, answered by the `lattice.iterator.runner` filter; the Jobs feature registers one.
// The Iterator itself is a foreground engine driven by the page, a runner or the shell through `run_batch()`: without a runner,
// Run in background and a scheduled start refuse with one line.
interface Iterator_Runner {

    // Hands the run to the background from its index: `['id' => ?int, 'queued' => bool, 'held' => bool]`, or
    // `['refused' => true, 'message' => string]` when nothing was dispatched.
    public function dispatch (Iterator $iterator) : array;

    // Cancels the open run: `cancelled` for a run that had not started, `requested` for one honoured before its next step,
    // `null` when nothing is open.
    public function cancel (Iterator $iterator) : ?string;

    // The open run, or `null`: `id`, `state` (pending, running, stopping), `running` (leased now), `stopping` (a cancel is
    // requested), `since` (when it was dispatched), `from` (the index it started from), `done`, `total`, `error`, `url`.
    public function status (Iterator $iterator) : ?array;

}
