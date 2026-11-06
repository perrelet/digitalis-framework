<?php

namespace Lattice;

class Jobs_Page extends Admin_Sub_Page {

    const STATES = [Job::PENDING, Job::DONE, Job::FAILED, Job::CANCELLED, Job::EXPIRED];

    protected $parent     = 'tools.php';
    protected $slug       = 'lattice-jobs';
    protected $title      = 'Jobs';
    protected $menu_title = 'Jobs';
    protected $capability = 'manage_options';

    public function __construct () {

        parent::__construct();

        add_action("load-tools_page_{$this->slug}", [$this, 'act']);

    }

    public static function get_rows (string $state = '', int $limit = 50) : array {

        global $wpdb;

        $where = in_array($state, static::STATES, true) ? $wpdb->prepare('WHERE state = %s', $state) : '';

        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Jobs_Table::get_name($wpdb) . " {$where} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A) ?: [];

    }

    public function get_url ($blog_id = null) {

        return get_admin_url($blog_id, "tools.php?page={$this->slug}");

    }

    public function callback () {

        $state  = sanitize_key($_GET['state'] ?? '');
        $worker = Jobs_Worker::inst();
        $pause  = $worker->get_pause();

        echo '<h1>Jobs</h1>';

        if (!Jobs_Schema::is_current()) {

            echo '<p>The jobs table is not current.</p>';

            if ($pause) $this->render_worker($pause);

            return;

        }

        $this->render_worker($pause);

        echo '<p>';
        foreach (['', ...static::STATES] as $each) echo '<a href="' . esc_url(add_query_arg('state', $each, $this->get_url())) . '">' . esc_html($each ?: 'all') . '</a> · ';
        echo '</p>';

        echo '<table class="widefat striped"><thead><tr><th>#</th><th>Type</th><th>State</th><th>Progress</th><th>Attempts</th><th>Due</th><th>Last error</th><th></th></tr></thead><tbody>';

        foreach (static::get_rows($state) as $row) $this->render_row($row);

        echo '</tbody></table>';

    }

    protected function render_worker (?array $pause) : void {

        if ($pause) {

            echo '<p>Paused: ' . esc_html($pause['why']) . ' since ' . esc_html($pause['at']) . ' UTC.</p>';
            echo $this->button('resume', 0, 'Resume') . ' ' . $this->button('resume-discard', 0, 'Resume and discard what was waiting');

        } else {

            echo $this->button('pause', 0, 'Pause');

        }

        echo ' ' . $this->button('uninstall', 0, 'Uninstall') . ' ' . $this->button('uninstall-drop', 0, 'Uninstall and drop the table');

    }

    protected function render_row (array $row) : void {

        $known = isset(Job::get_known_types()[$row['type']]);

        echo '<tr>';
        echo '<td>' . (int) $row['id'] . '</td>';
        echo '<td>' . esc_html($row['type']) . ($known ? '' : ' (no class)') . '</td>';
        echo '<td>' . esc_html($row['state']) . '</td>';
        echo '<td>' . (int) $row['done'] . (is_null($row['total']) ? '' : ' of ' . (int) $row['total']) . '</td>';
        echo '<td>' . (int) $row['attempts'] . '</td>';
        echo '<td>' . esc_html($row['run_after']) . '</td>';
        echo '<td>' . esc_html($row['cancel_why'] ?? ($row['error'] ?? '')) . '</td>';
        echo '<td>';
        if ($row['state'] === Job::PENDING) echo $this->button('cancel', (int) $row['id'], 'Cancel');
        if ($row['state'] === Job::FAILED)  echo $this->button('retry', (int) $row['id'], 'Retry');
        echo '</td>';
        echo '</tr>';

    }

    protected function button (string $action, int $id, string $label) : string {

        $confirm = in_array($action, ['resume-discard', 'uninstall', 'uninstall-drop'], true) ? ' onsubmit="return confirm(\'' . esc_js($label . '?') . '\')"' : '';

        return '<form method="post" style="display:inline"' . $confirm . '>' . wp_nonce_field("lattice_jobs_{$action}_{$id}", '_wpnonce', true, false)
            . '<input type="hidden" name="jobs_action" value="' . esc_attr($action) . '"><input type="hidden" name="job" value="' . $id . '">'
            . '<button class="button">' . esc_html($label) . '</button></form>';

    }

    public function act () : void {

        if (!sanitize_key($_POST['jobs_action'] ?? '')) return;

        $this->perform();

        wp_safe_redirect($this->get_url());
        exit;

    }

    public function perform () : void {

        $action = sanitize_key($_POST['jobs_action'] ?? '');

        $id = (int) ($_POST['job'] ?? 0);

        check_admin_referer("lattice_jobs_{$action}_{$id}");

        if (!current_user_can($this->capability)) wp_die('You cannot do that.');

        $worker = Jobs_Worker::inst();
        $job    = $id ? Job::get_instance($id) : null;

        match ($action) {
            'cancel'         => $job?->cancel(),
            'retry'          => $job?->retry(),
            'pause'          => $worker->pause(),
            'resume'         => $worker->resume(),
            'resume-discard' => $worker->resume(true),
            'uninstall'      => $worker->uninstall(),
            'uninstall-drop' => $worker->uninstall(true),
            default          => null,
        };

    }

}
