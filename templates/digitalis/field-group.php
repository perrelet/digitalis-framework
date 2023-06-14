<<?= $tag ?> <?= $id ? "id='{$id}'" : "" ?> <?= $attributes ?>>
    <?php if ($label): ?><label class='field-group-label'><?= $label ?></label><?php endif; ?>
    <?php if ($fields) foreach ($fields as $field) {
        $call = $field['field'] . "::render";
        if (is_callable($call)) call_user_func($call, $field);
    } ?>
</<?= $tag ?>>