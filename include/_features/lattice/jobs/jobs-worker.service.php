<?php

namespace Lattice;

class Jobs_Worker extends Service {

    const PAUSE  = 'lattice_jobs_pause';
    const SITE   = 'lattice_jobs_site';
    const BUDGET = 45;

    const COPIED      = 'copied';
    const BY_A_PERSON = 'by a person';
    const UNINSTALLED = 'uninstalled';

    protected ?float $started = null;
    protected ?float $budget  = null;
    protected array  $waiting = [];
    protected bool   $guarded = false;

    // Budget

    public static function get_budget () : float {

        $limit  = (int) ini_get('max_execution_time');
        $spent  = microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $budget = $limit ? min(static::BUDGET, max(0, ($limit * 0.75) - $spent)) : static::BUDGET;

        return (float) apply_filters('lattice.jobs.budget', $budget);

    }

    protected function begin (?float $budget) : void {

        $this->started = microtime(true);
        $this->budget  = $budget;

    }

    protected function elapsed () : float {

        return microtime(true) - $this->started;

    }

    public function must_yield () : bool {

        return (!is_null($this->budget) && ($this->elapsed() >= $this->budget)) || $this->near_memory_limit();

    }

    protected function may_start () : bool {

        return (is_null($this->budget) || ($this->elapsed() < ($this->budget / 2))) && !$this->near_memory_limit();

    }

    protected function near_memory_limit () : bool {

        $limit = wp_convert_hr_to_bytes((string) ini_get('memory_limit'));

        return ($limit > 0) && (memory_get_usage() >= ($limit * 0.8));

    }

    // Gate

    public function is_open () : bool {

        if (!Jobs_Schema::is_current()) return false;

        $this->check_site();

        return !$this->get_pause();

    }

    public function get_pause () : ?array {

        return get_option(static::PAUSE) ?: null;

    }

    public function pause (string $why = self::BY_A_PERSON) : void {

        if (($this->get_pause()['why'] ?? '') === static::UNINSTALLED) return;

        $this->store_pause($why);

    }

    // `created_at` is compared with the database's clock.
    protected function store_pause (string $why) : void {

        global $wpdb;

        update_option(static::PAUSE, ['why' => $why, 'at' => $wpdb->get_var('SELECT UTC_TIMESTAMP()')], true);

    }

    public function resume (bool $discard = false) : int {

        global $wpdb;

        $pause     = $this->get_pause();
        $discarded = 0;

        if ($discard && $pause && Jobs_Schema::is_current()) $discarded = (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . Jobs_Table::get_name($wpdb) . " SET state = %s, ended_at = UTC_TIMESTAMP(), open = NULL, lease_token = NULL, lease_until = NULL, cancel_why = %s WHERE state = %s AND created_at <= %s",
            Job::CANCELLED, 'Discarded on resuming.', Job::PENDING, $pause['at']
        ));

        update_option(static::PAUSE, '', true);
        update_option(static::SITE, $this->get_site_hash(), true);

        return $discarded;

    }

    public function uninstall (bool $drop = false) : void {

        global $wpdb;

        $this->store_pause(static::UNINSTALLED);

        Jobs_Tick::get_instance()->unschedule_tasks();

        if (!$drop) return;

        $wpdb->query('DROP TABLE IF EXISTS ' . Jobs_Table::get_name($wpdb));

        Jobs_Schema::forget();
        delete_option(static::SITE);
        delete_option(Lattice_Jobs::LATE);

    }

    // Hashed, or a migration's search and replace would rewrite it to match.
    protected function get_site_hash () : string {

        return md5(preg_replace('#^https?://#i', '', (string) get_option('home')));

    }

    protected function check_site () : void {

        $stored = get_option(static::SITE);

        if (!$stored) {

            update_option(static::SITE, $this->get_site_hash(), true);
            add_option(static::PAUSE, '', '', true);
            return;

        }

        if (($stored !== $this->get_site_hash()) && !$this->get_pause()) $this->pause(static::COPIED);

    }

    // Entries

    public function tick () : void {

        $this->work(static::get_budget());

    }

    public function work (?float $budget) : void {

        if (!$this->is_open()) return;

        $this->begin($budget);
        $this->park_unknown();

        $refused = null;

        while ($this->may_start() && ($id = $this->choose())) {

            if (!($job = Job::get_instance($id)) || !$job->take()) {

                if ($id === $refused) return;

                $refused = $id;
                continue;

            }

            $refused = null;

            $this->run_job($job);
            $this->forget();

        }

    }

    protected function forget () : void {

        Job::forget_instances();

        if (function_exists('wp_cache_supports') && wp_cache_supports('flush_runtime')) wp_cache_flush_runtime();

    }

    public function run_named (Job $job) : bool {

        global $wpdb;

        if (!$this->is_open() || (get_class($job) === Job::class)) return false;

        $suppress = $wpdb->suppress_errors(true);
        $inside   = $wpdb->get_var('SELECT @@in_transaction');

        $wpdb->suppress_errors($suppress);

        if ($inside) return false;

        $this->begin(static::get_budget());

        if (!$job->take(true)) return false;

        $this->run_job($job);

        return true;

    }

    public function run_after_response (int $id) : void {

        if ((defined('WP_CLI') && WP_CLI) || !(function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'))) return;

        if (!$this->waiting) add_action('shutdown', [$this, 'run_waiting'], 9999);

        $this->waiting[] = $id;

    }

    public function run_waiting () : void {

        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else                                           litespeed_finish_request();

        if (!$this->is_open()) return;

        $this->begin(static::get_budget());

        foreach ($this->waiting as $id) {

            if (!$this->may_start()) return;

            if (($job = Job::get_instance($id)) && (get_class($job) !== Job::class) && $job->take()) $this->run_job($job);

            $this->forget();

        }

    }

    protected function choose () : ?int {

        global $wpdb;

        $types = array_keys(Job::get_known_types());

        if (!$types) return null;

        $in  = implode(', ', array_fill(0, count($types), '%s'));
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT id, (SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = %s) AS pause FROM ' . Jobs_Table::get_name($wpdb) . " WHERE state = %s AND run_after <= UTC_TIMESTAMP() AND " . Job::FREE . " AND type IN ({$in}) ORDER BY run_after, id LIMIT 1",
            static::PAUSE, Job::PENDING, ...$types
        ));

        return ($row && !$row->pause) ? (int) $row->id : null;

    }

    protected function run_job (Job $job) : void {

        global $wp_current_filter;

        $this->guard();

        $user    = get_current_user_id();
        $filters = $wp_current_filter;
        $level   = ob_get_level();

        wp_set_current_user(0);

        try {

            $job->run($this);

        } finally {

            // A throw through do_action leaves these behind.
            $wp_current_filter = $filters;

            while (ob_get_level() > $level) ob_end_clean();

            wp_set_current_user($user);

        }

    }

    // A fatal in a `now` job never reaches this: its lease lapses instead.
    protected function guard () : void {

        if ($this->guarded) return;

        $this->guarded = true;

        add_action('shutdown', fn () => Job::get_current()?->abandon_step(), PHP_INT_MIN);

    }

    // Upkeep

    // At the head of the scan, the type filter would walk them all.
    protected function park_unknown () : void {

        global $wpdb;

        $types = array_keys(Job::get_known_types());

        if (!$types) return;

        $table = Jobs_Table::get_name($wpdb);
        $in    = implode(', ', array_fill(0, count($types), '%s'));
        $where = 'state = %s AND run_after <= UTC_TIMESTAMP() AND ' . Job::FREE . " AND type NOT IN ({$in})";

        $found = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET state = IF(cancel_why IS NULL, %s, %s), error = IF(cancel_why IS NULL, %s, error), ended_at = UTC_TIMESTAMP(), open = NULL, lease_token = NULL, lease_until = NULL WHERE {$where} AND parked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)",
            Job::FAILED, Job::CANCELLED, 'Its class has gone.', Job::PENDING, ...$types
        ));

        if ($found) Jobs_Log::inst()->log("{$found} jobs of an unknown type ended.");

        $wpdb->query($wpdb->prepare("UPDATE {$table} SET run_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), parked_at = IFNULL(parked_at, UTC_TIMESTAMP()) WHERE {$where}", Job::PENDING, ...$types));

    }

    public function prune () : void {

        global $wpdb;

        if (!$this->is_open()) return;

        $days  = (array) apply_filters('lattice.jobs.keep_days', ['done' => 30, 'other' => 90]);
        $table = Jobs_Table::get_name($wpdb);

        foreach ([['= %s', (int) $days['done']], ['<> %s', (int) $days['other']]] as [$state, $keep]) {

            do {

                $ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT j.id FROM {$table} j LEFT JOIN {$table} f ON f.type = %s AND f.open = 1 AND f.job_key = CONCAT('job:', j.id)"
                    . " WHERE j.ended_at IS NOT NULL AND j.ended_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) AND j.state {$state} AND f.id IS NULL LIMIT 500",
                    Job_End::get_type(), $keep, Job::DONE
                ));

                if ($ids) $wpdb->query("DELETE FROM {$table} WHERE id IN (" . implode(', ', array_map('intval', $ids)) . ')');

            } while (count($ids) === 500);

        }

    }

}
