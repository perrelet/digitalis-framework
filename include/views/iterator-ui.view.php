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
        $cron     = $iterator->is_doing_cron();
        $status   = $iterator->get_status();
        $stalled  = (bool) ($store['stalled'] ?? false);
        $total    = $iterator->has_total() ? $iterator->get_total() : $iterator->refresh_total(); // computed once per run, not once per render
        $elapsed  = (int) floor($iterator->get_elapsed());
        $started  = $iterator->get_started_at();
        $eta      = ($cron && $status === 'running' && !$stalled) ? $iterator->get_eta(true) : null; // a paused page has no pace; the script estimates a live run
        $log      = $iterator->get_log();

        $labels = ['halted' => 'Halted', 'complete' => 'Complete', 'running' => 'Paused'];
        $states = ['halted' => ['is-halted', 'err'], 'complete' => ['is-complete'], 'running' => ['is-paused']];

        // The cron flag outranks the record (a UI run the Stop button interrupted stays running in the store); a stall outranks the flag.
        $classes = ['digitalis-iterator', 'iterator', 'iterator-' . $iterator->get_key()];

        if ($cron)           $classes = array_merge($classes, ['doing_cron', 'running']);
        if ($stalled)        $classes[] = 'is-stalled';
        elseif (!$cron)      $classes = array_merge($classes, $states[$status] ?? []);
        if (is_null($total)) $classes[] = 'is-indeterminate';

        $p['total']   = $total;
        $p['index']   = $iterator->get_index();
        $p['store']   = $store;
        $p['cron']    = $cron;
        $p['failed']  = $iterator->get_failed_items(); // built once: it resolves an edit link per item
        $p['classes'] = implode(' ', $classes);
        $p['label']   = $stalled ? 'Stalled' : ($cron ? 'Running' : ($labels[$status] ?? 'Ready'));
        $p['time']    = $cron ? 'Cron task' . ($started ? ' · started ' . $iterator->format_time($started) : '') : sprintf('%02d:%02d:%02d', intdiv($elapsed, 3600), intdiv($elapsed, 60) % 60, $elapsed % 60);
        $p['eta']     = is_null($eta) ? '' : 'about ' . static::about($eta) . ' left';
        $p['log_url'] = ($p['log_url'] && $log && is_file($log->get_path())) ? $p['log_url'] : ''; // the full log, when there is one to link

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
