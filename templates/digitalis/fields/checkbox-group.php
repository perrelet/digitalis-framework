<?php if ($options) foreach ($options as $option => $option_label): ?>
    <label><?= $option_label ?>
        <input type='hidden' name='<?= $key ?>' value='0'>
        <input type='checkbox' id='<?= $id ?>' value='1' name='<?= $key ?>' <?= $attributes ?> <?= $value ? ' checked' : '' ?>>
        <span class='checkmark'></span>
    </label>
<?php endforeach; ?>