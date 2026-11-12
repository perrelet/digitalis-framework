<?php

declare(strict_types=1);

namespace Lattice;

use WP_Error;

// A held list of the layer's calls, as rows. Every read of a version or a step goes to the database, so another request's write is seen.
class Change_Set extends Model implements Step_List {

    use Has_Version, Has_Unsaved_Copies;

    const DRAFT           = 'draft';
    const SUBMITTED       = 'submitted';
    const AWAITING_REVIEW = 'awaiting_review';
    const APPLIED         = 'applied';
    const APPLIED_IN_PART = 'applied_in_part';
    const UNKNOWN         = 'unknown';
    const BROKEN          = 'broken';
    const WITHDRAWN       = 'withdrawn';

    const CLOSED = [self::APPLIED, self::WITHDRAWN];

    const TEXT_BYTES = 60000;

    protected static array $held = [];
    protected static int   $depth = 0;

    protected int    $blog   = 0;
    protected ?array $row    = null;
    protected array  $staged = [];

    public function __construct ($data = null) {

        $this->blog = get_current_blog_id();

        parent::__construct($data);

    }

    // Ids are per site, and the model cache is not.
    public static function get_instance ($data = null, $auto_resolve = null) {

        $set = parent::get_instance($data, $auto_resolve);

        if (!$set || ($set->blog === get_current_blog_id())) return $set;

        unset(self::$instances[get_class($set)][$set->id]);

        return parent::get_instance($data, $auto_resolve);

    }

    public static function table () : string {

        global $wpdb;

        return Change_Sets_Table::get_name($wpdb);

    }

    public static function steps_table () : string {

        global $wpdb;

        return Change_Steps_Table::get_name($wpdb);

    }

    public static function validate_id ($id) {

        global $wpdb;

        return is_int($id) && ($id > 0) && $wpdb->get_var($wpdb->prepare('SELECT 1 FROM ' . static::table() . ' WHERE set_id = %d', $id));

    }

    protected function build_instance ($data) {

        $this->staged = array_intersect_key((array) $data, ['purpose' => 0]);

    }

    // The row

    public function get_row () : array {

        global $wpdb;

        if ($this->is_new()) return [];

        if (!is_null($this->row)) return $this->row;

        $row = (array) $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . static::table() . ' WHERE set_id = %d', $this->get_id()), ARRAY_A);

        // A read that failed says nothing about the row, so the next one asks again.
        return ($wpdb->last_error !== '') ? $row : ($this->row = $row);

    }

    public function reload () : static {

        $this->row = null;

        return $this;

    }

    public function forget_derived () : void {

        $this->row = null;

    }

    public function get_field_id () {

        return null;

    }

    public function get_purpose () : string {

        return (string) ($this->staged['purpose'] ?? $this->get_row()['purpose'] ?? '');

    }

    public function set_purpose ($purpose) : void {

        $this->staged['purpose'] = (string) $purpose;

    }

    public function get_state () : string {

        return (string) ($this->get_row()['state'] ?? '');

    }

    public function get_author_id () : int {

        return (int) ($this->get_row()['author_id'] ?? 0);

    }

    public function get_reason () : ?string {

        return $this->get_row()['reason'] ?? null;

    }

    public function get_note () : ?string {

        return $this->get_row()['note'] ?? null;

    }

    public function get_submitted_at () : ?string {

        return $this->get_row()['submitted_at'] ?? null;

    }

    public function get_updated_at () : ?string {

        return $this->get_row()['updated_at'] ?? null;

    }

    public function has_applied_step () : bool {

        return in_array('applied', array_column($this->get_steps(), 'marker'), true);

    }

    public function is_closed () : bool {

        return in_array($this->get_state(), static::CLOSED, true);

    }

    public function insert () : object {

        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');

        if ($wpdb->insert(static::table(), ['author_id' => get_current_user_id(), 'credential' => Credentials::inst()->get_scoped_uuid(), 'purpose' => $this->get_purpose(), 'state' => static::DRAFT, 'created_at' => $now, 'updated_at' => $now]) === false) return new WP_Error('not_saved', 'The set could not be stored.');

        return static::get_instance((int) $wpdb->insert_id);

    }

    public function save () {

        global $wpdb;

        if ($this->is_new()) return $this->insert();

        if (!$this->staged) return $this;

        $purpose      = $this->staged['purpose'];
        $this->staged = [];

        // Writing what is already there changes no rows, which is no failure.
        if ($wpdb->update(static::table(), ['purpose' => $purpose, 'updated_at' => gmdate('Y-m-d H:i:s')], ['set_id' => $this->get_id()]) === false) return new WP_Error('not_saved', 'The set could not be stored.');

        return $this->reload();

    }

    // Moves the state, unless a delete has marked it broken, which cannot wait for the lock and only withdrawing closes. A closed set never moves, since a delete or a lost walk may arrive after it closed. Back to draft is taken back from review too.
    public function move_to (string $state, ?string $reason = null, array $with = []) : bool {

        global $wpdb;

        $columns = ['state' => $state, 'reason' => is_null($reason) ? null : static::fit_text($reason)] + $with + (($state === static::DRAFT) ? ['submitted_at' => null] : []);
        $values  = array_filter($columns, fn ($value) => !is_null($value));
        $writes  = array_map(fn ($column, $value) => $column . (is_null($value) ? ' = NULL' : ' = %s'), array_keys($columns), $columns);
        $stopped = in_array($state, [static::BROKEN, static::WITHDRAWN], true) ? static::CLOSED : [...static::CLOSED, static::BROKEN];

        $done = $wpdb->query($wpdb->prepare('UPDATE ' . static::table() . ' SET ' . implode(', ', $writes) . ", updated_at = UTC_TIMESTAMP() WHERE set_id = %d AND state NOT IN ('" . implode("', '", $stopped) . "')", ...[...array_values($values), $this->get_id()]));

        $this->reload();

        return $done !== false;

    }

    // move_to() answers true when broken or closed stops it, and these two must know.
    public function submit () : bool {

        return $this->move_to(static::SUBMITTED, null, ['submitted_at' => gmdate('Y-m-d H:i:s'), 'note' => null]) && ($this->get_state() === static::SUBMITTED);

    }

    public function send_back (?string $note) : bool {

        $state = $this->has_applied_step() ? static::APPLIED_IN_PART : static::DRAFT;

        return $this->move_to($state, null, ['submitted_at' => null, 'note' => static::fit_text((string) $note)]) && ($this->get_state() === $state);

    }

    // What a dead walk left: its markers are written and the state it would have written is not. Under the lock.
    public function settle_state () : void {

        $markers = array_column($this->get_steps(), 'marker');
        $state   = $this->reload()->get_state();

        if (in_array('unknown', $markers, true)) {

            if ($state !== static::UNKNOWN) $this->move_to(static::UNKNOWN);

        } elseif (($state === static::DRAFT) && in_array('applied', $markers, true)) {

            $this->move_to(static::APPLIED_IN_PART);

        }

    }

    // wpdb refuses a value it would have to change, and a refused marker would read as a lost walk.
    protected static function fit_text (string $text) : string {

        return mb_strcut((string) wp_check_invalid_utf8($text, true), 0, static::TEXT_BYTES, 'UTF-8');

    }

    // Where Has_Version keeps its count

    public function get_meta ($key) {

        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare('SELECT version FROM ' . static::table() . ' WHERE set_id = %d', $this->get_id()));

    }

    public function add_meta ($key, $value, $unique = false) {

        return $this->update_meta($key, $value, 0);

    }

    public function update_meta ($key, $value, $prev = null) {

        global $wpdb;

        $where = is_null($prev) ? '' : $wpdb->prepare(' AND version = %d', $prev);

        return (bool) $wpdb->query($wpdb->prepare('UPDATE ' . static::table() . ' SET version = %d, updated_at = UTC_TIMESTAMP() WHERE set_id = %d', $value, $this->get_id()) . $where);

    }

    public function delete_meta ($key, $value = '') {

        return $this->update_meta($key, 0);

    }

    // Steps

    public function get_key () : int {

        return (int) $this->get_id();

    }

    public function get_why () : string {

        return $this->get_purpose();

    }

    public function get_credential () : ?string {

        return $this->get_row()['credential'] ?? null;

    }

    // A set belongs to its account and the scoped password that wrote it, if one did; one written without (wp-admin, the CLI, a full password) belongs to its account as before.
    public function is_mine () : bool {

        if ($this->get_author_id() !== get_current_user_id()) return false;

        // A set written under a policy stays its password's after the policy is cleared.
        return $this->get_credential() ? ($this->get_credential() === Credentials::inst()->get_uuid()) : is_null(Credentials::inst()->get_scoped_uuid());

    }

    // The SQL that picks the current request's own sets.
    public static function where_mine () : string {

        global $wpdb;

        $scoped = Credentials::inst()->get_scoped_uuid();
        $uuid   = Credentials::inst()->get_uuid();

        return $wpdb->prepare('author_id = %d', get_current_user_id()) . ($scoped ? $wpdb->prepare(' AND credential = %s', $scoped) : ($uuid ? $wpdb->prepare(' AND (credential IS NULL OR credential = %s)', $uuid) : ' AND credential IS NULL'));

    }

    public function get_proposer_id () : int {

        return $this->get_author_id();

    }

    public function get_steps () : array {

        global $wpdb;

        $steps = [];
        $rows  = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . static::steps_table() . ' WHERE set_id = %d ORDER BY position', $this->get_id()), ARRAY_A);

        // Read as no steps, a set would apply as nothing and close.
        if ($wpdb->last_error !== '') throw new \RuntimeException('The steps of a set could not be read.');

        foreach ((array) $rows as $row) {

            $steps[(int) $row['position']] = [
                'step_id'      => (int) $row['step_id'],
                'op'           => $row['op'],
                'resource'     => $row['resource'],
                'id'           => $row['record_id'],
                'action'       => $row['action'],
                'input'        => (array) json_decode((string) $row['input'], true),
                'values'       => (array) json_decode((string) $row['proposed'], true),
                'expected'     => is_null($row['expected']) ? null : (array) json_decode($row['expected'], true),
                'removed'      => (array) json_decode((string) $row['removed'], true),
                'workspace_id' => is_null($row['workspace_id']) ? null : (int) $row['workspace_id'],
                'wait'         => (bool) $row['wait'],
                'marker'       => $row['marker'],
                'marked_at'    => $row['marked_at'],
                'journal_id'   => is_null($row['journal_id']) ? null : (int) $row['journal_id'],
                'error'        => $row['error'],
            ];

        }

        return $steps;

    }

    public function mark (int $position, string $marker, array $detail = []) : bool {

        global $wpdb;

        $row = ['marker' => $marker, 'marked_at' => gmdate('Y-m-d H:i:s'), 'journal_id' => $detail['journal_id'] ?? null, 'error' => isset($detail['error']) ? static::fit_text((string) $detail['error']) : null];

        if (array_key_exists('record_id', $detail)) $row['record_id'] = $detail['record_id'];

        return $wpdb->update(static::steps_table(), $row, ['set_id' => $this->get_id(), 'position' => $position]) !== false;

    }

    // A truthy answer commits. Nested, it joins the one under way.
    public static function transaction (callable $work) : bool {

        global $wpdb;

        if (static::$depth) return (bool) $work();

        $wpdb->query('START TRANSACTION');

        static::$depth = 1;

        try {

            $done = (bool) $work();

        } catch (\Throwable $e) {

            $wpdb->query('ROLLBACK');

            throw $e;

        } finally {

            static::$depth = 0;

        }

        if ($done && ($wpdb->query('COMMIT') !== false)) return true;

        $wpdb->query('ROLLBACK');

        return false;

    }

    // The list as given, in its order, in one transaction: a step with a step_id is that row, kept, and any row not named goes.
    public function replace_steps (array $steps) : bool {

        global $wpdb;

        $rows = [];

        foreach (array_values($steps) as $position => $step) if (is_null($rows[] = $this->to_row($step, $position))) return false;

        $kept = array_values(array_filter(array_map('intval', array_column($steps, 'step_id'))));

        return static::transaction(function () use ($wpdb, $steps, $rows, $kept) {

        // An update of no row looks like an update that changed nothing, so a kept id must be this set's, once.
        $held = array_map('intval', (array) $wpdb->get_col($wpdb->prepare('SELECT step_id FROM ' . static::steps_table() . ' WHERE set_id = %d FOR UPDATE', $this->get_id())));
        $done = ($wpdb->last_error === '') && !array_diff($kept, $held) && (count($kept) === count(array_unique($kept)));
        $done = $done && ($wpdb->query($wpdb->prepare('DELETE FROM ' . static::steps_table() . ' WHERE set_id = %d', $this->get_id()) . ($kept ? ' AND step_id NOT IN (' . implode(', ', $kept) . ')' : '')) !== false);

        foreach (array_values($steps) as $position => $step) {

            if (!$done) break;

            $done = empty($step['step_id']) ? ($wpdb->insert(static::steps_table(), $rows[$position]) !== false) : ($wpdb->update(static::steps_table(), $rows[$position], ['step_id' => (int) $step['step_id'], 'set_id' => $this->get_id()]) !== false);

        }

        return $done;

        });

    }

    // Null when a value has no JSON (PHP reads 1e999 as INF), which would otherwise be stored as nothing.
    protected function to_row (array $step, int $position) : ?array {

        $json = function ($value) use (&$bad) { if (is_null($value)) return null; $encoded = wp_json_encode($value); $bad = $bad || ($encoded === false); return $encoded; };
        $bad  = false;

        $row = [
            'set_id'       => $this->get_id(),
            'position'     => $position,
            'op'           => $step['op'],
            'resource'     => $step['resource'],
            'record_id'    => isset($step['id']) ? (string) $step['id'] : null,
            'action'       => $step['action'] ?? null,
            'input'        => $json(($step['input'] ?? []) ?: null),
            'proposed'     => $json(($step['values'] ?? []) ?: null),
            'expected'     => $json($step['expected'] ?? null),
            'removed'      => $json(($step['removed'] ?? []) ?: null),
            'workspace_id' => $step['workspace_id'] ?? null,
            'wait'         => empty($step['wait']) ? 0 : 1,
            'marker'       => $step['marker'] ?? '',
            'marked_at'    => $step['marked_at'] ?? null,
            'journal_id'   => $step['journal_id'] ?? null,
            'error'        => isset($step['error']) ? static::fit_text((string) $step['error']) : null,
        ];

        return $bad ? null : $row;

    }

    public static function delete_closed_before (string $before) : int {

        global $wpdb;

        $closed = "'" . implode("', '", static::CLOSED) . "'";

        // A set outlives its rows: the journal's own prune may have failed, or an undo come since.
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare('SELECT set_id FROM ' . static::table() . " AS sets WHERE state IN ({$closed}) AND updated_at < %s AND NOT EXISTS (SELECT 1 FROM " . Journal_Table::get_name($wpdb) . ' AS rows_of WHERE rows_of.set_key = sets.set_id)', $before)));

        if (!$ids) return 0;

        $list = implode(', ', $ids);

        return static::transaction(fn () => ($wpdb->query('DELETE FROM ' . static::steps_table() . " WHERE set_id IN ({$list})") !== false) && ($wpdb->query('DELETE FROM ' . static::table() . " WHERE set_id IN ({$list})") !== false)) ? count($ids) : 0;

    }

    // The lock

    protected function get_lock_name () : string {

        global $wpdb;

        return 'lattice_set_' . md5(DB_NAME . $wpdb->prefix . $this->get_id());

    }

    public function take_lock () : bool {

        global $wpdb;

        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $this->get_lock_name())) !== '1') return false;

        static::$held[$this->get_lock_name()] = true;

        return true;

    }

    public function release_lock () : void {

        global $wpdb;

        unset(static::$held[$this->get_lock_name()]);

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->get_lock_name()));

    }

    // GET_LOCK is re-entrant, so the database cannot refuse this request a second operation on its own set.
    public function is_held_here () : bool {

        return isset(static::$held[$this->get_lock_name()]);

    }

    public function holds_lock () : bool {

        global $wpdb;

        $holder = $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $this->get_lock_name()));
        $me     = $wpdb->get_var('SELECT CONNECTION_ID()');

        return $holder && $me && ((string) $holder === (string) $me);

    }

}
