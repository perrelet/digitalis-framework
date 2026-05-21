<?php if (($level === 1) && $landmark): ?>
    <<?= $tag ?> <?= $attributes ?>>
        <ul <?= $list_attributes ?>>
            <?php foreach ($items as $item) echo $item; ?>
        </ul>
    </<?= $tag ?>>
<?php else: ?>
    <ul <?= $attributes ?>>
        <?php foreach ($items as $item) echo $item; ?>
    </ul>
<?php endif; ?>
