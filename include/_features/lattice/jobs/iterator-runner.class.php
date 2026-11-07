<?php

declare(strict_types=1);

namespace Lattice;

// The Jobs feature's answer to `lattice.iterator.runner`: an Iterator's background run is an Iterator_Job.
final class Iterator_Job_Runner extends Service implements Iterator_Runner {

    const STOPPED = 'Stopped from the page.';

    public function dispatch (Iterator $iterator) : array {

        $opened = Iterator_Job::open_for($iterator);

        if ($job = $opened['job']) return ['id' => $job->get_id(), 'queued' => true, 'held' => is_null($job->get_id())];

        $open = $opened['open'] ?? null;

        return ['refused' => true, 'message' => match (true) {
            $open && !is_null($open->get_row()['cancel_why']) => 'The previous background run is still stopping; try again in a moment.',
            (bool) $open                                      => "A background run is already open (job #{$open->get_id()}).",
            default                                           => 'The background run could not be queued: the jobs table is not current, or the worker refused. See Tools -> Jobs.',
        }];

    }

    public function cancel (Iterator $iterator) : ?string {

        $job     = Iterator_Job::find($iterator->get_option_key());
        $outcome = $job instanceof Job ? $job->cancel(self::STOPPED) : null;

        return in_array($outcome, ['cancelled', 'requested'], true) ? $outcome : null; // a bare state (the run ended under the click, or ten contested tries) is no open run

    }

    public function status (Iterator $iterator) : ?array {

        if (!$job = Iterator_Job::find($iterator->get_option_key())) return null;
        if (!$row = $job->get_row())                          return null;

        $cursor   = is_null($row['position']) ? [] : (json_decode((string) $row['position'], true) ?: []);
        $stopping = !is_null($row['cancel_why']);
        $running  = (bool) $row['leased'];

        return [
            'id'       => (int) $row['id'],
            'state'    => $stopping ? 'stopping' : ($running ? 'running' : 'pending'),
            'running'  => $running,
            'stopping' => $stopping,
            'since'    => (int) strtotime($row['created_at'] . ' UTC'),
            'from'     => is_int($cursor['from'] ?? null) ? $cursor['from'] : null, // null until the first step has recorded it
            'done'     => (int) $row['done'],
            'total'    => is_null($row['total']) ? null : (int) $row['total'],
            'error'    => $row['error'] ?? null,
            'url'      => admin_url('tools.php?page=' . Jobs_Page::SLUG),
        ];

    }

}
