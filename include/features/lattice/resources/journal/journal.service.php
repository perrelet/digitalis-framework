<?php

namespace Digitalis;

class Journal extends Service {

    const KEEP_DAYS = 730;

    const PER_PAGE = 50;

    const MARKS = ['set_key', 'set_step', 'proposer_id'];

    protected function get_table () : string {

        global $wpdb;

        return Journal_Table::get_name($wpdb);

    }

    public function write (array $row) : int {

        global $wpdb;

        if (!array_key_exists('set_key', $row)) $row += Resources::inst()->get_held_marks($row);

        $wpdb->insert($this->get_table(), [
            'at'             => gmdate('Y-m-d H:i:s'),
            'user_id'        => get_current_user_id(),
            'via'            => (string) ($row['via'] ?? 'other'),
            'resource'       => (string) $row['resource'],
            'record_id'      => (string) $row['record_id'],
            'action'         => (string) $row['action'],
            'why'            => (string) ($row['why'] ?? ''),
            'before'         => is_null($row['before'] ?? null) ? null : wp_json_encode($row['before']),
            'after'          => is_null($row['after'] ?? null) ? null : wp_json_encode($row['after']),
            'version_before' => (int) ($row['version_before'] ?? 0),
            'version_after'  => (int) ($row['version_after'] ?? 0),
            'undoes'         => $row['undoes'] ?? null,
        ] + (Journal_Schema::is_current() ? array_map(fn ($mark) => is_null($mark) ? null : (int) $mark, array_intersect_key($row, array_flip(static::MARKS))) + ['credential' => Credentials::inst()->get_uuid(), 'credential_name' => is_null($name = Credentials::inst()->get_name()) ? null : mb_substr($name, 0, 255)] : []));

        Resources::inst()->note_row((int) $wpdb->insert_id, (string) $row['action'], (string) $row['resource'], (string) $row['record_id']);

        return (int) $wpdb->insert_id;

    }

    // Only for a row written earlier in this request.
    public function amend (int $id, array $changes) : bool {

        global $wpdb;

        foreach (array_intersect_key($changes, ['before' => 0, 'after' => 0]) as $column => $value) $changes[$column] = is_null($value) ? null : wp_json_encode($value);

        return (bool) $wpdb->update($this->get_table(), $changes, ['id' => $id]);

    }

    public function find (int $id) : ?array {

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->get_table()} WHERE id = %d", $id), ARRAY_A);

        return $row ? $this->decode($row) : null;

    }

    // Newest first; `since` is a row id.
    public function query (array $args = []) : array {

        global $wpdb;

        $where = ['1 = 1'];
        $vars  = [];

        foreach (['resource', 'record_id'] as $column) if (isset($args[$column]) && $args[$column] !== '') {

            $where[] = "{$column} = %s";
            $vars[]  = (string) $args[$column];

        }

        foreach (['set' => 'set_key', 'proposer' => 'proposer_id'] as $filter => $column) if (isset($args[$filter])) {

            if (!Journal_Schema::is_current() || ((int) $args[$filter] < 1)) return [];

            $where[] = "{$column} = %d";
            $vars[]  = (int) $args[$filter];

        }

        if (!empty($args['resources'])) $where[] = 'resource IN (' . implode(', ', array_map(fn ($name) => $wpdb->prepare('%s', $name), (array) $args['resources'])) . ')';

        if (!empty($args['since'])) {

            $where[] = 'id > %d';
            $vars[]  = (int) $args['since'];

        }

        $per_page = min(static::PER_PAGE, max(1, (int) ($args['per_page'] ?? static::PER_PAGE)));
        $offset   = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;
        $sql      = "SELECT * FROM {$this->get_table()} WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}";

        return array_map([$this, 'decode'], (array) $wpdb->get_results($vars ? $wpdb->prepare($sql, ...$vars) : $sql, ARRAY_A));

    }

    // Every row of a set, oldest first: a page holds too few for a walk, and a walk's own rows would shift the pages.
    public function rows_for_set (int $key) : array {

        global $wpdb;

        if (!Journal_Schema::is_current()) return [];

        return array_map([$this, 'decode'], (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->get_table()} WHERE set_key = %d ORDER BY id ASC", $key), ARRAY_A));

    }

    // Whether a row of a step was undone, from wherever: someone undoing one by hand writes no mark of the set. A query that fails answers yes, since what depends on this stops.
    public function has_undone_rows (int $key) : bool {

        global $wpdb;

        $table = $this->get_table();
        $found = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} AS undoing JOIN {$table} AS row_undone ON undoing.undoes = row_undone.id WHERE row_undone.set_key = %d AND row_undone.set_step IS NOT NULL AND row_undone.undoes IS NULL LIMIT 1", $key));

        return !is_null($found) || ($wpdb->last_error !== '');

    }

    // The rows undoing these, the undo's own walk.
    public function undoing (array $ids) : array {

        global $wpdb;

        if (!$ids = array_values(array_filter(array_map('intval', $ids)))) return [];

        return array_map([$this, 'decode'], (array) $wpdb->get_results("SELECT * FROM {$this->get_table()} WHERE undoes IN (" . implode(', ', $ids) . ') ORDER BY id ASC', ARRAY_A));

    }

    public function prune () : int {

        global $wpdb;

        $days = (int) apply_filters('lattice.journal.keep_days', static::KEEP_DAYS);

        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$this->get_table()} WHERE `at` < %s", gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)));

    }

    protected function decode (array $row) : array {

        foreach (['id', 'user_id', 'version_before', 'version_after'] as $column) $row[$column] = (int) $row[$column];

        foreach (['undoes', ...static::MARKS] as $column) $row[$column] = is_null($row[$column] ?? null) ? null : (int) $row[$column];

        $row['before'] = is_null($row['before']) ? null : json_decode($row['before'], true);
        $row['after']  = is_null($row['after']) ? null : json_decode($row['after'], true);

        return $row;

    }

}
