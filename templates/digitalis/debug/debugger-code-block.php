<?php if (!defined('ABSPATH')) exit; ?>
<div>
    <?php if ($label): ?>
        <label onclick="this.nextElementSibling.toggleAttribute(`open`);"><?= $label ?></label>
    <?php endif; ?>
    <code open><?= $code ?></code>
</div>