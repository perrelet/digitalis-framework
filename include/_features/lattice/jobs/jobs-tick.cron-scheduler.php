<?php

namespace Lattice;

class Jobs_Tick extends Cron_Scheduler {

    const SCHEDULE = 'lattice_jobs_minute';

    protected $tasks = [
        'tick' => [
            'schedule' => self::SCHEDULE,
            'time'     => '00:00:00',
        ],
        'prune' => [
            'schedule' => 'daily',
            'time'     => '03:45:00',
        ],
    ];

    public function schedules ($schedules) {

        $schedules[static::SCHEDULE] ??= ['interval' => MINUTE_IN_SECONDS, 'display' => 'Every minute (jobs)'];

        return $schedules;

    }

    public function schedule_tasks () {

        if ((Jobs_Worker::inst()->get_pause()['why'] ?? '') === Jobs_Worker::UNINSTALLED) {

            if (get_option($this->get_option_name())) $this->unschedule_tasks();

            return;

        }

        parent::schedule_tasks();

    }

    public function get_tick_time () : ?int {

        return wp_next_scheduled($this->get_action_name('tick')) ?: null;

    }

    public function tick () {

        Jobs_Worker::inst()->tick();

    }

    public function prune () {

        Jobs_Worker::inst()->prune();

    }

}
