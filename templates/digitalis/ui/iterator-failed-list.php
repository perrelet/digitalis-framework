<?php if (!defined('ABSPATH')) exit; ?>
<?php foreach ($failed['items'] as $item) echo "<li>" . ($item['url'] ? "<a href='" . esc_url($item['url']) . "'>#" . esc_html((string) $item['id']) . "</a>" : '#' . esc_html((string) $item['id'])) . "</li>"; ?>
<?php if ($failed['count'] > count($failed['items'])) echo "<li class='failed-more'>and " . ($failed['count'] - count($failed['items'])) . " more</li>"; ?>
