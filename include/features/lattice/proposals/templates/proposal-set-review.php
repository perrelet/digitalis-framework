<h1 class="wp-heading-inline">Proposed Changes</h1>
<a href="<?= esc_url(Digitalis\Proposal::get_queue_url()) ?>" class="page-title-action">Back to the queue</a>
<hr class="wp-header-end">

<?php if ($refusal): ?>

    <?= new Digitalis\Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => $refusal]) ?>

<?php else: ?>

    <h2><?= nl2br(esc_html($review['purpose'] ?: 'A set with no purpose')) ?></h2>

    <p class="lattice-proposal-muted">
        <span class="lattice-set-flag"><?= esc_html($review['state']) ?></span>
        By <strong><?= esc_html(get_userdata($review['author_id'])->display_name ?? '∅') ?></strong><?= $review['submitted_at'] ? ' · offered for review ' . esc_html($review['submitted_at']) . ' UTC' : '' ?>
    </p>

    <?php if ($review['note'] ?? ''): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'A reviewer asked for changes:', 'message' => $review['note']]) ?><?php endif; ?>
    <?php if ($review['reason'] ?? ''): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'It stopped:', 'message' => $review['reason']]) ?><?php endif; ?>
    <?php if ($review['undone'] && !$review['closed']): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'message' => 'What it applied has been undone, at least in part, so it cannot go on: withdraw it.']) ?><?php endif; ?>
    <?php if ($review['unsettled']): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'message' => 'A step may or may not have landed. Check the record, then say which: nothing else can happen until then.']) ?><?php endif; ?>
    <?php if ($refused): ?><?= new Digitalis\Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => 'Approving would be refused, for the reasons below, and you may not be able to fix that. Send it back to tell its author why.']) ?><?php endif; ?>
    <?php if ($review['as'] === 'author'): ?><p><em>You see what can be said without the right to write these records. A reviewer sees each step's preview.</em></p><?php endif; ?>

    <?php foreach ($review['records'] as $record => $positions): ?>

        <div class="lattice-set-record">

            <h3><?= esc_html($record) ?></h3>

            <table class="widefat striped lattice-set-steps">
                <tbody>
                    <?php foreach ($positions as $position): $step = $review['steps'][$position]; ?>
                        <tr>
                            <th scope="row">
                                <?= esc_html('Step ' . $position . ': ' . $step['op'] . (empty($step['action']) ? '' : " {$step['action']}")) ?>
                                <br>
                                <?php if ($step['marker'] ?? ''): ?><span class="lattice-set-flag"><?= esc_html($step['marker']) ?></span><?php endif; ?>
                                <?php foreach ((array) ($step['flags'] ?? []) as $flag): ?><span class="lattice-set-flag"><?= esc_html($flag) ?></span><?php endforeach; ?>
                            </th>
                            <td>
                                <?php if (!empty($step['reached'])): ?><p><strong>Previewed when reached</strong>, after step <?= esc_html(implode(', ', (array) ($step['after'] ?? []))) ?>, which will then be live.</p><?php endif; ?>
                                <?php if (($step['checked'] ?? null) === false && empty($step['reached']) && empty($step['done'])): ?><p><em>Not checked for you.</em></p><?php endif; ?>
                                <?php foreach (['values' => 'Holds', 'input' => 'Input', 'removed' => 'Filtering removed', 'applied' => 'Would apply', 'skipped' => 'Already live', 'withheld' => 'Withheld', 'problems' => 'Problems', 'locked' => 'Locked', 'error' => 'Error'] as $key => $label): ?>
                                    <?php if (!empty($step[$key])): ?><p><strong><?= esc_html($label) ?></strong></p><pre><?= esc_html(Digitalis\Proposal_Set_Review::show($step[$key])) ?></pre><?php endif; ?>
                                <?php endforeach; ?>
                                <?php foreach ((array) ($step['conflicts'] ?? []) as $conflict): ?>
                                    <?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'Conflict on ' . $conflict['field'] . ':', 'message' => !empty($conflict['masking']) ? 'this may be a difference in how a secret is masked, not someone\'s change.' : 'it no longer holds what this started from.']) ?>
                                    <pre><?= esc_html(Digitalis\Proposal_Set_Review::show(array_diff_key($conflict, ['field' => 0, 'masking' => 0]))) ?></pre>
                                <?php endforeach; ?>
                                <?php if (!empty($step['refusal'])): ?><?= new Digitalis\Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => is_array($step['refusal']) ? ($step['refusal']['message'] ?? '') : $step['refusal']]) ?><?php endif; ?>
                                <?php if (!empty($step['preview']['changes'])): ?>
                                    <table class="widefat lattice-proposal-changes">
                                        <thead><tr><th>Would change</th><th>Live</th><th>Proposed</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($step['preview']['changes'] as $change): ?>
                                                <tr>
                                                    <th scope="row"><?= esc_html($change['field'] ?? '') ?></th>
                                                    <td><?= nl2br(esc_html(Digitalis\Proposal_Set_Review::show_cell($change['before'] ?? ''))) ?></td>
                                                    <td><?= nl2br(esc_html(Digitalis\Proposal_Set_Review::show_cell($change['after'] ?? ''))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                                <?php if (!empty($step['preview']['removed'])): ?><p><strong>Filtering would remove, as you</strong></p><pre><?= esc_html(Digitalis\Proposal_Set_Review::show($step['preview']['removed'])) ?></pre><?php endif; ?>
                                <?php if (!empty($step['preview']['consequences'])): ?><p><strong>What follows</strong></p><pre><?= esc_html(Digitalis\Proposal_Set_Review::show($step['preview']['consequences'])) ?></pre><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>

    <?php endforeach; ?>

    <?php if ($undo): ?>
        <p><strong>Undoing it, newest first</strong></p>
        <ul class="lattice-proposal-list">
            <?php foreach ($undo as $row): ?>
                <li><?= esc_html("{$row['action']} {$row['resource']} {$row['record_id']}" . (!empty($row['passed_over']) ? ': nothing to reverse' : (empty($row['can_undo']) ? ': cannot be undone' : ''))) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="lattice-proposal-actions">
        <?php foreach ($forms as $form): ?>
            <form method="post" action="<?= esc_url($form['url']) ?>">
                <?php foreach ((array) ($form['fields'] ?? []) as $name => $value): ?><input type="hidden" name="<?= esc_attr($name) ?>" value="<?= esc_attr($value) ?>"><?php endforeach; ?>
                <button type="submit" class="button<?= empty($form['primary']) ? '' : ' button-primary' ?>"<?= empty($form['confirm']) ? '' : ' data-lattice-proposal-confirm="' . esc_attr($form['confirm']) . '"' ?>><?= esc_html($form['label']) ?></button>
            </form>
        <?php endforeach; ?>
        <?php if ($reject): ?><button type="button" class="button" data-lattice-proposal-reject="<?= esc_url($reject) ?>">Send back with a note</button><?php endif; ?>
    </div>

<?php endif; ?>
