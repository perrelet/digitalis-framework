<p class="lattice-proposal-muted"><?php if ($author): ?>Proposed by <strong><?= esc_html($author) ?></strong><?php else: ?>Proposed change<?php endif; ?><?= $created ? ' · ' . esc_html($created) : '' ?></p>

<?php if (!$original): ?>

    <?= new Digitalis\Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => 'The live version no longer exists, so this can\'t be applied.']) ?>

<?php else: ?>

    <?php if ($stale): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'The live version changed after this proposal was made.', 'message' => 'Applying it would overwrite that change, so it can\'t be applied. Discard it, then propose again on the current version.']) ?><?php endif; ?>
    <?php if ($removed): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'Approving removes markup from the live version:', 'message' => "{$removed}. An account that can't keep it saved the draft, which took it out. If it should stay, discard this and have someone whose account can keep it make the change on the live version."]) ?><?php endif; ?>
    <?php if ($held): ?><?= new Digitalis\Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'Held back, not applied when this is approved:', 'message' => implode('; ', $held) . '.']) ?><?php endif; ?>

    <?php if ($lines): ?>

        <p><?= esc_html(sprintf(_n('%d change against the live version.', '%d changes against the live version.', count($lines)), count($lines))) ?> <a href="<?= esc_url($original->get_edit_url()) ?>" target="_blank" rel="noopener">Open the live version</a></p>

        <table class="widefat striped lattice-proposal-changes">
            <thead><tr><th>Field</th><th>Live</th><th>Proposed</th></tr></thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <th scope="row"><?= esc_html($line['label']) ?></th>
                        <td><?= nl2br(esc_html(($line['before'] ?? '') === '' ? '∅' : $line['before'])) ?></td>
                        <td><?= nl2br(esc_html(($line['after'] ?? '') === '' ? '∅' : $line['after'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($preview): ?><p><a href="<?= esc_url($preview) ?>" target="_blank" rel="noopener">Preview the proposed version</a></p><?php endif; ?>

    <?php else: ?>

        <?= new Digitalis\Proposal_Notice(['type' => 'info', 'inline' => true, 'message' => 'This proposal matches the live version, so there is nothing to apply yet.', 'link' => ['url' => $original->get_edit_url(), 'label' => 'Open the live version']]) ?>

    <?php endif; ?>

    <?php if ($reviewer && !$decide): ?>

        <p><em>Its author hasn't submitted it for review yet.</em></p>

    <?php elseif ($decide): ?>

        <?php if ($refusal): ?><?= new Digitalis\Proposal_Notice(['type' => 'error', 'inline' => true, 'title' => 'Approving would be refused.', 'message' => $refusal]) ?><?php endif; ?>

        <?php foreach ($consequences as $consequence): ?>
            <p><strong><?= esc_html($consequence['label'] ?? $consequence['kind'] ?? '') ?></strong></p>
            <ul class="lattice-proposal-list">
                <?php foreach ((array) ($consequence['items'] ?? []) as $item): ?><li><?= esc_html(is_scalar($item) ? (string) $item : wp_json_encode($item)) ?></li><?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <p class="lattice-proposal-actions">
            <?php if ($approve_url): ?>
                <a href="<?= esc_url($approve_url) ?>" class="button button-primary" data-lattice-proposal-confirm="Apply these changes to the live version now?">Approve and apply</a>
            <?php else: ?>
                <button class="button button-primary" disabled>Approve and apply</button>
            <?php endif; ?>
            <button type="button" class="button" data-lattice-proposal-reject="<?= esc_url($reject_url) ?>">Send back with a note</button>
        </p>

    <?php endif; ?>

<?php endif; ?>
