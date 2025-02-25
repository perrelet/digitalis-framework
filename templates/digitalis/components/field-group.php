<?= $element->open() ?>
    <?php if ($label): ?><label class='field-group-label'><?= $label ?></label><?php endif; ?>
    <?php if ($fields) foreach ($fields as $field) {
        if ($field instanceof Digitalis\View) {
            $field->print();
        } else {
            Digitalis\Call::static($field['field'], 'render', $field);
        }
    } ?>
<?= $element->close() ?>