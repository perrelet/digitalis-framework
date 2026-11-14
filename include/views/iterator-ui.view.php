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
        $state    = $iterator->get_run_state();
        $cron     = in_array($state, $iterator::JOB_STATES, true);
        $job      = $cron ? $iterator->open_job() : null;      // the background run, for its start time and its row's link
        $running  = in_array($state, $iterator::BUSY_STATES, true); // a live driver: the job, this page's lock or another tab's
        $stalled  = $iterator->is_stalled();
        $total    = $iterator->has_total() ? $iterator->get_total() : ($running ? null : $iterator->refresh_total()); // computed once per run, never under a driver: refresh_total() writes the whole record, and a job's first step may be counting it
        $eta      = ($cron && $iterator->get_status() === 'running' && !$stalled) ? $iterator->get_eta(true) : null; // a paused page has no pace; the script estimates a live run
        $log      = $iterator->get_log();

        $labels = $iterator->get_ui_labels();
        $states = ['halted' => ['is-halted', 'err'], 'complete' => ['is-complete'], 'stopped' => ['is-stopped']];

        // A stall outranks the state in the label and stands beside it in the classes.
        $classes = ['digitalis-iterator', 'iterator', 'iterator-' . $iterator->get_key()];

        if ($cron)           $classes[] = 'doing_cron';
        if ($running)        $classes[] = 'running';
        if ($stalled)        $classes[] = 'is-stalled';
        elseif (!$running)   $classes = array_merge($classes, $states[$state] ?? []);
        if (is_null($total)) $classes[] = 'is-indeterminate';

        $p['total']   = $total;
        $p['index']   = $iterator->get_index();
        $p['store']   = $store;
        $p['cron']    = $cron;
        $p['failed']  = $iterator->get_failed_items(); // built once: it resolves an edit link per item
        $p['classes'] = implode(' ', $classes);
        $p['state']   = $state;
        $p['busy']    = $running;
        $p['labels']  = $labels;
        $p['label']   = $labels[$stalled ? 'stalled' : $state];
        $p['time']    = $job ? 'Background' . (is_int($job['since']) ? ' · started ' . $iterator->format_time($job['since']) : '') : $iterator->format_elapsed($iterator->get_elapsed());
        $p['job']     = $job ? ' · ' . (empty($job['url']) ? "job #{$job['id']}" : "<a href='" . esc_url((string) $job['url']) . "'>job #{$job['id']}</a>") : ''; // the row on Tools -> Jobs, one click away while it is open
        $p['eta']     = is_null($eta) ? '' : $iterator->format_eta($eta);
        $p['log_url'] = ($p['log_url'] && $log && is_file((string) $log->get_path())) ? $p['log_url'] : ''; // the full log, when there is one to link

    }

    public function condition () {

        return ($this['iterator'] instanceof Iterator);

    }

}
