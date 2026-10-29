<?php

declare(strict_types=1);

namespace Lattice;

class Changes_Page extends Admin_Sub_Page {

    protected $parent     = 'tools.php';
    protected $slug       = 'lattice-changes';
    protected $title      = 'Changes';
    protected $menu_title = 'Changes';
    protected $capability = 'manage_options';

    public function get_url ($blog_id = null) {

        return get_admin_url($blog_id, "tools.php?page={$this->slug}");

    }

    public function callback () {

        $filters = array_filter(['resource' => sanitize_key($_GET['resource'] ?? ''), 'record_id' => sanitize_text_field(wp_unslash($_GET['record'] ?? ''))]);
        $paged   = max(1, (int) ($_GET['paged'] ?? 1));
        $rows    = Journal::inst()->query($filters + ['page' => $paged]);

        echo '<h1>Changes</h1>';

        if ($filters) echo '<p>' . esc_html(implode(' ', $filters)) . ' · <a href="' . esc_url($this->get_url()) . '">All changes</a></p>';

        echo '<table class="widefat striped"><thead><tr><th>When</th><th>Who</th><th>Through</th><th>Record</th><th>What</th><th>Why</th><th>Changes</th></tr></thead><tbody>';

        foreach ($rows as $row) $this->render_row($row);

        if (!$rows) echo '<tr><td colspan="7">No changes.</td></tr>';

        echo '</tbody></table>';

        $this->render_paging($filters, $paged, count($rows) === Journal::PER_PAGE);

    }

    protected function render_row (array $row) : void {

        $user     = get_userdata($row['user_id']);
        $resource = Resources::inst()->get_resource($row['resource']);
        $model    = $resource?->find($row['record_id']);
        $record   = $model ? '<a href="' . esc_url($resource->get_edit_url($model)) . '">' . esc_html($resource->summarise($model)['title'] ?: "#{$row['record_id']}") . '</a>' : esc_html("#{$row['record_id']}");
        $history  = '<a href="' . esc_url(add_query_arg(['resource' => $row['resource'], 'record' => $row['record_id']], $this->get_url())) . '">history</a>';
        $lines    = $resource ? $resource->present_changes($row['before'] ?? [], $row['after'] ?? [], $model) : Resources::inst()->diff($row['before'] ?? [], $row['after'] ?? []);
        $changes  = array_map(fn ($line) => esc_html(($line['label'] ?? $line['field']) . ': ' . (($line['before'] ?? '') === '' ? '∅' : $line['before']) . ' → ' . (($line['after'] ?? '') === '' ? '∅' : $line['after'])), $lines);

        echo '<tr>';
        echo '<td>' . esc_html(get_date_from_gmt($row['at'], 'Y-m-d H:i')) . '</td>';
        echo '<td>' . esc_html($user ? $user->display_name : '—') . '</td>';
        echo '<td>' . esc_html($row['via']) . '</td>';
        echo '<td>' . esc_html($resource?->get_label() ?? $row['resource']) . ' ' . $record . ' (' . $history . ')</td>';
        echo '<td>' . esc_html($row['action'] . ($row['undoes'] ? " of #{$row['undoes']}" : '')) . '</td>';
        echo '<td>' . esc_html($row['why']) . '</td>';
        echo '<td>' . implode('<br>', $changes) . '</td>';
        echo '</tr>';

    }

    protected function render_paging (array $filters, int $paged, bool $more) : void {

        $link = fn ($page, $label) => '<a class="button" href="' . esc_url(add_query_arg($filters + ['paged' => $page], $this->get_url())) . '">' . $label . '</a>';

        echo '<p>' . ($paged > 1 ? $link($paged - 1, 'Newer') . ' ' : '') . ($more ? $link($paged + 1, 'Older') : '') . '</p>';

    }

}
