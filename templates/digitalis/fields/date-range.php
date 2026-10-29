<?php if (!defined('ABSPATH')) exit; ?>
<div id="<?= esc_attr($id) ?>" class="date-range">
    <?= $date_start ?>
    <div class='date-range-seperator'><?= $seperator ?></div>
    <?= $date_end ?>
</div>