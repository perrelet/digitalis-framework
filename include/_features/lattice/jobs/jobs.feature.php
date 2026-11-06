<?php

declare(strict_types=1);

namespace Lattice;

require_once __DIR__ . '/job-signal.class.php';
require_once __DIR__ . '/jobs-log.log.php';
require_once __DIR__ . '/jobs.db-table.php';
require_once __DIR__ . '/v1-create-jobs.migration.php';
require_once __DIR__ . '/jobs.schema.php';
require_once __DIR__ . '/job.model.php';
require_once __DIR__ . '/job-end.job.php';
require_once __DIR__ . '/jobs-worker.service.php';
require_once __DIR__ . '/jobs-tick.cron-scheduler.php';
require_once __DIR__ . '/jobs-command.class.php';
require_once __DIR__ . '/jobs.sub-page.php';

class Lattice_Jobs extends Feature {

    const LATE       = 'lattice_jobs_late';
    const LATE_AFTER = 15 * MINUTE_IN_SECONDS;

    public function get_hooks () {

        return [
            'cli_init'      => 'register_commands',
            'admin_notices' => 'print_notices',
        ];

    }

    public function run () {

        // Hand-required, so the autoloader never calls it.
        Job_End::static_init();

        if (!Jobs_Schema::is_current() && ((Jobs_Worker::inst()->get_pause()['why'] ?? '') !== Jobs_Worker::UNINSTALLED)) Jobs_Schema::ensure();

        Jobs_Tick::get_instance();

        if (is_admin()) Jobs_Page::get_instance();

    }

    public function register_commands () {

        Jobs_Command::register();

    }

    public function print_notices () {

        if (!current_user_can('manage_options')) return;

        $worker = Jobs_Worker::inst();
        $pause  = $worker->get_pause();

        if ($pause)                          $this->print_notice('Jobs are paused: ' . $pause['why'] . '.');
        elseif (!Jobs_Schema::is_current())  $this->print_notice('The jobs table is not current, so jobs are not running.');
        elseif ($this->is_late())            $this->print_notice('Jobs are late: nothing appears to be running WordPress cron.');

    }

    // With no real cron, the first visit is itself what wakes it.
    protected function is_late () : bool {

        $next = Jobs_Tick::get_instance()->get_tick_time();
        $seen = (int) get_option(static::LATE);

        if (!$next || ($next > (time() - static::LATE_AFTER))) {

            if ($seen) delete_option(static::LATE);

            return false;

        }

        if (!$seen) update_option(static::LATE, time(), false);

        return $seen && ($seen < (time() - static::LATE_AFTER));

    }

    protected function print_notice (string $message) : void {

        printf('<div class="notice notice-warning"><p>%s <a href="%s">See Jobs</a>.</p></div>', esc_html($message), esc_url(Jobs_Page::get_instance()->get_url()));

    }

}
