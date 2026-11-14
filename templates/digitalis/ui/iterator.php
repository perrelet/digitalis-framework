<?php if (!defined('ABSPATH')) exit; ?>
<div class='<?= esc_attr($classes) ?>' data-lattice='iterator' data-url='<?= esc_url(admin_url('admin-ajax.php')) ?>' data-action='<?= esc_attr($iterator->get_option_key()) ?>' data-nonce='<?= esc_attr(wp_create_nonce($iterator->get_option_key())) ?>' data-state='<?= esc_attr($state) ?>' data-busy='<?= esc_attr(implode(' ', $iterator::BUSY_STATES)) ?>' data-index='<?= (int) $index ?>' data-total='<?= esc_attr((string) $total) ?>' data-labels='<?= esc_attr((string) wp_json_encode($labels)) ?>' aria-busy='<?= $busy ? 'true' : 'false' ?>'>

    <?= $before_description ?>
    <?php if ($iterator->get_description()): ?>
        <div class='iterator-panel description'><?= $iterator->get_description() ?></div>
    <?php endif; ?>
    <?= $after_description ?>

    <?= $before_controls ?>
    <div class='iterator-panel controls'>
        <button type='button' class='button button-primary' data-task='start'>Start</button>
        <button type='button' class='button' data-task='once'>Run one batch</button>
        <button type='button' class='button' data-task='background'>Run in background</button>
        <button type='button' class='button' data-task='stop'>Stop</button>
        <button type='button' class='button' data-task='reset'>Reset</button>
        <fieldset class='run-options' data-el='options'<?= $busy ? ' disabled' : '' ?>>
            <label>Batch size <input type='number' data-el='batch_size' data-param='batch_size' min='1' max='<?= $iterator::MAX_BATCH_SIZE ?>' step='1' value='<?= (int) $iterator->get_batch_size() ?>'></label>
            <label><input type='checkbox' data-el='dry_run' data-param='dry_run' value='1'<?= !empty($store['dry_run']) ? ' checked' : '' ?>> Dry run</label>
        </fieldset>
    </div>
    <?= $after_controls ?>

    <?= $before_progress ?>
    <div class='iterator-panel progress'>
        <div class='status-bar'>
            <div class='index-total'>Progress <span class='index' data-el='index'><?= $index ?></span> / <span class='total' data-el='total'><?= $total ?? esc_html($labels['unknownTotal']) ?></span></div>
            <div class='status' data-el='status' role='status'><?= esc_html($label) ?></div>
        </div>
        <p class='iterator-message' data-el='message' role='alert'></p>
        <div class='progress-track' data-el='track' role='progressbar' aria-label='Progress' aria-valuemin='0'<?= is_null($total) ? " aria-valuetext='" . esc_attr(sprintf($labels['unknownProgress'], $index)) . "'" : " aria-valuenow='{$index}' aria-valuemax='{$total}'" ?>>
            <div class='progress-bar' data-el='bar'<?= is_null($total) ? '' : " style='width: " . (100 * $iterator->get_progress()) . "%;'" ?>></div>
        </div>
        <div class='status-bar'>
            <div class='percent' data-el='percent'><?= is_null($total) ? '' : floor(100 * $iterator->get_progress()) . '%' ?></div>
            <div class='time' data-el='time'><?= $time . $job ?></div>
        </div>
        <div class='status-bar'>
            <div class='counts'>
                <span class='chip count-processed'>Processed <b data-el='count-processed'><?= $iterator->get_counts()['processed'] ?></b></span>
                <span class='chip count-skipped'>Skipped <b data-el='count-skipped'><?= $iterator->get_counts()['skipped'] ?></b></span>
                <span class='chip count-failed<?= $iterator->get_counts()['failed'] ? ' is-nonzero' : '' ?>'>Failed <b data-el='count-failed'><?= $iterator->get_counts()['failed'] ?></b></span>
            </div>
            <div class='eta' data-el='eta'><?= $eta ?></div>
        </div>
    </div>
    <?= $after_progress ?>

    <div data-el='failed' class='iterator-panel failed-wrap<?= $failed['count'] ? '' : ' is-empty' ?>'>
        <div class='failed-head'>
            <label>Failed <span class='failed-count' data-el='failed-count'><?= $failed['count'] ?></span></label>
            <div class='failed-actions'>
                <?php if ($iterator->supports_retry()): ?><button type='button' class='button' data-task='retry'>Retry failed</button><?php endif; ?>
                <button type='button' class='button' data-task='dismiss'>Dismiss</button>
            </div>
        </div>
        <ul class='failed-list' data-el='failed-list'>
            <?= new Lattice\Iterator_Failed_List(['failed' => $failed]) ?>
        </ul>
    </div>

    <?= $before_log ?>
    <div class='iterator-panel log-wrap'>
        <label>Batch log<?php if ($log_url) echo " <span class='log-note'>Last " . $iterator::LOG_TAIL . " lines · <a href='" . esc_url($log_url) . "'>Full log</a></span>"; ?></label>
        <div class='iterator-log' data-el='log' role='log' aria-label='Batch log'>
            <?= new Lattice\Iterator_Log_Lines(['errors' => $store['errors'], 'log' => $store['log'], 'tags' => $iterator::LOG_TAGS]) ?>
        </div>
    </div>
    <?= $after_log ?>

</div>
