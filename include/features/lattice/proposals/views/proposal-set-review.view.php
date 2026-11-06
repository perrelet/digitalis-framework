<?php

namespace Digitalis;

class Proposal_Set_Review extends View {

    protected static $template      = 'proposal-set-review';
    protected static $template_path = __DIR__ . '/../templates/';

    protected static $defaults = [
        'set_id' => 0,
    ];

    public function params (&$p) {

        $review = Resources::inst()->view('change_set', $p['set_id'], 'review');

        $p['refusal'] = is_wp_error($review) ? $review->get_error_message() : '';
        $p['review']  = $review;
        $p['forms']   = [];
        $p['reject']  = '';
        $p['undo']    = [];
        $p['refused'] = [];

        if (!$p['refusal']) $this->prepare_forms($p);

        parent::params($p);

    }

    protected function prepare_forms (&$p) : void {

        $api    = Resources::inst();
        $set    = $api->get_resource('change_set')->find($p['set_id']);
        $record = $api->get('change_set', $p['set_id']);
        $review = $p['review'];
        $usable = fn (string $action) => !empty($record['actions'][$action]['usable']);
        $url    = fn (string $action) => Proposals_Admin::get_set_action_url($action, $set);
        $seen   = fn (array $looks, array $keys) => array_map(fn ($look) => array_intersect_key($look, $keys), $looks);

        foreach (['submit_proposal' => 'Submit for review', 'rebase_proposal' => 'Start from what is live'] as $action => $label) if ($usable($action)) $p['forms'][] = ['label' => $label, 'url' => $url($action), 'primary' => $action === 'submit_proposal'];

        // Refused whole, it would go back to its author with nothing to tell them: sending it back says why.
        $p['refused'] = array_filter($review['steps'], fn ($step) => empty($step['reached']) && empty($step['done']) && !in_array($step['refusal']['code'] ?? 'stale', ['stale'], true));

        // Undone, it could only be refused (D55).
        if ($usable('approve_proposal') && !$review['undone'] && !$p['refused']) $p['forms'][] = ['label' => 'Approve and apply', 'url' => $url('approve_proposal'), 'primary' => true, 'fields' => ['seen' => wp_json_encode(['version' => $review['version'], 'steps' => $seen($review['steps'], ['fingerprint' => 0, 'version' => 0, 'step_hash' => 0])])], 'confirm' => 'Apply these steps to what is live now?'];

        if ($usable('reject_proposal')) $p['reject'] = $url('reject_proposal');

        if ($usable('withdraw_proposal')) $p['forms'][] = ['label' => 'Withdraw', 'url' => $url('withdraw_proposal'), 'confirm' => 'Withdraw this set? What it applied stays.'];

        if ($usable('settle_step')) foreach ($set->get_steps() as $position => $step) if ($step['marker'] === 'unknown') foreach (['applied' => 'landed', 'not_applied' => 'did not land'] as $outcome => $words) $p['forms'][] = ['label' => "Step {$position} {$words}", 'url' => $url('settle_step'), 'fields' => ['position' => $position, 'outcome' => $outcome]];

        $undo = $usable('undo_set') ? $api->act('change_set', $p['set_id'], 'undo_set', ['dry_run' => true], 'admin') : null;

        if (!is_array($undo) || empty($undo['rows'])) return;

        $p['undo'] = $undo['rows'];

        if (array_filter($undo['rows'], fn ($row) => !empty($row['can_undo']))) $p['forms'][] = ['label' => 'Undo what it applied', 'url' => $url('undo_set'), 'fields' => ['seen' => wp_json_encode(['version' => $undo['version'], 'rows' => $seen($undo['rows'], ['fingerprint' => 0, 'version' => 0])])], 'confirm' => 'Undo what this set applied, newest first?'];

    }

    public static function show_cell ($value) : string {

        return (static::show($value) === '') ? '∅' : static::show($value);

    }

    public static function show ($value) : string {

        return is_scalar($value) ? (string) $value : (string) wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    }

}
