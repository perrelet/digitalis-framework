<?php if (!defined('ABSPATH')) exit; ?>
<?= $element->open() ?>
    <?php if ($label): ?><label class='field-group-label'><?= $label ?></label><?php endif; ?>
    <?php if ($fields) foreach ($fields as $field) $field->print(); ?>
<?= $element->close() ?>