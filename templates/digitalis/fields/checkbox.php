<?php if (!defined('ABSPATH')) exit; ?>
<?php if (!is_null($null_value)): ?><input type='hidden' name='<?= esc_attr($name) ?>' value='<?= esc_attr($null_value) ?>'>
<?php endif; ?><?= $element ?>
<span class='checkmark'></span>