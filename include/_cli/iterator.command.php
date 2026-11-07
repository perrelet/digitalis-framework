<?php

declare(strict_types=1);

namespace Lattice;

use WP_CLI;

// The shell as the Iterator's third driver, on the page's and the job's surface: `background()`, `run_batch()`, `stop_cron()`, `reset()`.
class Iterator_Command extends Command {

    protected $name        = 'lattice iterator';
    protected $subcommands = [
        'list'   => ['Every loaded iterator: key, class, title, status, progress and the background run.', '[--fields=<fields>] [--format=<format>]', 'list_iterators'],
        'status' => ['One iterator: the record, the lock, the open job and the last errors.', '<key>'],
        'start'  => ['Hand the run to the background from where it stands. --run drives the job now; --foreground drives the run here, under the lock.', '<key> [--run] [--foreground]'],
        'batch'  => ['One bare batch.', '<key>'],
        'stop'   => ['Cancel the background run.', '<key>'],
        'reset'  => ['Reset the record. Refused while a run is open.', '<key>'],
    ];

    const FIELDS = ['key', 'class', 'title', 'status', 'progress', 'background'];

    public function list_iterators ($args, $assoc) {

        $rows = [];

        foreach (Iterator::all() as $key => $iterator) {

            $job    = $iterator->open_job();
            $rows[] = [
                'key'        => $key,
                'class'      => get_class($iterator),
                'title'      => $iterator->get_title(),
                'status'     => $iterator->get_status(),
                'progress'   => $this->progress($iterator),
                'background' => $job ? $job['state'] . (is_int($job['id']) ? " (job #{$job['id']})" : '') : '',
            ];

        }

        if (!$rows) { WP_CLI::warning('No iterator is loaded.'); return; }

        $fields = isset($assoc['fields']) ? array_values(array_intersect(explode(',', (string) $assoc['fields']), self::FIELDS)) : self::FIELDS;

        $this->table($rows, $fields ?: self::FIELDS, (string) ($assoc['format'] ?? 'table'));

    }

    public function status ($args, $assoc) {

        $iterator = $this->find((string) $args[0]);
        $store    = $iterator->get_store();
        $job      = $iterator->open_job();

        $this->answer([
            'key'         => $iterator->get_key(),
            'class'       => get_class($iterator),
            'status'      => $iterator->get_status(),
            'index'       => $iterator->get_index(),
            'total'       => $iterator->has_total() ? $iterator->get_total() : $iterator->refresh_total(), // counted once per run, as the page does
            'stalled'     => !empty($store['stalled']),
            'failed'      => count($store['failed'] ?? []),
            'retry'       => count($store['retry'] ?? []),
            'started_at'  => $iterator->get_started_at(),
            'updated_at'  => $iterator->get_updated_at(),
            'elapsed'     => (int) floor($iterator->get_elapsed()),
            'eta'         => $iterator->get_eta((bool) $job),
            'lock'        => $iterator->get_lock(),
            'job'         => $job,
            'errors'      => array_slice(is_array($store['errors'] ?? null) ? $store['errors'] : [], -10),
        ]);

    }

    public function start ($args, $assoc) {

        $iterator = $this->find((string) $args[0]);

        if (isset($assoc['foreground'])) { $this->foreground($iterator); return; }

        $handed = $iterator->background();

        if (!empty($handed['refused'])) $this->fail((string) $handed['message']);

        $id = is_int($handed['job'] ?? null) ? $handed['job'] : null;

        WP_CLI::log('Handed to the background' . ($id ? " (job #{$id})" : ' (queued behind the open job)') . '.');

        if (!isset($assoc['run'])) return;

        // What `wp lattice jobs run --id` does, on the job just dispatched: `now` runs nothing on a CLI shutdown.
        $job = ($id && class_exists(Job::class)) ? Job::get_instance($id) : null;

        if (!$job instanceof Job)                          $this->fail('The job is queued behind the open one; the worker runs it in turn.');
        if (!Jobs_Worker::inst()->run_named($job))         $this->fail('The job could not be taken now (the worker is paused, or the job is leased); it runs on the next tick.');

        $state = (string) $job->get_state();

        if ($state === Job::DONE)    WP_CLI::success('Complete: ' . $this->progress($iterator) . '.');
        elseif ($state === Job::PENDING) WP_CLI::success("The budget is spent at {$this->progress($iterator)}; the worker continues the job.");
        else                         $this->fail("Background run {$state} at {$this->progress($iterator)}" . (($error = $job->get_error()) ? ": {$error}" : '.'));

    }

    // The shell as a foreground driver, the page's kind: under the lock, so a page Start mid-run is refused instead of racing the
    // batch in flight. Resumes a halted run on its first batch. Ctrl-C releases the lock when pcntl is loaded; without it the
    // page reads Running until the CLI window (LOCK_STALE_UNLIMITED) closes.
    protected function foreground (Iterator $iterator) : void {

        if ($iterator->get_status() === 'complete') $this->fail('The run is complete; reset first.');
        if ($job = $iterator->open_job())            $this->fail((string) $iterator->job_refusal($job)['message']);
        if (!$token = $iterator->take_lock())        $this->fail((string) $iterator->lock_refusal()['message']);

        if (function_exists('pcntl_signal')) {

            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use ($iterator) { $iterator->stop(); WP_CLI::halt(130); });

        }

        $resume = true;

        try {

            do {

                $batch  = $iterator->run_batch((string) $token, $resume);
                $resume = false;

                if ($batch['ran'] && !$batch['refused']) WP_CLI::log(sprintf('%s: %d to %d of %s%s', $batch['status'], $batch['from'], $batch['index'], $batch['total'] ?? '?', $batch['retry'] ? " ({$batch['retry']} to retry)" : ''));

            } while ($batch['ran'] && !$batch['refused'] && !$batch['stalled'] && !in_array($batch['status'], ['complete', 'halted'], true));

        } finally {

            $iterator->stop(); // releases only this driver's lock: a throwing process_item() must not leave the page reading Running for the CLI window

        }

        if (!$batch['ran'])                 $this->fail("Nothing ran: the record is {$batch['status']}.");
        if ($batch['refused'])              $this->fail((string) ($batch['message'] ?? 'The batch was refused.'));
        if ($batch['status'] === 'halted')  $this->fail('Halted at ' . $this->progress($iterator) . ': ' . $this->last_error($iterator));
        if ($batch['stalled'])            { WP_CLI::warning('Stalled at ' . $this->progress($iterator) . ': the source answered no items.'); WP_CLI::halt(2); }

        WP_CLI::success('Complete: ' . $this->progress($iterator) . '.');

    }

    public function batch ($args, $assoc) {

        $batch = $this->find((string) $args[0])->run_batch();

        $this->table([array_map(fn ($v) => is_bool($v) ? var_export($v, true) : $v, $batch)], array_keys($batch));

        if ($batch['refused']) $this->fail((string) ($batch['message'] ?? 'The batch was refused.'));

    }

    public function stop ($args, $assoc) {

        $outcome = $this->find((string) $args[0])->stop_cron()['cancelled'];

        if ($outcome === 'requested')     WP_CLI::success('Stopping: the run ends before its next step.');
        elseif ($outcome === 'cancelled') WP_CLI::success('Stopped.');
        else                              WP_CLI::warning('Nothing is running in the background.');

    }

    public function reset ($args, $assoc) {

        $reset = $this->find((string) $args[0])->reset();

        if (!empty($reset['refused'])) $this->fail((string) $reset['message']);

        WP_CLI::success('Reset.');

    }

    protected function find (string $key) : Iterator {

        if ($iterator = Iterator::find($key)) return $iterator;

        $declared = Iterator::declared($key);

        $this->fail($declared ? "{$declared} declares '{$key}' but is not loaded: it opts out of auto-instantiation." : "No loaded iterator has the key '{$key}'.");

    }

    protected function progress (Iterator $iterator) : string {

        $total = $iterator->get_total();

        return $iterator->get_index() . (is_null($total) ? '' : " of {$total}");

    }

    protected function last_error (Iterator $iterator) : string {

        $errors = $iterator->get_store()['errors'] ?? [];

        return is_array($errors) && $errors ? wp_strip_all_tags((string) end($errors)) : 'see the run log';

    }

}
