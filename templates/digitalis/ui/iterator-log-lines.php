<?php if (!defined('ABSPATH')) exit; ?>
<?php foreach ($batch as $line) echo "<div class='log-batch'>" . esc_html((string) $line) . "</div>"; ?>
<?php foreach ($failed as $line) echo "<div class='log-error'>" . esc_html((string) $line) . "</div>"; ?>
<?php foreach (['error' => $errors, 'item' => $log] as $type => $lines) foreach (array_reverse($lines) as $line) echo "<div class='log-{$type}'>" . wp_kses((string) $line, $tags) . "</div>"; ?>
