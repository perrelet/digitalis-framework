<?php

namespace Digitalis;

require_once __DIR__ . '/change-sets.db-table.php';
require_once __DIR__ . '/change-steps.db-table.php';
require_once __DIR__ . '/v1-create-change-sets.migration.php';
require_once __DIR__ . '/change-sets.schema.php';

class Lattice_Change_Sets extends Feature {

    protected static $requires = ['lattice/resources'];

    public function run () {

        Change_Sets_Schema::ensure();

        // A row written before the journal is current loses its marks for good.
        if (!Change_Sets_Schema::is_current() || !Journal_Schema::is_current()) return;

        // Here and not above: a feature's file is included before its requirements load, and these extend theirs.
        require_once __DIR__ . '/change-set.model.php';
        require_once __DIR__ . '/change-set-resource.class.php';

        Change_Set_Resource::get_instance();

        $this->add_hooks([
            'before_delete_post'            => 'mark_broken',
            'lattice.resources.journal_set' => 'answer_journal_read',
        ]);

    }

    // Proposals registers its own resource under this name, so this asks for whichever is registered.
    protected function get_resource () : Change_Set_Resource {

        return Resources::inst()->get_resource('change_set');

    }

    public function mark_broken ($post_id) {

        global $wpdb;

        $resource = Resources::inst()->get_resource_for_post_type((string) get_post_type($post_id));

        if (!$resource) return;

        // WordPress's purge deletes a list in one loop: nothing here may stop it, and one set that cannot be marked must not stop the next.
        try {

            $closed = "'" . implode("', '", [...Change_Set::CLOSED, Change_Set::BROKEN]) . "'";
            $ids    = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT sets.set_id FROM ' . Change_Set::steps_table() . ' AS steps JOIN ' . Change_Set::table() . " AS sets ON sets.set_id = steps.set_id WHERE steps.resource = %s AND steps.record_id = %s AND sets.state NOT IN ({$closed})", $resource->get_name(), (string) $post_id));

        } catch (\Throwable $e) {

            error_log('Change sets: a deleted record could not find its sets: ' . $e->getMessage());

            return;

        }

        foreach ((array) $ids as $id) {

            try {

                if ($set = $this->get_resource()->find((int) $id)) $this->get_resource()->mark_broken($set, "{$resource->get_label()} {$post_id} was deleted.");

            } catch (\Throwable $e) {

                error_log("Change sets: set {$id} could not be marked broken: " . $e->getMessage());

            }

        }

    }

    // What a set's reader sees of its rows, whatever they may list: those on records its steps name. Its own they may list already.
    public function answer_journal_read ($named, $set_id) {

        $resource = $this->get_resource();
        $set      = $resource->find((int) $set_id);

        if (!$set || Resources::inst()->refuse_read($resource, $set)) return $named;

        return [...(array) $named, ...array_map(fn ($step) => "{$step['resource']}:{$step['id']}", array_filter($set->get_steps(), fn ($step) => !is_null($step['id'])))];

    }

}
