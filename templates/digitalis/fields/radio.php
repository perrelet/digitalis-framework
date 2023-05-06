<?php if ($options) foreach ($options as $option => $option_label): ?>
    <?php $option_id = "{$id}-{$option}"; ?>
    <input type='radio' id='<?= $option_id ?>' value='<?= $option ?>' name='<?= $key ?>' <?= $attributes ?> <?= checked($value, $option, false) ?>>
    <label for='<?= $option_id ?>'><?= $option_label ?></label>
<?php endforeach; ?>