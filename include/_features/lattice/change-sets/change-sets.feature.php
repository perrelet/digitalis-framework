<?php

namespace Lattice;

require_once __DIR__ . '/change-sets.db-table.php';
require_once __DIR__ . '/change-steps.db-table.php';
require_once __DIR__ . '/v1-create-change-sets.migration.php';
require_once __DIR__ . '/v2-change-sets-credential.migration.php';
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
        require_once __DIR__ . '/change-sets-cleanup.cron-scheduler.php';

        Change_Set_Resource::get_instance();
        Change_Sets_Cleanup::get_instance();

        $this->add_hooks([
            'before_delete_post'              => 'mark_broken',
            'woocommerce_before_delete_order' => 'mark_broken',
            'lattice.resources.journal_set'   => 'answer_journal_read',
            Lattice_Resources::PRUNE          => ['prune_sets', 20],
            'lattice.resources.erased'        => 'erase_account',
        ]);

    }

    public function erase_account ($resource, $user_id) {

        global $wpdb;

        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare('SELECT DISTINCT set_id FROM ' . Change_Set::steps_table() . ' WHERE resource = %s AND record_id = %s', $resource->get_name(), (string) $user_id)));

        $this->scrub_sets($this->break_open($ids, "{$resource->get_label()} {$user_id} was erased."), '_scrubbed');

    }

    // Break first, so no walk applies a placeholder; one that cannot be broken keeps its values.
    protected function break_open (array $ids, string $why) : array {

        $ready = [];

        foreach ($ids as $id) {

            try {

                if (!$set = $this->get_resource()->find($id)) continue;

                if (!in_array($set->get_state(), [...Change_Set::CLOSED, Change_Set::BROKEN], true)) $this->get_resource()->mark_broken($set, $why);

                $ready[] = $id;

            } catch (\Throwable $e) {

                error_log("Change sets: set {$id} could not be marked broken, so it keeps its values: " . $e->getMessage());

            }

        }

        return $ready;

    }

    protected function scrub_sets (array $ids, string $mark) : void {

        global $wpdb;

        if (!$ids) return;

        $in = implode(', ', array_map('intval', $ids));

        foreach ((array) $wpdb->get_results('SELECT step_id, input, proposed, expected, removed, error FROM ' . Change_Set::steps_table() . " WHERE set_id IN ({$in})", ARRAY_A) as $step) {

            $erased = [];

            foreach (['input', 'proposed', 'expected', 'removed'] as $column) $erased[$column] = is_null($step[$column]) ? null : wp_json_encode(Journal::erase(json_decode($step[$column], true)));

            $erased['error'] = is_null($step['error']) ? null : Journal::ERASED;

            $wpdb->update(Change_Set::steps_table(), $erased, ['step_id' => (int) $step['step_id']]);

        }

        $wpdb->query($wpdb->prepare('UPDATE ' . Change_Set::table() . " SET purpose = %s, note = IF(note IS NULL, NULL, %s), reason = IF(reason IS NULL, NULL, %s) WHERE set_id IN ({$in})", Journal::ERASED, Journal::ERASED, Journal::ERASED));

        foreach ($ids as $id) Journal::inst()->scrub('change_set', (string) $id, $mark);

        $wpdb->query($wpdb->prepare('UPDATE ' . Journal_Table::get_name($wpdb) . " SET why = %s WHERE set_key IN ({$in}) AND why <> ''", Journal::ERASED));

    }

    public function expire_sets () {

        global $wpdb;

        $names = array_map(fn ($resource) => $resource->get_name(), array_filter(Resources::inst()->get_resources(), fn ($resource) => $resource->is_named_only()));

        if (!$names) return;

        $days = (int) apply_filters('lattice.journal.account_values_days', Journal::ACCOUNT_VALUES_DAYS);
        $ids  = array_map('intval', (array) $wpdb->get_col($wpdb->prepare('SELECT DISTINCT sets.set_id FROM ' . Change_Set::table() . ' AS sets JOIN ' . Change_Set::steps_table() . " AS steps ON steps.set_id = sets.set_id WHERE sets.updated_at < %s AND sets.purpose <> %s AND steps.resource IN ('" . implode("', '", array_map('esc_sql', $names)) . "')", gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS), Journal::ERASED)));

        $this->scrub_sets($this->break_open($ids, 'Its values expired.'), '_expired');

    }

    // After the journal's own, so a set never outlives none of its rows. Every action raises updated_at, so its rows are older still.
    public function prune_sets () {

        try {

            $this->expire_sets();

        } catch (\Throwable $e) {

            error_log('Change sets: expiring values failed: ' . $e->getMessage());

        }


        $days = (int) apply_filters('lattice.journal.keep_days', Journal::KEEP_DAYS) + 1;

        Change_Set::delete_closed_before(gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS)));

    }

    // Proposals registers its own resource under this name, so this asks for whichever is registered.
    protected function get_resource () : Change_Set_Resource {

        return Resources::inst()->get_resource('change_set');

    }

    public function mark_broken ($post_id) {

        global $wpdb;

        $resource = Resources::inst()->get_resource_for_record($post_id);

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
