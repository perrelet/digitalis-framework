<?php

namespace Digitalis;

require_once __DIR__ . '/resource-field.class.php';
require_once __DIR__ . '/resource-change.class.php';
require_once __DIR__ . '/model-resource.abstract.php';
require_once __DIR__ . '/options-page.model.php';
require_once __DIR__ . '/options-page-record.class.php';
require_once __DIR__ . '/options-page-resource.abstract.php';
require_once __DIR__ . '/resources.service.php';
require_once __DIR__ . '/journal/journal.db-table.php';
require_once __DIR__ . '/journal/v1-create-journal.migration.php';
require_once __DIR__ . '/journal/journal.schema.php';
require_once __DIR__ . '/journal/journal.service.php';
require_once __DIR__ . '/resources-command.class.php';
require_once __DIR__ . '/changes.sub-page.php';
require_once __DIR__ . '/routes/resource.route.php';

foreach (glob(__DIR__ . '/routes/*.route.php') as $file) require_once $file;

class Lattice_Resources extends Feature {

    const PRUNE         = 'lattice_journal_prune';
    const VERSION_FIELD = 'lattice_version';

    const ROUTES = [Resources_Guide_Route::class, Resources_Journal_Route::class, Resources_Undo_Route::class, Resources_Collection_Route::class, Resources_Record_Route::class, Resources_Member_Route::class];

    protected array $before = [];

    public function get_hooks () {

        return [
            'save_post'                                       => 'raise_version',
            'pre_post_update'                                 => 'take_before',
            'wp_after_insert_post'                            => ['settle_save', 100],
            'post_submitbox_misc_actions'                     => 'print_version',
            'admin_action_editpost'                           => 'claim_from_editor',
            'acf/save_post'                                   => ['take_page_before', 5],
            'acf/options_page/submitbox_before_major_actions' => 'print_page_version',
            'acf/options_page/save'                           => 'settle_page_save',
            'lattice.post.duplicate.exclude_meta'             => 'exclude_version',
            'lattice.relationship.partner_updated'            => 'raise_partner_version',
            'cli_init'                                        => 'register_commands',
            static::PRUNE                                     => 'prune_journal',
        ];

    }

    public function run () {

        Journal_Schema::ensure();

        if (!wp_next_scheduled(static::PRUNE)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', static::PRUNE);

        foreach (static::ROUTES as $route) $route::get_instance();

        if (is_admin()) Changes_Page::get_instance();

    }

    public function register_commands () {

        Resources_Command::register();

    }

    protected function find ($post_id) : array {

        $resource = Resources::inst()->get_resource_for_post_type((string) get_post_type($post_id));
        $model    = $resource?->find($post_id);

        return $model ? [$resource, $model] : [null, null];

    }

    // Autosave writes a draft in place; raising would refuse the editor's own save after it.
    protected function is_autosave () : bool {

        return defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;

    }

    // Preview posts the editor's form and saves one's own draft, while the form stays open.
    protected function is_preview ($post_id) : bool {

        return (($_POST['wp-preview'] ?? '') === 'dopreview') && ((int) ($_POST['post_ID'] ?? 0) === (int) $post_id);

    }

    // Version

    public function raise_version ($post_id) {

        if ($this->is_autosave() || $this->is_preview($post_id) || Resources::inst()->is_writing($post_id)) return;

        [, $model] = $this->find($post_id);

        if ($model && !$model->is_claimed()) $model->claim_version($model->get_version());

    }

    public function print_version ($wp_post) {

        [, $model] = $this->find($wp_post->ID);

        if ($model) $this->print_version_field($model);

    }

    // Before edit_post() writes anything, _edit_last included. post.php checks the nonce itself, but only after this.
    public function claim_from_editor () {

        if (!isset($_POST[static::VERSION_FIELD])) return;

        $post_id = (int) ($_POST['post_ID'] ?? 0);

        [$resource, $model] = $this->find($post_id);

        if (!$model || !wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), "update-post_{$post_id}") || !current_user_can('edit_post', $post_id)) return;

        $seen = (int) $_POST[static::VERSION_FIELD];

        if ($this->is_preview($post_id) ? ($seen === $model->get_version()) : $model->claim_version($seen)) return;

        $this->refuse_stale($resource, $post_id, $seen);

    }

    protected function print_version_field ($model) : void {

        echo '<input type="hidden" name="' . static::VERSION_FIELD . '" value="' . esc_attr($model->get_version()) . '">';

    }

    protected function refuse_stale (Model_Resource $resource, $record_id, int $seen) : void {

        $by = $this->describe_change($resource, $record_id, $seen);

        wp_die(esc_html("This {$resource->get_label()} changed after you opened it" . ($by ? " ({$by})" : '') . ', so your edit was not saved. Open it again to see the change, then make yours.'), 'Changed since you opened it', ['response' => 409, 'back_link' => true]);

    }

    // Who, how and when, from the latest journal row newer than what the form showed.
    protected function describe_change (Model_Resource $resource, $record_id, int $seen) : string {

        $row = Journal::inst()->query(['resource' => $resource->get_name(), 'record_id' => (string) $record_id, 'per_page' => 1])[0] ?? null;

        if (!$row || ($row['version_after'] <= $seen)) return '';

        $how = ['rest' => 'through the API', 'cli' => 'through WP-CLI', 'admin' => 'in wp-admin'][$row['via']] ?? '';

        return implode(', ', array_filter([get_userdata($row['user_id'])?->display_name ?: 'someone', $how, wp_date('j M H:i', strtotime("{$row['at']} UTC"))]));

    }

    // Relationship sync writes the partner's meta without saving it.
    public function raise_partner_version ($type, $id) {

        if (($type !== 'post') || Resources::inst()->is_writing($id)) return;

        [$resource, $model] = $this->find($id);

        if (!$model) return;

        $model->raise_version();
        $resource->forget_values($model);

    }

    public function exclude_version ($keys, $post) {

        return method_exists($post, 'get_version_key') ? [...$keys, $post::get_version_key()] : $keys;

    }

    // Journal

    public function take_before ($post_id) {

        if ($this->is_autosave() || Resources::inst()->is_writing()) return;

        [$resource, $model] = $this->find($post_id);

        if (!$model) return;

        $resource->forget_values($model);

        $this->before[$post_id] = ['status' => get_post_status($post_id), 'values' => $resource->read($model), 'version' => $model->get_version()];

        $resource->forget_values($model);

    }

    public function settle_save ($post_id, $wp_post, $update) {

        $before = $this->before[$post_id] ?? null;

        unset($this->before[$post_id]);

        [$resource, $model] = $this->find($post_id);

        if (!$model) return;

        if (!Resources::inst()->is_writing($post_id)) $model->finish_version();

        if ($this->is_autosave() || Resources::inst()->is_writing() || ($wp_post->post_status === 'auto-draft') || ($update && !$before)) return;

        $resource->forget_values($model);
        $model->reload();

        $created = !$update || $before['status'] === 'auto-draft';
        $after   = $resource->read($model);

        if ((!$created && $after === $before['values']) || !Resources::inst()->is_journaled($resource, $model)) return;

        Journal::inst()->write([
            'via'            => is_admin() ? 'admin' : 'other',
            'resource'       => $resource->get_name(),
            'record_id'      => $post_id,
            'action'         => $created ? 'create' : 'update',
            'before'         => $created ? null : $before['values'],
            'after'          => $after,
            'version_before' => $created ? 0 : $before['version'],
            'version_after'  => $model->get_version(),
        ]);

    }

    // Options pages

    // Every options page saves under one ACF id; wp-admin names the page.
    public function take_page_before ($post_id) {

        global $plugin_page;

        if (is_numeric($post_id) || !is_string($plugin_page) || Resources::inst()->is_writing()) return;

        $resource = Resources::inst()->get_resource_for_page($plugin_page);

        if (!$model = $resource?->find($plugin_page)) return;

        // ACF has checked the page's nonce.
        $seen = isset($_POST[static::VERSION_FIELD]) ? (int) $_POST[static::VERSION_FIELD] : $model->get_version();

        if (!$model->claim_version($seen)) $this->refuse_stale($resource, $plugin_page, $seen);

        $resource->forget_values($model);

        $this->before[$plugin_page] = ['values' => $resource->read($model), 'version' => $seen];

    }

    public function print_page_version ($page) {

        if ($model = Resources::inst()->get_resource_for_page((string) ($page['menu_slug'] ?? ''))?->find($page['menu_slug'])) $this->print_version_field($model);

    }

    public function settle_page_save ($post_id, $slug) {

        $before = $this->before[$slug] ?? null;

        unset($this->before[$slug]);

        if (!$before || !($resource = Resources::inst()->get_resource_for_page($slug)) || !($model = $resource->find($slug))) return;

        $model->finish_version();
        $resource->forget_values($model);

        if ((($after = $resource->read($model)) === $before['values']) || !Resources::inst()->is_journaled($resource, $model)) return;

        Journal::inst()->write([
            'via'            => 'admin',
            'resource'       => $resource->get_name(),
            'record_id'      => $slug,
            'action'         => 'update',
            'before'         => $before['values'],
            'after'          => $after,
            'version_before' => $before['version'],
            'version_after'  => $model->get_version(),
        ]);

    }

    public function prune_journal () {

        Journal::inst()->prune();

    }

}
