<?php if ($shape === 'divider'): ?>
    <<?= $tag ?> <?= $attributes ?>></<?= $tag ?>>

<?php elseif ($shape === 'heading'): ?>
    <<?= $tag ?> <?= $attributes ?>>
        <h<?= (int) $heading_level ?>><?= esc_html($heading) ?></h<?= (int) $heading_level ?>>
    </<?= $tag ?>>

<?php else: ?>
    <<?= $tag ?> <?= $attributes ?>>
        <?= $link ?>
        <?= $button ?>
        <?= $description_el ?>
        <?php if ($has_submenu) echo $submenu; ?>
        <?= $panel ?>
    </<?= $tag ?>>
<?php endif; ?>
