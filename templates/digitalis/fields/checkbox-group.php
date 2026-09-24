<?php if ($name): ?>
    <input type='hidden' name='<?= esc_attr($name) ?>' value='<?= esc_attr($null_value) ?>'>
<?php endif; ?>
<?php $i = 0; foreach ($options as $option => $option_label): ?>
    <label for='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>'><?= $option_label ?>
        <input id='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>' <?= $i ? "{$once_atts} " : '' ?><?= $option_atts[$option] ?? '' ?> <?= $attributes ?>>
        <span class='checkmark'></span>
    </label>
<?php $i++; endforeach; ?>