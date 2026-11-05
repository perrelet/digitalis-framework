<?php

namespace Digitalis;

final class Job_End extends Job {

    protected static $type       = 'lattice/job-end';
    protected static $expires_in = null;

    public function handle (int $job, string $state) {

        $ended = Job::get_instance($job);

        if (!$ended || (get_class($ended) === Job::class)) $this->cancel('The job or its class has gone.');
        if ($ended->get_state() !== $state)                $this->cancel('The job has moved on.');

        $ended->on_end($state);

    }

    public static function open_for (int $job, string $state) : bool {

        global $wpdb;

        $args = wp_json_encode(['job' => $job, 'state' => $state]);

        return $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . static::table() . ' (type, args, job_key, open, state, run_after, created_at) VALUES (%s, %s, %s, 1, %s, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            . ' ON DUPLICATE KEY UPDATE args = VALUES(args), attempts = 0, again = IF(lease_token IS NOT NULL, 1, again)',
            static::$type, $args, "job:{$job}", static::PENDING
        )) !== false;

    }

}
