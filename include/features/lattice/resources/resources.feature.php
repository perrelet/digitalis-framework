<?php

namespace Digitalis;

require_once __DIR__ . '/resource-field.class.php';
require_once __DIR__ . '/resource-change.class.php';
require_once __DIR__ . '/model-resource.abstract.php';
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
            'save_post'                           => 'raise_version',
            'pre_post_update'                     => 'take_before',
            'wp_after_insert_post'                => ['settle_save', 100],
            'post_submitbox_misc_actions'         => 'print_version',
            'admin_action_editpost'               => 'refuse_stale_edit',
            'lattice.post.duplicate.exclude_meta' => 'exclude_version',
            'cli_init'                            => 'register_commands',
            static::PRUNE                         => 'prune_journal',
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

    // Version

    public function raise_version ($post_id) {

        if ($this->is_autosave() || Resources::inst()->is_writing($post_id)) return;

        [, $model] = $this->find($post_id);

        $model?->claim_version($model->get_version());

    }

    public function print_version ($wp_post) {

        [, $model] = $this->find($wp_post->ID);

        if ($model) echo '<input type="hidden" name="' . static::VERSION_FIELD . '" value="' . esc_attr($model->get_version()) . '">';

    }

    public function refuse_stale_edit () {

        if (!isset($_POST[static::VERSION_FIELD])) return;

        [$resource, $model] = $this->find((int) ($_POST['post_ID'] ?? 0));

        if (!$model || (int) $_POST[static::VERSION_FIELD] === $model->get_version()) return;

        wp_die(esc_html("This {$resource->get_label()} changed after you opened it, so your edit was not saved. Open it again to see the change, then make yours."), 'Changed since you opened it', ['response' => 409, 'back_link' => true]);

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

        if (!$created && $after === $before['values']) return;

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

    public function prune_journal () {

        Journal::inst()->prune();

    }

}
