<?php if (!defined('ABSPATH')) exit; ?>
<?php if ($items_only): ?>
    <?= $content ?>
<?php else: ?>
    <<?= $tag ?> <?= $attributes ?>><?= $content ?></<?= $tag ?>>
<?php endif; ?>