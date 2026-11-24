<?php if (!defined('ABSPATH')) exit; ?>
<?= $element->open() ?>
    <?php if ($label): ?><label class='field-group-label'><?= $label ?></label><?php endif; ?>
    <?php if ($fields) foreach ($fields as $field) {
        if ($field instanceof Lattice\View) {
            $field->print();
        } else {
            $field_class = Lattice\Call::get_class_name($field['field']);
            echo new $field_class($field);
        }
    } ?>
<?= $element->close() ?>