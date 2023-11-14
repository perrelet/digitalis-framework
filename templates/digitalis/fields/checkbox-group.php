<input type='hidden' name='<?= $key ?>' value='0'>
<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <label><?= $option_label ?>
        <input type='checkbox' id='<?= $id ?>' value='<?= $option ?>' name='<?= $key ?>' <?= $i ? $once_atts : '' ?> <?= $attributes ?><?= $option_atts[$option]['html'] ?? '' ?>>
        <span class='checkmark'></span>
    </label>
<?php $i++;   endforeach; ?>