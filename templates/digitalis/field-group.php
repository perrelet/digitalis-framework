<<?= $tag ?> <?= $id ? "id='{$id}'" : "" ?> <?= $attributes ?>>
    <?php if ($fields) foreach ($fields as $field) {
        call_user_func($field['field'] . "::render", $field);
    } ?>
</<?= $tag ?>>