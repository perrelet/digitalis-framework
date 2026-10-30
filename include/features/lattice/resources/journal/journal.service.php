<?php

namespace Digitalis;

class Journal extends Service {

    const KEEP_DAYS = 730;

    const PER_PAGE = 50;

    protected function get_table () : string {

        global $wpdb;

        return Journal_Table::get_name($wpdb);

    }

    public function write (array $row) : int {

        global $wpdb;

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
        ]);

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

    public function prune () : int {

        global $wpdb;

        $days = (int) apply_filters('lattice.journal.keep_days', static::KEEP_DAYS);

        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$this->get_table()} WHERE `at` < %s", gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)));

    }

    protected function decode (array $row) : array {

        foreach (['id', 'user_id', 'version_before', 'version_after'] as $column) $row[$column] = (int) $row[$column];

        $row['undoes'] = is_null($row['undoes']) ? null : (int) $row['undoes'];
        $row['before'] = is_null($row['before']) ? null : json_decode($row['before'], true);
        $row['after']  = is_null($row['after']) ? null : json_decode($row['after'], true);

        return $row;

    }

}
