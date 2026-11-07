<?php if (!defined('ABSPATH')) exit; ?>
<div class='<?= esc_attr($classes) ?>'>

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
        <fieldset class='run-options'<?= $cron ? ' disabled' : '' ?>>
            <label>Batch size <input type='number' data-param='batch_size' min='1' max='<?= $iterator::MAX_BATCH_SIZE ?>' step='1' value='<?= (int) $iterator->get_batch_size() ?>'></label>
            <label><input type='checkbox' data-param='dry_run' value='1'<?= !empty($store['dry_run']) ? ' checked' : '' ?>> Dry run</label>
        </fieldset>
    </div>
    <?= $after_controls ?>

    <?= $before_progress ?>
    <div class='iterator-panel progress'>
        <div class='status-bar'>
            <div class='index-total'>Progress <span class='index'><?= $index ?></span> / <span class='total'><?= $total ?? '?' ?></span></div>
            <div class='status' aria-live='polite'><?= $label ?></div>
        </div>
        <div class='progress-track' role='progressbar' aria-label='Progress' aria-valuemin='0'<?= is_null($total) ? " aria-valuetext='{$index} of unknown'" : " aria-valuenow='{$index}' aria-valuemax='{$total}'" ?>>
            <div class='progress-bar'<?= is_null($total) ? '' : " style='width: " . (100 * $iterator->get_progress()) . "%;'" ?>></div>
        </div>
        <div class='status-bar'>
            <div class='percent'><?= is_null($total) ? '' : floor(100 * $iterator->get_progress()) . '%' ?></div>
            <div class='time'><?= $time . $job ?></div>
        </div>
        <div class='status-bar'>
            <div class='counts'>
                <span class='chip count-processed'>Processed <b><?= $iterator->get_counts()['processed'] ?></b></span>
                <span class='chip count-skipped'>Skipped <b><?= $iterator->get_counts()['skipped'] ?></b></span>
                <span class='chip count-failed<?= $iterator->get_counts()['failed'] ? ' is-nonzero' : '' ?>'>Failed <b><?= $iterator->get_counts()['failed'] ?></b></span>
            </div>
            <div class='eta'><?= $eta ?></div>
        </div>
    </div>
    <?= $after_progress ?>

    <div class='iterator-panel failed-wrap<?= $failed['count'] ? '' : ' is-empty' ?>'>
        <div class='failed-head'>
            <label>Failed <span class='failed-count'><?= $failed['count'] ?></span></label>
            <div class='failed-actions'>
                <?php if ($iterator->supports_retry()): ?><button type='button' class='button' data-task='retry'>Retry failed</button><?php endif; ?>
                <button type='button' class='button' data-task='dismiss'>Dismiss</button>
            </div>
        </div>
        <ul class='failed-list'>
            <?php foreach ($failed['items'] as $item) echo "<li>" . ($item['url'] ? "<a href='" . esc_url($item['url']) . "'>#" . esc_html((string) $item['id']) . "</a>" : '#' . esc_html((string) $item['id'])) . "</li>"; ?>
            <?php if ($failed['count'] > count($failed['items'])) echo "<li class='failed-more'>and " . ($failed['count'] - count($failed['items'])) . " more</li>"; ?>
        </ul>
    </div>

    <?= $before_log ?>
    <div class='iterator-panel log-wrap'>
        <label>Batch log<?php if ($log_url) echo " <span class='log-note'>Last " . $iterator::LOG_TAIL . " lines · <a href='" . esc_url($log_url) . "'>Full log</a></span>"; ?></label>
        <div class='iterator-log' role='log' aria-label='Batch log'>
            <?php foreach (['error' => $store['errors'], 'item' => $store['log']] as $type => $lines) foreach (array_reverse($lines) as $line) echo "<div class='log-{$type}'>" . wp_kses((string) $line, $iterator::LOG_TAGS) . "</div>"; ?>
        </div>
    </div>
    <?= $after_log ?>

</div>
