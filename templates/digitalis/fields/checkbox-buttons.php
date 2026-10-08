<?php $i = 0; if ($options) foreach ($options as $option => $option_label): ?>
    <input id='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>' <?= $i ? $this->get_pre_once_attributes() : $attributes ?><?= ($s = (string) ($option_atts[$option] ?? '')) ? " {$s}" : '' ?>>
    <label for='<?= esc_attr($id) ?>-<?= esc_attr($option) ?>'><?= $option_label ?></label>
<?php $i++;   endforeach; ?>