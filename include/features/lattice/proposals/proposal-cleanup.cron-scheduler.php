<?php

namespace Digitalis;

// Submitted drafts wait for their review.
class Proposal_Cleanup extends Cron_Scheduler {

    protected $tasks = [
        'delete_abandoned' => [
            'schedule' => 'daily',
            'time'     => '03:30:00',
        ],
    ];

    public function delete_abandoned () {

        if (!$types = Proposal::get_post_types()) return;

        $days = max(1, (int) apply_filters('lattice.proposal.cleanup_days', 90));

        $drafts = Post::query([
            'post_type'      => $types,
            'post_status'    => 'draft',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            '_suppress'      => true,
            'meta_query'     => [['key' => Proposal::META['FOR'], 'compare' => 'EXISTS']],
            'date_query'     => [['column' => 'post_modified_gmt', 'before' => gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))]],
        ]);

        if (!$drafts) return;

        foreach ($drafts as $draft) $draft->delete(true);

        Log::w(sprintf('Deleted %d abandoned proposal drafts idle over %d days.', count($drafts), $days), 'proposal-cleanup.log');

    }

}
