<?php

declare(strict_types=1);

namespace Lattice;

class Iterator_UI extends View {

    protected static $template = 'iterator';
    protected static $template_path = LATTICE_PATH . "templates/digitalis/ui/";

    protected static $defaults = [
        'iterator' => null,
        'before_description' => '',
        'after_description'  => '',
        'before_controls'    => '',
        'after_controls'     => '',
        'before_progress'    => '',
        'after_progress'     => '',
        'before_log'         => '',
        'after_log'          => '',
        'log_url'            => '',
    ];

    public function params (array &$p) {

        if (!($p['iterator'] instanceof Iterator)) return;

        $iterator = $p['iterator'];
        $store    = $iterator->get_store();
        $lock     = $iterator->get_lock();
        $job      = $iterator->open_job();                      // the background run, from the runner: null without a runner or an open run
        $cron     = (bool) $job;
        $running  = $cron || ((bool) $lock && !$lock['stale']); // a live driver: the job, this page's lock or another tab's; a stale lock is nobody's
        $status   = $iterator->get_status();
        $stalled  = (bool) ($store['stalled'] ?? false);
        $total    = $iterator->has_total() ? $iterator->get_total() : $iterator->refresh_total(); // computed once per run, not once per render
        $elapsed  = (int) floor($iterator->get_elapsed());
        $started  = $iterator->get_started_at();
        $eta      = ($cron && $status === 'running' && !$stalled) ? $iterator->get_eta(true) : null; // a paused page has no pace; the script estimates a live run
        $log      = $iterator->get_log();

        $labels = ['halted' => 'Halted', 'complete' => 'Complete', 'running' => 'Stopped'];
        $states = ['halted' => ['is-halted', 'err'], 'complete' => ['is-complete'], 'running' => ['is-stopped']];

        // A live lock outranks the record (a running record with no driver is a stopped run); a stall outranks the lock.
        $classes = ['digitalis-iterator', 'iterator', 'iterator-' . $iterator->get_key()];

        if ($cron)           $classes[] = 'doing_cron';
        if ($running)        $classes[] = 'running';
        if ($stalled)        $classes[] = 'is-stalled';
        elseif (!$running)   $classes = array_merge($classes, $states[$status] ?? []);
        if (is_null($total)) $classes[] = 'is-indeterminate';

        $p['total']   = $total;
        $p['index']   = $iterator->get_index();
        $p['store']   = $store;
        $p['cron']    = $cron;
        $p['failed']  = $iterator->get_failed_items(); // built once: it resolves an edit link per item
        $p['classes'] = implode(' ', $classes);
        $p['label']   = $stalled ? 'Stalled' : ($job ? ($job['stopping'] ? 'Stopping' : ($job['running'] ? 'Running' : 'Queued')) : ($running ? 'Running' : ($labels[$status] ?? 'Ready')));
        $p['time']    = $job ? 'Background' . (is_int($job['since']) ? ' · started ' . $iterator->format_time($job['since']) : '') : sprintf('%02d:%02d:%02d', intdiv($elapsed, 3600), intdiv($elapsed, 60) % 60, $elapsed % 60);
        $p['job']     = $job ? ' · ' . (empty($job['url']) ? "job #{$job['id']}" : "<a href='" . esc_url((string) $job['url']) . "'>job #{$job['id']}</a>") : ''; // the row on Tools -> Jobs, one click away while it is open
        $p['eta']     = is_null($eta) ? '' : 'about ' . static::about($eta) . ' left';
        $p['log_url'] = ($p['log_url'] && $log && is_file((string) $log->get_path())) ? $p['log_url'] : ''; // the full log, when there is one to link

    }

    protected static function about (int $seconds) : string {

        if ($seconds < 60)   return "{$seconds}s";
        if ($seconds < 3600) return intdiv($seconds, 60) . 'm';

        return intdiv($seconds, 3600) . 'h ' . (intdiv($seconds, 60) % 60) . 'm';

    }

    public function condition () {

        return ($this['iterator'] instanceof Iterator);

    }

}
