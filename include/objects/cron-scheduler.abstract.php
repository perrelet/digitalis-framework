<?php

namespace Digitalis;

use DateTime;
use DateTimeZone;

abstract class Cron_Scheduler extends Singleton {

    protected $timezone = 'UTC';

    protected $tasks = [
        // 'task' => [
        //     'schedule' => 'daily',
        //     'time'     => '01:45:00',
        // ],
    ];

    public function schedules ($schedules) {

        return $schedules;
    
    }

    // public function task () {
    // 
    //     
    // 
    // }

    public function init () {
    
        add_filter('cron_schedules', [$this, 'schedules']);

        $this->schedule_tasks();
    
    }

    public function schedule_tasks () {

        foreach ($this->tasks as $key => $task) {

            $cron_action = strtolower(static::class) . '_' . $key;

            if (!is_callable([$this, $key])) continue;

            add_action($cron_action, [$this, $key]);

            if (!wp_next_scheduled($cron_action)) {

                $task = wp_parse_args($task, [
                    'schedule' => 'daily',
                    'time'     => '00:00:00',
                ]);

                $date_string = (new DateTime)->format('Y-m-d') . ' ' . $task['time'];
                $date_time = DateTime::createFromFormat('Y-m-d H:i:s', $date_string, new DateTimeZone($this->get_timezone()));

                wp_schedule_event($date_time->getTimestamp(), $task['schedule'], $cron_action);

            }

        }
    
    }

    public function get_timezone () {
    
        return $this->timezone;
    
    }

}