<?php if (!defined('ABSPATH')) exit;

$cron     = $iterator->is_doing_cron();
$progress = $iterator->get_progress();
$counts   = $iterator->get_counts();
$elapsed  = (int) floor($iterator->get_elapsed());
$started  = $iterator->get_started_at();
$eta      = ($cron && $status === 'running' && !$stalled) ? $iterator->get_eta(true) : null; // a paused page has no pace; the script estimates a live run
$clock    = sprintf('%02d:%02d:%02d', intdiv($elapsed, 3600), intdiv($elapsed, 60) % 60, $elapsed % 60);
$about    = fn ($s) => $s < 60 ? "{$s}s" : ($s < 3600 ? intdiv($s, 60) . 'm' : intdiv($s, 3600) . 'h ' . (intdiv($s, 60) % 60) . 'm');
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
            <div class='time'><?= $cron ? 'Cron task' . ($started ? ' · started ' . $iterator->format_time($started) : '') : $clock ?></div>
        </div>
        <div class='status-bar'>
            <div class='counts'>
                <span class='chip count-processed'>Processed <b><?= $counts['processed'] ?></b></span>
                <span class='chip count-skipped'>Skipped <b><?= $counts['skipped'] ?></b></span>
                <span class='chip count-failed<?= $counts['failed'] ? ' is-nonzero' : '' ?>'>Failed <b><?= $counts['failed'] ?></b></span>
            </div>
            <div class='eta'><?= is_null($eta) ? '' : 'about ' . $about($eta) . ' left' ?></div>
        </div>
    </div>
    <?= $after_progress ?>

    <?= $before_log ?>
    <div class='iterator-panel log-wrap'>
        <label>Batch log</label>
        <div class='iterator-log' role='log' aria-label='Batch log'>
            <?php foreach (['error' => $store['errors'], 'item' => $store['log']] as $type => $lines) foreach (array_reverse($lines) as $line) echo "<div class='log-{$type}'>" . wp_kses((string) $line, $iterator::LOG_TAGS) . "</div>"; ?>
        </div>
    </div>
    <?= $after_log ?>

</div>