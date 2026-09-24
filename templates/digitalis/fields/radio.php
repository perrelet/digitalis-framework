<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <input type='radio' id='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>' <?= $i ? $once_atts : '' ?> <?= $attributes ?> <?= $option_atts[$option] ?? '' ?>>
    <label for='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>'><?= $option_label ?></label>
<?php $i++;   endforeach; ?>