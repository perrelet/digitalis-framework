<<?= $tag ?> <?= $attributes ?>>
    <?= $a ?>
    <?php if ($child): ?>
        <div <?= $child_wrap_attributes ?>>
            <?= $child ?>
            <?= $close_button ? $close : '' ?>
        </div>
    <?php endif; ?>
    <?php if ($fixed): ?>
        <fixed-menu-bg onclick='this.closest(`digitalis-nav`).closeAllItems();'></fixed-menu-bg>
    <?php endif; ?>
</<?= $tag ?>>