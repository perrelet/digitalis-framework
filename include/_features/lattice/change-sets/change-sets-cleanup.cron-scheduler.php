<?php

namespace Lattice;

// A set nobody is working on: anything submitted or unsettled waits for a person.
class Change_Sets_Cleanup extends Cron_Scheduler {

    protected $tasks = [
        'withdraw_idle' => [
            'schedule' => 'daily',
            'time'     => '03:45:00',
        ],
    ];

    public function withdraw_idle () {

        global $wpdb;

        $resource = Resources::inst()->get_resource('change_set');
        $days     = max(1, (int) apply_filters('lattice.change_set.cleanup_days', 90));
        $before   = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $ids      = $wpdb->get_col($wpdb->prepare('SELECT set_id FROM ' . Change_Set::table() . ' WHERE state IN (%s, %s) AND updated_at < %s', Change_Set::DRAFT, Change_Set::BROKEN, $before));

        foreach ((array) $ids as $id) {

            try {

                if ($set = $resource->find((int) $id)) $resource->withdraw_idle($set, $before, "Idle for over {$days} days.");

            } catch (\Throwable $e) {

                error_log("Change sets: idle set {$id} could not be withdrawn: " . $e->getMessage());

            }

        }

    }

}
