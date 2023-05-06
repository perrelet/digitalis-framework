<select id='<?= $id ?>' name='<?= $key ?>' <?= $attributes ?>>
<?php if ($options) foreach ($options as $option => $option_label): ?>
    <option value='<?= $option ?>' <?= selected($value, $option, false) ?>><?= $option_label ?></option>
<?php endforeach; ?>
</select>