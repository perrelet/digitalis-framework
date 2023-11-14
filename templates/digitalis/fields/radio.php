<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <?php $option_id = "{$id}-{$option}"; ?>
    <input type='radio' id='<?= $option_id ?>' value='<?= $option ?>' name='<?= $key ?>' <?= $i ? $once_atts : '' ?> <?= $attributes ?><?= $option_atts[$option]['html'] ?? '' ?>>
    <label for='<?= $option_id ?>'><?= $option_label ?></label>
<?php $i++;   endforeach; ?>