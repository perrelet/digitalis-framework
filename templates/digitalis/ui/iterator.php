<div class='digitalis-iterator iterator iterator-<?= $iterator->get_key(); ?><?= $iterator->is_doing_cron() ? ' doing_cron running' : '' ?>'>
    <?php if ($description = $iterator->get_description()): ?>
        <div class='iterator-panel description'><?= $description ?></div>
    <?php endif; ?>    
    <div class='iterator-panel controls'>
        <button data-task='start'>Start</button>
        <button data-task='stop'>Stop</button>
        <button data-task='reset'>Reset</button>
    </div>
    <div class='iterator-panel progress'>
        <div class='status-bar'>
            <div class='index-total'>Progress: <span class='index'><?= $index ?></span> / <span class='total'><?= $total; ?></span></div>
            <div class="status"><?= $iterator->is_doing_cron() ? 'Running' : 'Ready' ?></div>
        </div>
        <div class='progress-track'>
            <div class='progress-bar' style='width: <?= $total ? (100 * $index / $total) : 0; ?>%;'></div>
        </div>
        <div class='status-bar'>
            <div class='percent'><?= $total ? floor(100 * $index / $total) : 0 ?>%</div>
            <div class='time'><?= $iterator->is_doing_cron() ? 'Cron Task' : '00:00:00' ?></div>
        </div>
    </div>

    <div class='iterator-panel log-wrap'>
        <label>Batch Log:</label>
        <div class='iterator-log'>
            <?php if ($store['errors']) echo "<div class='log-error'>" . implode("</div><div class='log-error'>", array_reverse($store['errors'])) . "</div>"; ?>
            <?php if ($store['log']) echo "<div class='log-item'>" . implode("</div><div class='log-item'>", array_reverse($store['log'])) . "</div>"; ?>
        </div>
    </div>

</div>