<?php use Digitalis\{Proposal, Proposal_Notice, Proposal_Set_Review}; ?>

<div class="lattice-review">

<p class="lattice-review-back"><a href="<?= esc_url(Proposal::get_queue_url()) ?>">&larr; Proposed changes</a></p>
<hr class="wp-header-end">

<?php if ($refusal): ?>

    <?= new Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => $refusal]) ?>

<?php else: ?>

    <header class="lattice-review-head">
        <h1><?= nl2br(esc_html($review['purpose'] ?: 'A set with no purpose')) ?></h1>
        <p class="lattice-review-byline">
            By <strong><?= esc_html($byline['author']) ?></strong><?php if ($byline['password']): ?>, with the password <strong><?= esc_html($byline['password']) ?></strong><?php endif; ?>
            <?php if ($byline['when']): ?><span class="lattice-review-dot">&middot;</span> offered <time title="<?= esc_attr($byline['when']) ?>"><?= esc_html($byline['ago']) ?></time><?php endif; ?>
        </p>
    </header>

    <div class="lattice-review-status is-<?= esc_attr($status['tone']) ?>">
        <strong><?= esc_html($status['label']) ?></strong>
        <?php if ($status['message']): ?><span><?= esc_html($status['message']) ?></span><?php endif; ?>
    </div>

    <?php if ($review['note'] ?? ''): ?><?= new Proposal_Notice(['type' => 'warning', 'inline' => true, 'title' => 'A reviewer asked for changes:', 'message' => $review['note']]) ?><?php endif; ?>
    <?php if ($review['as'] === 'author'): ?><p class="lattice-review-byline"><em>You see what can be said without the right to write these records. A reviewer sees each change's preview.</em></p><?php endif; ?>
    <?php if ($refused): ?><?= new Proposal_Notice(['type' => 'error', 'inline' => true, 'message' => 'Approving would be refused, for the reasons below, and you may not be able to fix that. Send it back to tell its author why.']) ?><?php endif; ?>

    <ol class="lattice-review-steps">

        <?php foreach ($steps as $step): ?>

            <li class="lattice-review-step is-<?= esc_attr($step['state']['tone']) ?>">

                <div class="lattice-review-step-head">
                    <span class="lattice-review-number"><?= (int) $step['number'] ?></span>
                    <div class="lattice-review-step-title">
                        <strong><?= esc_html($step['does']) ?></strong>
                        <span class="lattice-review-record">
                            <span class="lattice-review-type"><?= esc_html($step['type']) ?></span>
                            <?php if ($step['url']): ?><a href="<?= esc_url($step['url']) ?>"><?= esc_html($step['record']) ?></a><?php else: ?><?= esc_html($step['record']) ?><?php endif; ?>
                        </span>
                    </div>
                    <span class="lattice-review-pill is-<?= esc_attr($step['state']['tone']) ?>"><?= esc_html($step['state']['label']) ?></span>
                </div>

                <?php foreach ($step['notes'] as $note): ?>
                    <?= new Proposal_Notice(['type' => $note['type'], 'inline' => true, 'message' => $note['message']]) ?>
                <?php endforeach; ?>

                <?php if ($step['lines']): ?>
                    <div class="lattice-review-changes">
                        <?php foreach ($step['lines'] as $line): ?>
                            <div class="lattice-review-change">
                                <div class="lattice-review-field"><?= esc_html($line['label'] ?? $line['field'] ?? '') ?></div>
                                <?php if ($line['long'] && function_exists('wp_text_diff') && ($diff = wp_text_diff((string) ($line['before'] ?? ''), (string) ($line['after'] ?? ''), ['show_split_view' => true]))): ?>
                                    <div class="lattice-review-diff"><?= $diff ?></div>
                                <?php else: ?>
                                    <div class="lattice-review-values">
                                        <del class="lattice-review-before"><?= nl2br(esc_html(Proposal_Set_Review::show_cell($line['before'] ?? ''))) ?></del>
                                        <span class="lattice-review-arrow" aria-hidden="true">&rarr;</span>
                                        <ins class="lattice-review-after"><?= nl2br(esc_html(Proposal_Set_Review::show_cell($line['after'] ?? ''))) ?></ins>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step['consequences']): ?>
                    <ul class="lattice-review-follows">
                        <?php foreach ($step['consequences'] as $consequence): ?>
                            <li>
                                <?= esc_html($consequence['label']) ?>
                                <?php if ($consequence['items']): ?><span class="lattice-review-items"><?= esc_html(implode(', ', $consequence['items'])) ?></span><?php endif; ?>
                                <?php if ($consequence['extras']): ?><span class="lattice-review-extras"><?= esc_html(implode(' · ', $consequence['extras'])) ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <details class="lattice-review-details"><summary>Details</summary><pre><?= esc_html($step['details']) ?></pre></details>

            </li>

        <?php endforeach; ?>

    </ol>

    <?php if ($undo): ?>
        <section class="lattice-review-undo">
            <h2>Undoing it, newest first</h2>
            <ol>
                <?php foreach ($undo as $row): ?>
                    <li><?php if ($row['number']): ?><span class="lattice-review-type">Change <?= (int) $row['number'] ?></span> <?php endif; ?><?= esc_html($row['what']) ?><?php if ($row['note']): ?> <em><?= esc_html($row['note']) ?></em><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($forms || $reject): ?>
        <div class="lattice-review-actions">
            <?php foreach ($forms as $form): ?>
                <form method="post" action="<?= esc_url($form['url']) ?>">
                    <?php foreach ((array) ($form['fields'] ?? []) as $name => $value): ?><input type="hidden" name="<?= esc_attr($name) ?>" value="<?= esc_attr($value) ?>"><?php endforeach; ?>
                    <button type="submit" class="button button-large<?= empty($form['primary']) ? '' : ' button-primary' ?>"<?= empty($form['confirm']) ? '' : ' data-lattice-proposal-confirm="' . esc_attr($form['confirm']) . '"' ?>><?= esc_html($form['label']) ?></button>
                </form>
            <?php endforeach; ?>
            <?php if ($reject): ?><button type="button" class="button button-large" data-lattice-proposal-reject="<?= esc_url($reject) ?>">Send back with a note</button><?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>
