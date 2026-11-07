<?php

declare(strict_types=1);

namespace Lattice;

// An Iterator's background run: one batch per step, keyed by the record's option name (`iterator_{key}`) so one run is open per
// record. The record is the run; the row is its driver and what Tools -> Jobs shows. A step that throws spends a try
// and backs off; three leave the record where it stood, with the error in its errors (the feature's ended listener writes it).
final class Iterator_Job extends Job {

    protected static $type       = 'lattice/iterator';
    protected static $tries      = 3;
    protected static $expires_in = null;

    public function handle (string $iterator) {

        if (!class_exists($iterator) || !is_subclass_of($iterator, Iterator::class)) $this->cancel("{$iterator} is not an iterator.");

        $cursor = $this->get_cursor() ?? [];

        // The step after the batch that ended the run: the record says complete or halted, and the job is done.
        if (isset($cursor['end'])) return;

        // The first step may resume a halted run (Run in background allows one); a later step finding the run ended (its own batch
        // ended it but the cursor was lost with the step, or a stale re-run) records the end instead of running on.
        $tick = $iterator::get_instance()->run_batch(resume: !$cursor);

        // A UI chain holds the lock (one that started between the dispatch and this step), or the source answered nothing with items
        // remaining: wait, keep the cursor, spend no try.
        if ($tick['refused'] || $tick['stalled']) $this->snooze((int) static::MIN_SNOOZE);

        if (is_int($tick['total'])) $this->set_total($tick['total']);

        $ended = in_array($tick['status'], ['complete', 'halted'], true) && !$tick['retry'];

        $this->next(array_filter([
            'index' => $tick['index'],
            'tick'  => (int) ($cursor['tick'] ?? 0) + 1,
            'from'  => $cursor['from'] ?? $tick['from'], // the index the run started from, read at the first step: the ETA paces from it
            'end'   => $ended ? $tick['status'] : null,
        ], fn ($value) => !is_null($value)), (int) $tick['count']);

    }

    // One run per record. A dispatch that lands on an open row marks it to run again when it ends, which a run never wants: the
    // mark is cleared and the open job answered instead of a new one. Answers `['job' => Iterator_Job]` (unsaved when held by the
    // dispatching step), or `['job' => null, 'open' => ?Iterator_Job]`.
    public static function open_for (Iterator $iterator) : array {

        $key = $iterator->get_option_key();
        $job = static::dispatch(['iterator' => get_class($iterator)], key: $key, now: true);

        if (!$job)                                   return ['job' => null, 'open' => static::find($key)];
        if (!$job->get_id() || !$job->get_row()['again']) return ['job' => $job];

        $job->update('again = 0', [], 'again = 1', []);

        return ['job' => null, 'open' => $job];

    }

}
