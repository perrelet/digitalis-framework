<<?= $tag ?> <?= $id ? "id='{$id}'" : "" ?> <?= $attributes ?>>
    <?php if ($fields) foreach ($fields as $field) {
        $call = $field['field'] . "::render";
        if (is_callable($call)) call_user_func($call, $field);
    } ?>
</<?= $tag ?>>