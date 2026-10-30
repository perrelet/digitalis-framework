<?php

declare(strict_types=1);

namespace Lattice;

use DateTime;
use DateTimeZone;

abstract class Cron_Scheduler extends Singleton {

    // H:i:s and nothing else: createFromFormat() would accept '9:00:00' and roll '25:61:61' over to tomorrow without a word.
    public const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/';

    protected $timezone = null;

    protected $tasks = [
        // 'task' => [
        //     'schedule' => 'daily',
        //     'time'     => '01:45:00',
        // ],
    ];

    public function schedules ($schedules) {

        return $schedules;

    }

    public function __construct () {

        add_filter('cron_schedules', [$this, 'schedules']);

        $this->schedule_tasks();

    }

    //

    protected function get_action_name ($key) {

        return sanitize_key(static::class . '_' . $key);

    }

    protected function get_option_name () {

        return sanitize_key('digitalis_cron_' . static::class);

    }

    protected function get_task_hash ($task) {

        return crc32(serialize($task));

    }

    public function get_timezone () {

        return $this->timezone ?? wp_timezone_string();

    }

    //

    public function schedule_tasks () {

        $stored = get_option($this->get_option_name(), []);
        $dirty  = false;

        // Unschedule removed tasks

        foreach ($stored as $key => $entry) {
            if (!isset($this->tasks[$key])) {
                wp_clear_scheduled_hook($this->get_action_name($key));
                unset($stored[$key]);
                $dirty = true;
            }
        }

        // Schedule new or updated tasks

        foreach ($this->tasks as $key => $task) {

            if (!is_callable([$this, $key])) continue;

            $action = $this->get_action_name($key);
            $task   = wp_parse_args($task, [
                'schedule' => 'daily',
                'time'     => '00:00:00',
            ]);
            $hash = $this->get_task_hash($task);

            add_action($action, [$this, $key]);

            // Migrate old backslash-format action names

            $legacy_action = strtolower(static::class) . '_' . $key;
            if ($legacy_action !== $action && wp_next_scheduled($legacy_action)) {
                wp_clear_scheduled_hook($legacy_action);
            }

            // Skip if already scheduled with the same config

            $stored_hash = isset($stored[$key]) ? $stored[$key]['hash'] : null;

            if ($stored_hash === $hash && wp_next_scheduled($action)) continue;

            // Reschedule

            wp_clear_scheduled_hook($action);

            $date_string = (new DateTime)->format('Y-m-d') . ' ' . $task['time'];
            $date_time   = DateTime::createFromFormat('Y-m-d H:i:s', $date_string, new DateTimeZone($this->get_timezone()));

            if (!preg_match(self::TIME_PATTERN, (string) $task['time']) || !$date_time) {

                Strict::violation(static::class, "declares task '{$key}' at '{$task['time']}', which is not an H:i:s time.", "Declare the task's 'time' as 'H:i:s', '01:45:00' for example.");
                Strict::log_once(static::class, "task '{$key}' time '{$task['time']}' is not an H:i:s time; it is not scheduled.");
                continue;

            }

            wp_schedule_event($date_time->getTimestamp(), $task['schedule'], $action);

            $stored[$key] = [
                'hash' => $hash,
                'time' => time(),
            ];
            $dirty = true;

        }

        if ($dirty) update_option($this->get_option_name(), $stored, false);

    }

    public function unschedule_tasks () {

        foreach ($this->tasks as $key => $task) {
            wp_clear_scheduled_hook($this->get_action_name($key));
        }

        delete_option($this->get_option_name());

    }

}