<?php if (!defined('ABSPATH')) exit;

$cron     = $iterator->is_doing_cron();
$progress = $iterator->get_progress();
$labels   = ['halted' => 'Halted', 'complete' => 'Complete', 'running' => 'Paused'];
$states   = ['halted' => ['is-halted', 'err'], 'complete' => ['is-complete'], 'running' => ['is-paused']];

// The cron flag outranks the record (a UI run the Stop button interrupted stays running in the store); a stall outranks the flag.
$label   = $stalled ? 'Stalled' : ($cron ? 'Running' : ($labels[$status] ?? 'Ready'));
$classes = ['digitalis-iterator', 'iterator', 'iterator-' . $iterator->get_key()];

if ($cron)              $classes = array_merge($classes, ['doing_cron', 'running']);
if ($stalled)           $classes[] = 'is-stalled';
elseif (!$cron)         $classes = array_merge($classes, $states[$status] ?? []);
if (is_null($total))    $classes[] = 'is-indeterminate';

?>
<div class='<?= esc_attr(implode(' ', $classes)) ?>'>

    <?= $before_description ?>
    <?php if ($description = $iterator->get_description()): ?>
        <div class='iterator-panel description'><?= $description ?></div>
    <?php endif; ?>
    <?= $after_description ?>

    <?= $before_controls ?>
    <div class='iterator-panel controls'>
        <button type='button' class='button button-primary' data-task='start'>Start</button>
        <button type='button' class='button' data-task='stop'>Stop</button>
        <button type='button' class='button' data-task='reset'>Reset</button>
    </div>
    <?= $after_controls ?>

    <?= $before_progress ?>
    <div class='iterator-panel progress'>
        <div class='status-bar'>
            <div class='index-total'>Progress <span class='index'><?= $index ?></span> / <span class='total'><?= $total ?? '?' ?></span></div>
            <div class='status' aria-live='polite'><?= $label ?></div>
        </div>
        <div class='progress-track' role='progressbar' aria-label='Progress' aria-valuemin='0'<?= is_null($total) ? " aria-valuetext='{$index} of unknown'" : " aria-valuenow='{$index}' aria-valuemax='{$total}'" ?>>
            <div class='progress-bar'<?= is_null($total) ? '' : " style='width: " . (100 * $progress) . "%;'" ?>></div>
        </div>
        <div class='status-bar'>
            <div class='percent'><?= is_null($total) ? '' : floor(100 * $progress) . '%' ?></div>
            <div class='time'><?= $cron ? 'Cron Task' : '00:00:00' ?></div>
        </div>
    </div>
    <?= $after_progress ?>

    <?= $before_log ?>
    <div class='iterator-panel log-wrap'>
        <label>Batch log</label>
        <div class='iterator-log' role='log' aria-label='Batch log'>
            <?php if ($store['errors']) echo "<div class='log-error'>" . implode("</div><div class='log-error'>", array_reverse($store['errors'])) . "</div>"; ?>
            <?php if ($store['log']) echo "<div class='log-item'>" . implode("</div><div class='log-item'>", array_reverse($store['log'])) . "</div>"; ?>
        </div>
    </div>
    <?= $after_log ?>

</div>