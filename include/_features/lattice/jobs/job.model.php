<?php

namespace Lattice;

use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

class Job extends Model {

    const PENDING   = 'pending';
    const DONE      = 'done';
    const FAILED    = 'failed';
    const CANCELLED = 'cancelled';
    const EXPIRED   = 'expired';

    const MIN_LEASE  = MINUTE_IN_SECONDS;
    const MIN_SNOOZE = MINUTE_IN_SECONDS;
    const KEY_LENGTH = 191;
    const FREE       = '(lease_until IS NULL OR lease_until < UTC_TIMESTAMP())';

    protected static $type       = null;
    protected static $tries      = 5;
    protected static $backoff    = [MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS];
    protected static $lease      = 5 * MINUTE_IN_SECONDS;
    protected static $expires_in = 30 * DAY_IN_SECONDS;

    protected static ?Job  $current = null;
    protected static array $types   = [];
    protected static int   $depth   = 0;
    protected static array $ended   = [];

    protected int     $blog     = 0;
    protected array   $row      = [];
    protected ?string $token    = null;
    protected bool    $stepping = false;
    protected bool    $has_next = false;
    protected         $next     = null;
    protected int     $next_done = 0;
    protected ?int    $new_total = null;
    protected array   $outcome  = [];
    protected array   $held     = [];

    // Type

    public static function get_type () : ?string {

        return static::$type;

    }

    public static function get_specificity () {

        return static::$type ? 1 : 0;

    }

    public static function static_init () {

        foreach (static::get_known_types() as $type => $class) if (static::$type && ($type === static::$type) && ($class !== static::class)) _doing_it_wrong(static::class, esc_html("{$class} already declares the job type {$type}."), '');

        parent::static_init();

    }

    public static function get_known_types () : array {

        $types = [];

        foreach (array_keys(static::$class_map[self::class] ?? []) as $class) if ($class::get_type()) $types[$class::get_type()] = $class;

        return $types;

    }

    // The base class answers for any row: a class may have gone.
    public static function validate_id ($id) {

        if (!is_int($id) || ($id < 1)) return false;

        $type = self::$types[get_current_blog_id()][$id] ??= static::read_type($id);

        if (!$type)         return false;
        if (static::$type)  return $type === static::$type;

        return static::class === self::class;

    }

    public function __construct ($data = null) {

        $this->blog = get_current_blog_id();

        parent::__construct($data);

    }

    // Ids are per site, and the model cache is not.
    public static function get_instance ($data = null, $auto_resolve = null) {

        if (is_null($data) && self::$current) return self::$current;

        $job = parent::get_instance($data, $auto_resolve);

        if (!$job || ($job->blog === get_current_blog_id())) return $job;

        unset(self::$instances[get_class($job)][$job->id]);

        return parent::get_instance($data, $auto_resolve);

    }

    public function get_id () {

        return $this->is_new ? null : $this->id;

    }

    public static function get_global_id () {

        return self::$current?->get_id();

    }

    public static function forget_instances () : void {

        self::$instances = [];
        self::$types     = [];

    }

    // Steps

    public function on_end ($state) {}

    public static function get_current () : ?Job {

        return self::$current;

    }

    public function next ($cursor, int $done = 1) : void {

        $this->assert_stepping('next');

        $json = is_null($cursor) ? false : wp_json_encode($cursor);

        $problem = match (true) {
            $json === false                      => 'next() needs a cursor that is not null and can be JSON.',
            $this->has_next                      => 'next() was called twice in one step.',
            $json === ($this->row['position'] ?? null) => 'next() was given the cursor it already has.',
            default                              => null,
        };

        if ($problem) {

            $this->outcome = $this->outcome ?: ['misuse', $problem];

            throw new LogicException($problem);

        }

        $this->has_next  = true;
        $this->next      = $json;
        $this->next_done = $done;

    }

    public function get_cursor () {

        $this->assert_saved();

        if (self::$current !== $this) $this->refresh();

        return is_null($this->row['position'] ?? null) ? null : json_decode($this->row['position'], true);

    }

    public function snooze (int $seconds) : void {

        $this->assert_stepping('snooze');

        $this->outcome = $this->outcome ?: ['snooze', max($seconds, static::MIN_SNOOZE)];

        throw new Job_Signal();

    }

    public function set_total (int $total) : void {

        $this->new_total = $total;

    }

    public function cancel (string $why = '') {

        $this->assert_saved();

        $why = $why ?: 'Cancelled.';

        if ($this->stepping && (self::$current === $this)) {

            $this->outcome = $this->outcome ?: ['cancel', $why];

            throw new Job_Signal();

        }

        for ($try = 0; $try < 10; $try++) {

            if (!$this->refresh())                        return null;
            if ($this->row['state'] !== static::PENDING)  return $this->row['state'];

            if ($this->row['leased']) {

                $this->update('cancel_why = %s', [$why], 'state = %s AND lease_until >= UTC_TIMESTAMP()', [static::PENDING]);

                if ($this->refresh() && $this->row['leased'] && !is_null($this->row['cancel_why'])) return 'requested';

                continue;

            }

            if ($this->end(static::CANCELLED, static::FREE, cancel_why: $why)) return static::CANCELLED;

        }

        return $this->refresh() ? $this->row['state'] : null;

    }

    // Dispatch

    public static function dispatch (array $args = [], $at = null, ?string $key = null, $expires = null, bool $now = false) {

        if (!static::$type)                    throw new LogicException(static::class . ' declares no $type.');
        if (strlen(static::$type) > 64)        throw new LogicException(static::class . ' declares a $type over 64 characters.');
        if (!$row = static::prepare_row($args, $at, $key, $expires)) return null;

        if (!Jobs_Schema::is_current()) return null;

        if (self::$current?->stepping) {

            $job = new static(['unsaved' => true]);

            self::$current->held[] = [static::class, $row];

            return $job;

        }

        if (!$id = static::write_row($row)) return null;

        if ($now) Jobs_Worker::inst()->run_after_response($id);

        return new static($id);

    }

    protected static function prepare_row (array $args, $at, ?string $key, $expires) : ?array {

        $json = static::encode_args($args);

        if (is_null($json)) return null;

        if (!is_null($key) && (($key === '') || (mb_strlen($key) > static::KEY_LENGTH) || (rtrim($key) !== $key))) return null;

        $at_time      = static::to_time($at);
        $expires_time = ($expires === false) ? false : static::to_time($expires);

        if (!is_null($at) && !$at_time)                          return null;
        if (!is_null($expires) && ($expires !== false) && !$expires_time) return null;
        if ($expires_time && ($expires_time <= ($at_time ?: time()))) return null;

        return [
            'blog'       => get_current_blog_id(),
            'args'       => $json,
            'key'        => $key,
            'at'         => $at_time,
            'expires'    => $expires_time,
            'expires_in' => is_null($expires) ? static::$expires_in : null,
        ];

    }

    protected static function encode_args (array $args) : ?string {

        $plain = true;

        array_walk_recursive($args, function ($value) use (&$plain) { if (is_object($value) || is_resource($value)) $plain = false; });

        return ($plain && (($json = wp_json_encode($args)) !== false)) ? $json : null;

    }

    protected static function to_time ($value) : ?int {

        if (is_int($value))                         return $value;
        if ($value instanceof \DateTimeInterface)   return $value->getTimestamp();

        return null;

    }

    protected static function write_row (array $row) : ?int {

        global $wpdb;

        if (!is_null($row['key'])) {

            $open = static::read_open($row['key']);

            if ($open && (!is_null($open['cancel_why']) || $open['past'])) {

                if ($open['leased']) return null;

                static::settle_row($open);

            } elseif ($open && !$open['leased'] && ($open['attempts'] >= static::get_tries())) {

                static::settle_row($open);

            }

        }

        $table = static::table();
        $when  = $row['at'] ? $wpdb->prepare('%s', gmdate('Y-m-d H:i:s', $row['at'])) : 'UTC_TIMESTAMP()';
        $latest = match (true) {
            is_int($row['expires'])                  => $wpdb->prepare('%s', gmdate('Y-m-d H:i:s', $row['expires'])),
            !is_null($row['expires_in'])             => $wpdb->prepare("DATE_ADD({$when}, INTERVAL %d SECOND)", $row['expires_in']),
            default                                  => 'NULL',
        };
        $key = is_null($row['key']) ? 'NULL' : $wpdb->prepare('%s', $row['key']);

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (type, args, job_key, open, state, run_after, expires_at, created_at) VALUES (%s, %s, {$key}, 1, %s, {$when}, {$latest}, UTC_TIMESTAMP())",
            static::$type, $row['args'], static::PENDING
        ) . ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), again = IF(lease_token IS NOT NULL OR position IS NOT NULL, 1, again)';

        for ($try = 1; $try <= 3; $try++) {

            if ($wpdb->query($sql) !== false) return (int) $wpdb->insert_id;
            // A deadlock has rolled back the whole transaction: it decides.
            if (self::$depth || !static::is_deadlock()) return null;

        }

        return null;

    }

    protected static function settle_row (array $row) : void {

        $job = new static((int) $row['id']);

        $job->row = $row;

        $job->settle();

    }

    // Read

    protected static function table () : string {

        global $wpdb;

        return Jobs_Table::get_name($wpdb);

    }

    protected static function read_type (int $id) : ?string {

        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SELECT type FROM ' . static::table() . ' WHERE id = %d', $id));

    }

    protected static function read_where (string $where, array $args) : ?array {

        global $wpdb;

        $sql = 'SELECT *, (lease_until IS NOT NULL AND lease_until >= UTC_TIMESTAMP()) AS leased, (expires_at IS NOT NULL AND expires_at < UTC_TIMESTAMP()) AS past, (run_after <= UTC_TIMESTAMP()) AS due,'
            . " (SELECT option_value FROM {$wpdb->options} WHERE option_name = %s) AS pause FROM " . static::table() . " WHERE {$where}";

        return $wpdb->get_row($wpdb->prepare($sql, Jobs_Worker::PAUSE, ...$args), ARRAY_A) ?: null;

    }

    protected static function read_open (string $key) : ?array {

        return static::read_where('type = %s AND job_key = %s AND open = 1', [static::$type, $key]);

    }

    public static function find (string $key) {

        if (!static::$type) throw new LogicException('find() needs a job class with a $type.');

        return ($row = static::read_open($key)) ? static::get_instance((int) $row['id']) : null;

    }

    protected function refresh () : bool {

        $this->row = static::read_where('id = %d', [$this->id]) ?? [];

        return (bool) $this->row;

    }

    public function get_state () : ?string {

        $this->assert_saved();

        return $this->refresh() ? $this->row['state'] : null;

    }

    public function is_running () : bool {

        $this->assert_saved();

        return $this->refresh() && ($this->row['state'] === static::PENDING) && $this->row['leased'];

    }

    public function get_progress () : array {

        $this->assert_saved();
        $this->refresh();

        return ['done' => (int) ($this->row['done'] ?? 0), 'total' => is_null($this->row['total'] ?? null) ? null : (int) $this->row['total']];

    }

    public function get_args () : array {

        $this->assert_saved();
        $this->refresh();

        return json_decode($this->row['args'] ?? '[]', true) ?: [];

    }

    public function get_error () : ?string {

        $this->assert_saved();
        $this->refresh();

        return $this->row['cancel_why'] ?? ($this->row['error'] ?? null);

    }

    public function get_row () : array {

        $this->assert_saved();
        $this->refresh();

        return $this->row;

    }

    // Move

    public function reschedule ($at, $expires = null) : bool {

        global $wpdb;

        $this->assert_saved();

        $at_time = static::to_time($at);

        if (!$at_time || !$this->refresh() || ($this->row['state'] !== static::PENDING) || $this->row['leased']) return false;

        $latest = ($expires === false) ? false : static::to_time($expires);

        if (!is_null($expires) && ($expires !== false) && !$latest) return false;

        $kept = is_null($expires) && !is_null($this->row['expires_at']) ? strtotime($this->row['expires_at'] . ' UTC') : null;

        if ($latest && ($latest <= max($at_time, time()))) return false;
        if ($kept && ($kept < max($at_time, time())))      return false;

        $when = ($at_time <= time()) ? 'UTC_TIMESTAMP()' : $wpdb->prepare('%s', gmdate('Y-m-d H:i:s', $at_time));
        $sets = "run_after = {$when}, lease_token = NULL, lease_until = NULL";

        if ($latest)            $sets .= $wpdb->prepare(', expires_at = %s', gmdate('Y-m-d H:i:s', $latest));
        if ($expires === false) $sets .= ', expires_at = NULL';

        $rows = $wpdb->query($wpdb->prepare('UPDATE ' . static::table() . " SET {$sets} WHERE id = %d AND state = %s AND " . static::FREE, $this->id, static::PENDING));

        if ($rows) return true;

        return ($rows !== false) && $this->refresh() && ($this->row['state'] === static::PENDING) && !$this->row['leased'];

    }

    public function retry ($expires = null) : bool {

        global $wpdb;

        $this->assert_saved();

        if (!static::$type || !$this->refresh() || ($this->row['state'] !== static::FAILED)) return false;

        $latest = ($expires === false) ? false : static::to_time($expires);

        if (!is_null($expires) && ($expires !== false) && !$latest) return false;

        if ($latest && ($latest <= time()))                                       return false;
        if (is_null($expires) && !is_null($this->row['expires_at']) && $this->row['past']) return false;

        $sets = 'state = %s, run_after = UTC_TIMESTAMP(), attempts = 0, open = 1, ended_at = NULL, cancel_why = NULL, error = NULL';

        if ($latest)            $sets .= $wpdb->prepare(', expires_at = %s', gmdate('Y-m-d H:i:s', $latest));
        if ($expires === false) $sets .= ', expires_at = NULL';

        $suppress = $wpdb->suppress_errors(true);
        $rows     = $wpdb->query($wpdb->prepare('UPDATE ' . static::table() . " SET {$sets} WHERE id = %d AND state = %s", static::PENDING, $this->id, static::FAILED));

        $wpdb->suppress_errors($suppress);

        return (bool) $rows;

    }

    // Run

    public function take (bool $waive_due = false) : bool {

        global $wpdb;

        if (!$this->refresh() || ($this->row['state'] !== static::PENDING) || $this->row['leased'] || $this->row['pause']) return false;
        if (!$waive_due && !$this->row['due'])                                                                              return false;

        if ($this->settle()) return false;

        $token = bin2hex(random_bytes(8));
        $due   = $waive_due ? '' : 'AND run_after <= UTC_TIMESTAMP() ';
        $held  = is_null($this->row['lease_token']) ? 'lease_token IS NULL' : $wpdb->prepare('lease_token = %s', $this->row['lease_token']);

        $rows = $wpdb->query($wpdb->prepare(
            'UPDATE ' . static::table() . " SET lease_token = %s, lease_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), attempts = attempts + 1, parked_at = NULL WHERE id = %d AND state = %s {$due}AND cancel_why IS NULL AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP()) AND attempts = %d AND {$held} AND " . static::FREE,
            $token, static::get_lease(), $this->id, static::PENDING, (int) $this->row['attempts']
        ));

        if ($rows !== 1) return false;

        $this->token = $token;

        return $this->refresh();

    }

    protected function settle () : bool {

        if (!is_null($this->row['cancel_why']))            return $this->end(static::CANCELLED, static::FREE . ' AND cancel_why IS NOT NULL');
        if ($this->row['past'])                            return $this->end(static::EXPIRED, static::FREE . ' AND expires_at < UTC_TIMESTAMP()');
        if ($this->row['attempts'] >= static::get_tries()) return $this->end(static::FAILED, static::FREE . ' AND attempts = ' . (int) $this->row['attempts'], error: $this->row['error'] ?: 'The worker died.');

        return false;

    }

    public function run (Jobs_Worker $worker) : void {

        self::$current = $this;

        try {

            $this->run_steps($worker);

        } finally {

            self::$current = null;
            $this->token   = null;

        }

    }

    protected function run_steps (Jobs_Worker $worker) : void {

        try {

            $args = $this->bind();

        } catch (Throwable $e) {

            $this->failed_attempt($e->getMessage());
            return;

        }

        if (is_null($args)) {

            $this->end(static::FAILED, $this->owned(), error: static::class . ' cannot be called with these arguments.');
            return;

        }

        do {

            $failure = $this->step($args);

        } while ($this->advance($failure, $worker));

    }

    protected function step (array $args) : ?string {

        global $wpdb;

        $failure = null;
        $depth   = count($GLOBALS['_wp_switched_stack'] ?? []);

        $this->has_next = false;
        $this->outcome  = [];
        $this->held     = [];
        $this->new_total = null;
        $this->stepping = true;

        try {

            call_user_func_array([$this, 'handle'], $args);

        } catch (Job_Signal) {

        } catch (Throwable $e) {

            $failure = get_class($e) . ': ' . $e->getMessage();

        } finally {

            $this->stepping = false;

        }

        while (count($GLOBALS['_wp_switched_stack'] ?? []) > $depth) restore_current_blog();

        $wpdb->query('ROLLBACK');

        return $failure;

    }

    protected function advance (?string $failure, Jobs_Worker $worker) : bool {

        [$outcome, $value] = $this->outcome + [null, null];

        if ($outcome === 'cancel') {

            $this->end(static::CANCELLED, $this->owned(), cancel_why: $value);
            return false;

        }

        if ($outcome === 'snooze') {

            $this->wait_or_cancel('run_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), attempts = GREATEST(attempts - 1, 0), lease_token = NULL, lease_until = NULL', [static::jitter($value)]);
            return false;

        }

        if ($outcome === 'misuse') $failure = $value;

        if (!is_null($failure)) {

            $this->failed_attempt($failure);
            return false;

        }

        if (!$this->has_next) {

            $this->finish();
            return false;

        }

        if (!$this->commit_step()) return false;
        if (!$this->refresh())     return false;

        if (!is_null($this->row['cancel_why'])) {

            $this->end(static::CANCELLED, $this->owned());
            return false;

        }

        if ($this->row['past']) {

            $this->end(static::EXPIRED, $this->owned());
            return false;

        }

        if ($this->row['pause'] || $worker->must_yield()) {

            $this->update('run_after = UTC_TIMESTAMP(), lease_token = NULL, lease_until = NULL', [], $this->owned(), []);
            return false;

        }

        return true;

    }

    // Held dispatches write after the update, so a job's own key needs no rule.
    protected function commit_step () : bool {

        global $wpdb;

        $token = bin2hex(random_bytes(8));
        $total = is_null($this->new_total) ? '' : $wpdb->prepare(', total = %d', $this->new_total);
        $sql   = $wpdb->prepare(
            'UPDATE ' . static::table() . " SET position = %s, done = done + %d{$total}, lease_token = %s, lease_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), attempts = 0 WHERE id = %d AND lease_token = %s",
            $this->next, $this->next_done, $token, static::get_lease(), $this->id, $this->token
        );

        if (!$this->held) {

            $result = $wpdb->query($sql);

        } else {

            $result = static::transaction(function () use ($wpdb, $sql) {

                $rows = $wpdb->query($sql);

                return $rows ? $this->write_held() : $rows;

            });

        }

        if ($result === false) {

            $this->failed_attempt($wpdb->last_error ?: 'The step could not be saved.');
            return false;

        }

        if (!$result) return false;

        $this->token     = $token;
        $this->new_total = null;
        $this->held      = [];

        return true;

    }

    protected function write_held () : bool {

        foreach ($this->held as [$class, $row]) {

            $elsewhere = $row['blog'] !== get_current_blog_id();

            if ($elsewhere) switch_to_blog($row['blog']);

            $written = $class::write_row($row);

            if ($elsewhere) restore_current_blog();

            if (!$written) return false;

        }

        return true;

    }

    protected function finish () : void {

        global $wpdb;

        $total  = is_null($this->new_total) ? '' : $wpdb->prepare(', total = %d', $this->new_total);
        $result = static::transaction(function () use ($wpdb, $total) {

            $rows = $this->write_end(static::DONE, $this->owned() . ' AND (again = 0 OR cancel_why IS NOT NULL)', $total);

            if ($rows === 0) $rows = $wpdb->query($wpdb->prepare(
                'UPDATE ' . static::table() . ' SET run_after = UTC_TIMESTAMP(), position = NULL, done = 0, total = NULL, attempts = 0, again = 0, lease_token = NULL, lease_until = NULL WHERE id = %d AND ' . $this->owned() . ' AND again = 1',
                $this->id
            ));

            return $rows ? $this->write_held() : $rows;

        });

        if ($result === false) {

            $this->failed_attempt($wpdb->last_error ?: 'The end could not be saved.');
            return;

        }

        if ($result && $this->refresh() && ($this->row['state'] === static::DONE)) {

            if (!is_null($this->row['total']) && ((int) $this->row['done'] < (int) $this->row['total'])) Jobs_Log::inst()->log(sprintf('Job #%d (%s) finished at %d of %d.', $this->id, static::$type, $this->row['done'], $this->row['total']));

            static::announce($this, static::DONE);

        }

    }

    public function abandon_step () : void {

        global $wpdb;

        if (!$this->stepping) return;

        $this->stepping = false;

        if ($this->blog !== get_current_blog_id()) switch_to_blog($this->blog);

        $wpdb->query('ROLLBACK');

        $last = error_get_last();

        $this->failed_attempt(($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) ? $last['message'] : 'Exited without returning.');

        self::$current = null;

    }

    protected function failed_attempt (string $error) : void {

        if (!$this->refresh()) return;

        if (!is_null($this->row['cancel_why'])) {

            $this->end(static::CANCELLED, $this->owned());
            return;

        }

        if ($this->row['attempts'] >= static::get_tries()) {

            $this->end(static::FAILED, $this->owned(), error: $error);
            return;

        }

        $this->log_failure($error);

        $this->wait_or_cancel('error = %s, run_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), lease_token = NULL, lease_until = NULL', [mb_substr($error, 0, 2000), static::jitter($this->get_delay())]);

    }

    protected function log_failure (string $error) : void {

        Jobs_Log::inst()->log(sprintf('Job #%d (%s): %s', $this->id, static::$type, $error));

    }

    protected function wait_or_cancel (string $sets, array $args) : void {

        $rows = $this->update($sets, $args, $this->owned() . ' AND cancel_why IS NULL', []);

        if (!$rows && $this->refresh() && ($this->row['lease_token'] === $this->token) && !is_null($this->row['cancel_why'])) $this->end(static::CANCELLED, $this->owned());

    }

    // End

    protected function end (string $state, string $condition, ?string $cancel_why = null, ?string $error = null) : bool {

        $sets = ($cancel_why ? $GLOBALS['wpdb']->prepare(', cancel_why = %s', $cancel_why) : '') . ($error ? $GLOBALS['wpdb']->prepare(', error = %s', mb_substr($error, 0, 2000)) : '');

        $done = static::transaction(fn () => $this->write_end($state, $condition, $sets));

        if (!$done) return false;

        if ($error) $this->log_failure($error);

        static::announce($this, $state);

        return true;

    }

    protected static function announce (Job $job, string $state) : void {

        if (self::$depth) {

            self::$ended[] = [$job, $state];
            return;

        }

        try {

            do_action('lattice.jobs.ended', $job, $state);

        } catch (Throwable $e) {

            Jobs_Log::inst()->log('lattice.jobs.ended: ' . $e->getMessage());

        }

    }

    protected function write_end (string $state, string $condition, string $sets = '') {

        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare(
            'UPDATE ' . static::table() . " SET state = %s, ended_at = UTC_TIMESTAMP(), open = NULL, lease_token = NULL, lease_until = NULL{$sets} WHERE id = %d AND state = %s AND {$condition}",
            $state, $this->id, static::PENDING
        ));

        if (!$rows) return $rows;

        return static::overrides_on_end() ? Job_End::open_for($this->id, $state) : true;

    }

    protected static function overrides_on_end () : bool {

        return (new ReflectionMethod(static::class, 'on_end'))->class !== self::class;

    }

    protected function owned () : string {

        return is_null($this->token) ? '0 = 1' : $GLOBALS['wpdb']->prepare('lease_token = %s', $this->token);

    }

    protected function update (string $sets, array $set_args, string $where, array $where_args) {

        global $wpdb;

        return $wpdb->query($wpdb->prepare('UPDATE ' . static::table() . " SET {$sets} WHERE id = %d AND {$where}", ...[...$set_args, $this->id, ...$where_args]));

    }

    // A truthy answer commits. Nested, it joins the one under way.
    protected static function transaction (callable $work, int $tries = 3) {

        global $wpdb;

        if (self::$depth) return $work();

        for ($try = 1; ; $try++) {

            $wpdb->query('START TRANSACTION');

            self::$depth = 1;

            try {

                $result = $work();

            } catch (Throwable $e) {

                self::$ended = [];

                $wpdb->query('ROLLBACK');

                throw $e;

            } finally {

                self::$depth = 0;

            }

            if ($result && ($wpdb->query('COMMIT') !== false)) {

                [$ended, self::$ended] = [self::$ended, []];

                foreach ($ended as [$job, $state]) static::announce($job, $state);

                return $result;

            }

            $deadlock = static::is_deadlock();
            $error    = $wpdb->last_error;

            self::$ended = [];

            $wpdb->query('ROLLBACK');

            if (!$deadlock || ($try >= $tries)) {

                $wpdb->last_error = $error;

                return $result ? false : $result;

            }

        }

    }

    protected static function is_deadlock () : bool {

        return stripos($GLOBALS['wpdb']->last_error, 'deadlock') !== false;

    }

    // Settings

    protected static function get_tries () : int {

        return max(1, (int) static::$tries);

    }

    protected static function get_lease () : int {

        return max(static::MIN_LEASE, (int) static::$lease);

    }

    protected function get_delay () : int {

        $delays = array_values((array) static::$backoff) ?: [MINUTE_IN_SECONDS];

        return (int) $delays[min(max((int) $this->row['attempts'], 1) - 1, count($delays) - 1)];

    }

    protected static function jitter (int $seconds) : int {

        $spread = (int) round($seconds / 10);

        return max(1, $seconds + ($spread ? random_int(-$spread, $spread) : 0));

    }

    // Bind

    // Not the trait: it sends a missing argument to get_instance(null), the global subject.
    protected function bind () : ?array {

        if (!method_exists($this, 'handle') || !(new ReflectionMethod($this, 'handle'))->isPublic()) return null;

        $stored = $this->get_args();
        $args   = [];

        foreach ((new ReflectionMethod($this, 'handle'))->getParameters() as $param) {

            $name = $param->getName();

            if (is_null($stored[$name] ?? null)) {

                if (!$param->isDefaultValueAvailable()) return null;

                $args[$name] = $param->getDefaultValue();
                continue;

            }

            $type  = $param->getType();
            $class = (($type instanceof ReflectionNamedType) && !$type->isBuiltin()) ? $type->getName() : null;

            if ($class && method_exists($class, 'get_instance')) {

                $args[$name] = $class::get_instance($stored[$name]);

                if (!$args[$name]) throw new RuntimeException("No {$class} with the id {$stored[$name]}.");

                continue;

            }

            $args[$name] = $stored[$name];

        }

        return $args;

    }

    // Guards

    public function run_one () : bool {

        $this->assert_saved();

        if (self::$current?->stepping) throw new LogicException('run_one() cannot be called inside handle().');

        return Jobs_Worker::inst()->run_named($this);

    }

    protected function assert_stepping (string $method) : void {

        if (!$this->stepping || (self::$current !== $this)) throw new LogicException("{$method}() only works inside the job's own handle().");

    }

    protected function assert_saved () : void {

        if ($this->is_new)                            throw new LogicException('This job is held by its step and has no row yet.');
        if ($this->blog !== get_current_blog_id())    throw new LogicException('This job belongs to another site.');

    }

}
