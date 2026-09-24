<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <input type='checkbox' id='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>' <?= $i ? $once_atts : '' ?> <?= $attributes ?><?= ($s = (string) ($option_atts[$option] ?? '')) ? " {$s}" : '' ?>>
    <label for='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>'><?= $option_label ?></label>
<?php $i++;   endforeach; ?>