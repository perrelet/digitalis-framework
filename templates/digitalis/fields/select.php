<?= $element->open() ?>
<?php if ($options) foreach ($options as $option => $option_label): ?>
    <?php if (is_array($option_label)): ?>
        <optgroup label='<?= $option ?>'>
        <?php if ($option_label) foreach ($option_label as $sub_option => $sub_option_label): ?>
            <option value='<?= $sub_option ?>'<?= $option_atts[$sub_option] ?? '' ?>><?= $sub_option_label ?></option>
        <?php endforeach; ?>
        </optgroup>
    <?php else: ?>
        <option value='<?= $option ?>'<?= $option_atts[$option] ?? '' ?>><?= $option_label ?></option>
    <?php endif; ?>
<?php endforeach; ?>
<?= $element->close() ?>