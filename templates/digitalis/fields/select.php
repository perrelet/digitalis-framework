<select id='<?= $id ?>' name='<?= $key ?>' <?= $attributes ?>>
<?php if ($options) foreach ($options as $option_key => $option): ?>
    <?php if (is_array($option)): ?>
        <optgroup label='<?= $option_key ?>'>
        <?php if ($option) foreach ($option as $sub_option_key => $sub_option): ?>
            <option value='<?= $sub_option_key ?>' <?= selected($value, $sub_option_key, false) ?>><?= $sub_option ?></option>
        <?php endforeach; ?>
        </optgroup>
    <?php else: ?>
    <option value='<?= $option_key ?>' <?= selected($value, $option_key, false) ?>><?= $option ?></option>
    <?php endif; ?>
<?php endforeach; ?>
</select>