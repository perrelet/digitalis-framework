<h1 class="wp-heading-inline">Proposed Changes</h1>
<hr class="wp-header-end">

<?php if (!$rows): ?>

    <p><?= esc_html($reviewer ? 'No open proposals. A proposal shows here once its author submits it.' : 'You have no open proposals. "Propose changes" on a published item starts one.') ?></p>

<?php else: ?>

    <table class="widefat striped lattice-proposal-queue">
        <thead><tr><th>Item</th><th>Type</th><th>Proposed by</th><th>Status</th><th>Changes</th><th>Last activity</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?php if ($row['review_url']): ?><a href="<?= esc_url($row['review_url']) ?>"><strong><?= esc_html($row['title']) ?></strong></a><?php else: ?><strong><?= esc_html($row['title']) ?></strong><?php endif; ?>
                        <?php if ($row['live_url']): ?><a class="lattice-proposal-live" href="<?= esc_url($row['live_url']) ?>">live &#8599;</a><?php else: ?><em class="lattice-proposal-gone">(live version removed)</em><?php endif; ?>
                    </td>
                    <td><?= esc_html($row['type']) ?></td>
                    <td><?= esc_html($row['author'] ?? '∅') ?></td>
                    <td>
                        <span class="lattice-proposal-pill is-<?= esc_attr($row['status']) ?>"><?= esc_html(Digitalis\Proposals_Queue::STATUSES[$row['status']]) ?></span>
                        <?php if ($row['stale']): ?><span class="lattice-proposal-pill is-stale">Stale</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (is_null($row['changes'])): ?>∅
                        <?php elseif (!$row['changes']): ?><span class="lattice-proposal-muted">No changes yet</span>
                        <?php else: ?><span title="<?= esc_attr(implode(', ', $row['changes'])) ?>"><strong><?= count($row['changes']) ?></strong> · <?= esc_html(implode(', ', array_slice($row['changes'], 0, 4)) . ((count($row['changes']) > 4) ? ' +' . (count($row['changes']) - 4) : '')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc_html($row['activity']) ?></td>
                    <td class="lattice-proposal-actions">
                        <?php if ($row['review_url']): ?><a class="button button-small" href="<?= esc_url($row['review_url']) ?>"><?= esc_html($row['open_label']) ?></a><?php endif; ?>
                        <?php if ($row['reject_url']): ?><button type="button" class="button button-small" data-lattice-proposal-reject="<?= esc_url($row['reject_url']) ?>">Send back</button><?php endif; ?>
                        <?php if ($row['delete_url']): ?><a class="button button-small lattice-proposal-delete" href="<?= esc_url($row['delete_url']) ?>" data-lattice-proposal-confirm="<?= esc_attr($reviewer ? 'Discard this proposal? Its draft goes to the Trash, and the live version can take a new proposal.' : 'Withdraw this proposal? Your draft goes to the Trash.') ?>"><?= $reviewer ? 'Discard' : 'Withdraw' ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>
