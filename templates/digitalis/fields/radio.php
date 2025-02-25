<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <input type='radio' id='<?= $id ?>-<?= $option ?>' <?= $i ? $once_atts : '' ?> <?= $attributes ?> <?= $option_atts[$option] ?? '' ?>>
    <label for='<?= $id ?>-<?= $option ?>'><?= $option_label ?></label>
<?php $i++;   endforeach; ?>