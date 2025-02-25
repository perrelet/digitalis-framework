<?php if ($name): ?>
    <input type='hidden' name='<?= $name ?>' value='<?= $null_value ?>'>
<?php endif; ?>
<?php $i = 0; foreach ($options as $option => $option_label): ?>
    <label for='<?= $id ?>-<?= $option ?>'><?= $option_label ?>
        <input id='<?= $id ?>-<?= $option ?>' <?= $i ? $once_atts : '' ?><?= $option_atts[$option] ?? '' ?> <?= $attributes ?>>
        <span class='checkmark'></span>
    </label>
<?php $i++; endforeach; ?>