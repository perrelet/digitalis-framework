<button type='<?= $type ?>' <?php if ($key) echo "id='{$id}' "; ?><?php if ($key) echo "name='{$key}' "; ?>value='<?= $value ?>' <?= $once_atts ?> <?= $attributes ?>><?= $text ?></button>