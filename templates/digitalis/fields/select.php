<?= $element->open() ?>
<?php if ($options) foreach ($options as $option => $option_label): ?>
    <?php if (is_array($option_label)): ?>
        <optgroup label='<?= esc_attr($option) ?>'>
        <?php if ($option_label) foreach ($option_label as $sub_option => $sub_option_label): ?>
            <option value='<?= esc_attr($sub_option) ?>'<?= ($s = (string) ($option_atts[$sub_option] ?? '')) ? " {$s}" : '' ?>><?= esc_html($sub_option_label) ?></option>
        <?php endforeach; ?>
        </optgroup>
    <?php else: ?>
        <option value='<?= esc_attr($option) ?>'<?= ($s = (string) ($option_atts[$option] ?? '')) ? " {$s}" : '' ?>><?= esc_html($option_label) ?></option>
    <?php endif; ?>
<?php endforeach; ?>
<?= $element->close() ?>