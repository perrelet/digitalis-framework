<?php if ($level == 1): ?>
    <<?= $tag ?> role="navigation" <?= $attributes ?>>
        <ul <?= $list_attributes ?>>
            <?php foreach ($items as $item) echo $item; ?>
        </ul>
        <parsed-callback></parsed-callback>
    </<?= $tag ?>>
<?php else: ?>        
    <ul <?= $attributes ?> <?= $list_attributes ?>>
        <?php foreach ($items as $item) echo $item; ?>
    </ul>
<?php endif; ?>
