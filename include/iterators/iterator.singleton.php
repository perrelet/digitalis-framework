<?php

declare(strict_types=1);

namespace Lattice;

use DateTime;
use DateTimeZone;

abstract class Iterator extends Singleton {

    use Dependency_Injection;

    protected $title              = 'Iterator';
    protected $key                = 'digitalis_iterator';
    protected $batch_size         = 1;
    protected $capability         = 'administrator';
    protected $menu_slug          = null;
    protected $parent_menu_slug   = 'tools.php';
    protected $timezone           = 'UCT';
    protected $description        = false;

    // Opt in: a file name, and the run is appended to it line by line while the record keeps the last LOG_TAIL lines.
    protected $log_file           = null;
    protected $log_directory      = null;
  
    protected $halt_on_fail       = false;
    protected $dynamic_total      = false;
    protected $print_results      = false;
  
    protected $cron               = false;
    protected $cron_time          = '00:00:00';
    protected $cron_schedule      = 'daily';
    protected $cron_loop_schedule = 'every_minute';
    protected $cron_budget        = 20; // seconds of batches per loop fire

    protected $cron_run_action  = false;
    protected $cron_loop_action = false;

    // The run lock outlives its last tick by this many seconds; null derives the window from max_execution_time (get_lock_window()).
    protected $lock_window = null;

    protected $ui_options = [];

    protected $labels = [
        'single'    => 'item',
        'plural'    => 'items',
    ];

    const LOG_TAIL       = 200;
    const MAX_BATCH_SIZE = 1000;

    const LOCK_STALE           = 120; // the least a lock outlives its last tick; a batch runs under max_execution_time, so that plus 30 when larger
    const LOCK_STALE_UNLIMITED = 600; // the window when max_execution_time is 0 (a system cron running php wp-cron.php has no limit)

    // What a log line may carry: a consumer colours a span, nothing scripts. The prefix is built after the filter.
    const LOG_TAGS = [
        'span'   => ['style' => true],
        'b'      => [], 'strong' => [], 'em' => [], 'i' => [], 'code' => [], 'br' => [],
        'a'      => ['href' => true],
    ];

    protected $default_store = [
        'index'     => 0,
        'processed' => [],
        'skip'      => [],
        'skipped'   => [],
        'failed'    => [],
        'retry'     => [],
        'log'       => [],
        'errors'    => [],
        'status'    => 'idle',
        'stalled'   => false,
        'started_at'  => null,
        'updated_at'  => null,
        'finished_at' => null,
        'elapsed'     => 0.0,
    ];

    const FAILED_LIST = 50; // ids the page lists; the rest is a count

    protected $items = [];
    protected $retrying = false;
    protected $token = null; // this request's lock token: taken here, adopted by the cron loop, or sent with an iterate request
    protected $store;
    protected $index;
    protected $item_id;

    protected $log = [];
    protected $errors = [];
    protected $buffer = [];       // every line since the last store write, appended to the file in one write per tick
    protected $log_handle = false; // false until resolved, then ?Log

    public function get_items () { return []; }

    public function process_item ($item) {

        return true;
    
    }

    // null is "unknown": the bar runs indeterminate and the run completes when a batch answers nothing.
    public function get_total_items () {

        return null;

    }

    public function get_item_id ($item) {

        return $this->index;

    }

    public function cron_condition () {
    
        return $this->cron;
    
    }

    //

    public function __construct () {

        add_action('admin_menu', [$this, 'add_admin_page'], 99);
        add_action('admin_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        add_action("wp_ajax_iterator_{$this->key}", [$this, 'ajax']);

        // The loop is every iterator's, since Run in background hands any run to it; the scheduled start stays a $cron iterator's.
        // Core registers no every_minute schedule; a site's own 60-second one under that name is kept.
        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_minute'] ??= ['interval' => 60, 'display' => 'Every Minute'];
            return $schedules;
        });

        add_action($this->get_loop_action(), [$this, 'cron_loop']);

        if ($this->cron_condition()) {

            $date_string = (new DateTime)->format('Y-m-d') . ' ' . $this->cron_time;
            $date_time   = DateTime::createFromFormat('Y-m-d H:i:s', $date_string, new DateTimeZone($this->get_timezone()));

            if (!preg_match(Cron_Scheduler::TIME_PATTERN, (string) $this->cron_time) || !$date_time) {

                Strict::violation(static::class, "declares \$cron_time '{$this->cron_time}', which is not an H:i:s time.", "Declare \$cron_time as 'H:i:s', '02:00:00' for example.");
                Strict::log_once(static::class, "\$cron_time '{$this->cron_time}' is not an H:i:s time; nothing is scheduled.");
                return;

            }

            $cron_action_start = $this->get_start_action();
            $cron_action_loop  = $this->get_loop_action();

            add_action($cron_action_start, [$this, 'cron_start']);

            if (!wp_next_scheduled($cron_action_start)) {

                // If today's slot has already passed, push to tomorrow — otherwise wp_schedule_event
                // accepts the past timestamp and WP-cron fires it immediately on the next poll, locking
                // the first run to "now" instead of the intended time.
                if ($date_time->getTimestamp() < time()) $date_time->modify('+1 day');

                $this->schedule_event($date_time->getTimestamp(), $this->cron_schedule, $cron_action_start);

            }

            if (!wp_next_scheduled($cron_action_loop)) $this->schedule_event(time(), $this->cron_loop_schedule, $cron_action_loop);

        }

    }

    public function get_start_action () : string { return $this->get_option_key() . '_start'; }
    public function get_loop_action ()  : string { return $this->get_option_key() . '_loop';  }

    // An unregistered schedule is a declaration error: core answers false, nothing fires, and a run would do one batch and stop.
    protected function schedule_event ($timestamp, $schedule, $action) {

        if (!isset(wp_get_schedules()[$schedule])) {

            Strict::violation(static::class, "schedules '{$action}' on '{$schedule}', which no cron_schedules filter registers.", "Declare a registered schedule, or register '{$schedule}' with a cron_schedules filter.");
            Strict::log_once(static::class, "schedule '{$schedule}' is not registered; '{$action}' is not scheduled.");
            return false;

        }

        if (wp_schedule_event($timestamp, $schedule, $action) !== true) {

            error_log('Lattice: ' . static::class . ": '{$action}' was not scheduled on '{$schedule}' (a pre_schedule_event veto or a WP_Error).");
            return false;

        }

        return true;

    }

    public static function strict_audit (string $class) {

        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) return;

        $defaults = $reflection->getDefaultProperties();
        $labels   = is_array($defaults['labels'] ?? null) ? $defaults['labels'] : [];

        foreach (['single', 'plural'] as $key) {

            if (!is_string($labels[$key] ?? null) || $labels[$key] === '') Strict::violation($class, "declares \$labels without a string '{$key}'.", "Declare \$labels with string 'single' and 'plural' keys.");

        }

        $cron_time = $defaults['cron_time'] ?? null;

        if (!is_string($cron_time) || !preg_match(Cron_Scheduler::TIME_PATTERN, $cron_time)) Strict::violation($class, "declares \$cron_time " . var_export($cron_time, true) . ", which is not an H:i:s time.", "Declare \$cron_time as 'H:i:s', '02:00:00' for example.");

    }

    //

    public function ajax () {

        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', "iterator_{$this->key}")) wp_send_json_error('Iterator Expired.', 401);
        if (!current_user_can($this->capability))                          wp_send_json_error('Unauthorized Access.', 401);
        if (!isset($_REQUEST['task']))                                     wp_send_json_error('Task parameter is required.', 422);

        $this->token = is_string($_REQUEST['token'] ?? null) ? sanitize_key($_REQUEST['token']) : null;

        // A run opens by taking the lock (total, retry) and the token comes back; every iterate carries it, and a mismatch is 409.
        switch ($_REQUEST['task']) {

            case "total":
                if (($token = $this->take_lock('ui')) === false) wp_send_json($this->lock_refusal(), 409);
                wp_send_json(['total' => $this->get_total_items_wrap(), 'token' => $token]);

            case "iterate":
                if (!$this->holds_lock()) wp_send_json($this->lock_refusal(), 409);
                $this->read_request($_REQUEST);
                wp_send_json($this->iterate());

            case "stop":
                wp_send_json($this->stop());

            case "background":
                $handover = $this->background();
                wp_send_json($handover, empty($handover['refused']) ? 200 : 409);

            case "reset":
                $reset = $this->reset();
                wp_send_json($reset, empty($reset['refused']) ? 200 : 409);

            case "stop_cron":
                wp_send_json($this->stop_cron());
            case "retry":
                if (!$this->supports_retry()) wp_send_json_error('This iterator cannot serve items by id.', 422);
                if (($token = $this->take_lock('ui')) === false) wp_send_json($this->lock_refusal(), 409);
                wp_send_json(['token' => $token] + $this->retry());
            case "dismiss":
                if (!$this->check_lock()) wp_send_json($this->lock_refusal(), 409);
                wp_send_json($this->dismiss());

            default:
                wp_send_json_error('Unknown task.', 422);

        }

    }

    // The lock is taken before the reset: a reset under a live UI run would be written back over by its next tick.
    public function cron_start () {

        if ($this->take_lock('cron') === false) {

            $message = 'Cron start refused: ' . $this->lock_refusal()['message'];
            $this->mark("--- {$message} ---");
            error_log('Lattice: ' . static::class . ": {$message}");

            return;

        }

        $this->reset_progress();
        $this->iterate();

    }

    // Run in background: the run continues from where it stands under the loop, the lock held as cron (cron_start() keeps its reset
    // for the scheduled start). WP-cron fires on traffic: a quiet site wants a system cron hitting wp-cron.php.
    public function background () {

        if ($this->get_status() === 'complete') return ['refused' => true, 'status' => 'complete', 'run_by' => null, 'message' => 'The run is complete; Reset first.'];
        if ($this->take_lock('cron') === false) return $this->lock_refusal() + ['refused' => true];

        if (!wp_next_scheduled($this->get_loop_action())) $this->schedule_event(time(), $this->cron_loop_schedule, $this->get_loop_action());

        $this->mark('--- handed to the background loop ---');

        return ['status' => $this->get_status(), 'doing_cron' => true, 'next' => wp_next_scheduled($this->get_loop_action())];

    }

    // One fire does up to $cron_budget seconds of batches. A second row, iterator_{key} fire, holds the next fire off while this
    // one runs (WP-cron's own lock lasts 60 s, and a budget plus a long batch crosses it), stale after the lock window so a fire
    // that died clears itself. The loop adopts the cron run's token, stale or not: a dead fire leaves its lock and the next
    // continues the run. When the run has ended, or the lock is no longer a cron one, a non-cron iterator unschedules its loop.
    public function cron_loop () {

        $this->get_store(); // migrates a pre-upgrade cron flag into the lock
        $lock = $this->read_lock();

        if (!$lock || $lock['run_by'] !== 'cron') {

            if (!$this->cron_condition()) wp_unschedule_hook($this->get_loop_action());
            return;

        }

        if (!$this->take_fire()) return;

        $this->token       = $lock['run'];
        $this->lock_window = (int) ($lock['window'] ?? $this->get_lock_window()); // adopted with the token, so the mid-batch touch keeps the taker's window
        $until             = microtime(true) + $this->cron_budget;

        try {

            do {

                $tick = $this->iterate();

            } while (empty($tick['refused']) && $this->get_status() === 'running' && empty($this->store['stalled']) && microtime(true) < $until);

        } finally {

            $this->release_fire();

        }

        if ($this->get_status() !== 'running' && !$this->cron_condition()) wp_unschedule_hook($this->get_loop_action());

    }

    protected function take_fire () : bool {

        $name = $this->get_option_key('fire');
        $fire = ['taken_at' => time()];

        if ($this->row_insert($name, $fire)) return true;

        $held = $this->row_read($name);

        if (!$held) return $this->row_insert($name, $fire);
        if (time() - (int) ($held['taken_at'] ?? 0) <= $this->get_lock_window()) return false;

        return $this->row_swap($name, $fire, $held);

    }

    protected function release_fire () : void {

        $this->row_delete($this->get_option_key('fire'));

    }

    //

    public function iterate ($return_store = false) {

        $results = [
            'processed' => [],
            'failed'    => [],
            'skipped'   => [],
        ];

        $tick = microtime(true);

        $this->warm_up();

        // A live lock that is not this request's refuses the tick; none at all runs it bare (a direct call). A held lock is touched here
        // as well as at the write, so a long batch is seen as alive while it runs.
        if (!$this->check_lock(true)) return $return_store ? $this->store : $this->lock_refusal() + ['refused' => true];

        // A retry tick serves the queued ids and leaves the index, the status and the run's own tests alone.
        $this->retrying = is_array($this->store['retry'] ?? null) && $this->store['retry'];

        if ($this->retrying) {

            $ids = array_splice($this->store['retry'], 0, (int) $this->batch_size);
            $this->items = $this->get_items_by_id($ids) ?? [];

        } else {

            // Before get_items(): a source that fills itself in get_total_items() is read fresh by the batch that follows.
            if (!$this->has_total()) $this->refresh_total();

            $this->items = $this->get_items();

            if ($this->index == 0 && $this->get_status() === 'idle') {

                $this->store['started_at'] = time();
                $this->store['elapsed']    = 0.0;
                $this->on_start();

            }

            $this->store['status']      = 'running';
            $this->store['finished_at'] = null;

        }

        $touched = time();
        $half    = intdiv($this->get_lock_window(), 2); // a batch longer than the window would read as stale mid-way

        if ($this->items) foreach ($this->items as $item) {

            if (time() - $touched >= $half) { $this->check_lock(true); $touched = time(); }

            $this->item_id = $this->get_item_id($item);
            if (is_null($this->item_id)) $this->item_id = $this->index;

            if (in_array($this->item_id, $this->store['skip'])) {

                $results['skipped'][] = $this->item_id;

            } else {

                if ($this->process_item($item) !== false) {

                    $results['processed'][] = $this->item_id;
    
                } else {
    
                    $results['failed'][] = $this->item_id;
    
                }

            }

            if (!$this->retrying) $this->index++;

        }

        $count = 0;
        foreach ($results as $result) $count += count($result);

        if ($this->retrying) {

            $this->move_retried($results);

        } else {

            foreach ($results as $key => $result) $this->store[$key] = array_merge(is_array($this->store[$key] ?? null) ? $this->store[$key] : [], $result);

        }

        $this->on_batch_complete($count, $results);

        if ($this->retrying) return $this->finish_tick($tick, $results, $count, $return_store);

        if ($this->dynamic_total) $this->refresh_total();

        $total     = $this->get_total();
        $remaining = is_null($total) ? null : $total - $this->index;
        $halt      = $this->halt_on_fail && $results['failed'];
        $failed    = count($results['failed']) . " {$this->labels['plural']} failed";

        // Complete wins over halted: the UI's Start refuses an index at the total, so a run halted on its last batch could never resume.
        if ((!$this->items && ($remaining ?? 0) <= 0) || (!is_null($total) && $this->index >= $total)) {

            if (is_null($total)) $this->store['total'] = $this->index;

            $this->store['status']  = 'complete';
            $this->store['stalled'] = false;

            if ($halt) $this->error("{$failed}.", true);

            $this->store['finished_at'] = time();
            $this->on_complete();

        } elseif ($halt) {

            $this->store['status']      = 'halted';
            $this->store['stalled']     = false;
            $this->store['finished_at'] = time();

            $this->error("Halted: {$failed}.", true);

        } elseif (!$this->items) {

            // Items remain and the source answered none: the run stays running, retries every tick and logs once per stall.
            if (empty($this->store['stalled'])) $this->error("No {$this->labels['plural']} returned with {$remaining} remaining; the run resumes when they return.", true);

            $this->store['stalled'] = true;

        } else {

            $this->store['stalled'] = false;

        }

        return $this->finish_tick($tick, $results, $count, $return_store);

    }

    protected function finish_tick ($tick, $results, $count, $return_store) {

        $this->store['updated_at'] = time();
        $this->store['elapsed']    = round($this->get_elapsed() + (microtime(true) - $tick), 3);

        $this->update_store();

        if (!$this->retrying && in_array($this->store['status'], ['complete', 'halted'], true) && $this->holds_lock()) $this->release_lock(); // after the write that records the end; a retry chain ends through stop

        if ($return_store) {

            return $this->store;

        } else {

            $response = [
                'index'           => $this->index,
                'results'         => $results,
                'count'           => $count,
                'counts'          => $this->get_counts(),
                'failed'          => $this->get_failed_items(),
                'log'             => $this->log,
                'errors'          => $this->errors,
                'status'          => $this->get_status(),
                'stalled'         => !empty($this->store['stalled']),
                'total'           => $this->get_total(),
                'elapsed'         => $this->get_elapsed(),
                'retrying'        => $this->retrying,
                'retry_remaining' => is_array($this->store['retry'] ?? null) ? count($this->store['retry']) : 0,
            ];

            return $response;

        }

    }

    // A retry tick moves ids instead of merging them: a success leaves the failed list for processed once, a failure stays
    // once, an id on the skip list leaves the failed list and is reported skipped.
    protected function move_retried (array $results) {

        $gone = array_merge($results['processed'], $results['skipped']);

        foreach (['failed', 'processed', 'skipped'] as $key) {

            $have = is_array($this->store[$key] ?? null) ? $this->store[$key] : [];
            if ($key === 'failed') $have = array_diff($have, $gone);

            // Only the incoming ids are deduplicated: a run that met an id twice keeps its own record.
            $this->store[$key] = array_values(array_merge($have, array_diff(array_unique($results[$key]), $have)));

        }

    }

    public function is_retry () {

        return $this->retrying;

    }

    // Opt-in: a subclass that can serve items by id implements this (and a stable get_item_id()); the base cannot.
    public function get_items_by_id (array $ids) {

        return null;

    }

    public function get_item_url ($id) {

        return null;

    }

    // Both methods must be the subclass's: the base id is the index, which a retry tick freezes, so every retried item
    // would carry one id and the record could never clear.
    public function supports_retry () {

        foreach (['get_items_by_id', 'get_item_id'] as $method) {

            if ((new \ReflectionMethod($this, $method))->getDeclaringClass()->getName() === self::class) return false;

        }

        return true;

    }

    public function get_failed_items () {

        $failed = $this->get_store()['failed'] ?? null;
        $failed = is_array($failed) ? array_values($failed) : [];
        $items  = [];

        foreach (array_slice($failed, 0, (int) static::FAILED_LIST) as $id) $items[] = ['id' => $id, 'url' => $this->get_item_url($id)];

        return ['count' => count($failed), 'items' => $items];

    }

    public function retry () {

        $failed = $this->get_store()['failed'] ?? null;

        $this->store['retry'] = is_array($failed) ? array_values(array_unique($failed)) : [];
        update_option($this->get_option_key(), $this->store, false);

        return ['retry_remaining' => count($this->store['retry']), 'failed' => $this->get_failed_items(), 'counts' => $this->get_counts()];

    }

    public function dismiss () {

        $this->get_store();

        $this->store['failed'] = [];
        $this->store['retry']  = [];
        update_option($this->get_option_key(), $this->store, false);

        return ['failed' => $this->get_failed_items(), 'counts' => $this->get_counts()];

    }

    public function on_start () {
    
        $this->log('Starting process.', true);
    
    }

    protected function on_batch_complete ($count, $results) {

        if ($this->batch_size > 1) {

            $this->log("Batch complete - {$count} {$this->labels['plural']} processed (" . count($results['skipped']) . " skipped, "  . count($results['failed']) . " failed)", true);

        }

    }

    protected function on_complete () {

        $this->log('Process complete 🚀', true);

    }

    //

    public function get_menu_slug () {
    
        return $this->menu_slug ? $this->menu_slug : $this->get_option_key();
    
    }

    public function add_admin_page () {

        add_submenu_page(
            $this->parent_menu_slug,
            $this->title,
            $this->title,
            $this->capability,
            $this->get_menu_slug(),
            [$this, 'render_admin_page']
        );

    }

    public function render_admin_page () {

        $this->warm_up();

        echo "<div class='wrap'>";
            echo "<h1>{$this->title}</h1>";
            echo "<div class='digitalis-iterator-wrap'>";
                $this->render_controller();
            echo "</div>";
        echo "</div>";

    }

    public function get_ui_options () {

        return $this->ui_options;

    }

    // The iterator's own screen (the CSV intake state included) gets the stylesheet in the head; a host rendering the controller
    // elsewhere gets it from the footer. The script stays with the controller: it prints in the footer either way and must not
    // load without the params the controller localizes.
    public function maybe_enqueue_assets () {

        if (($_GET['page'] ?? 0) == $this->get_menu_slug()) $this->enqueue_style();

    }

    public function enqueue_style () {

        wp_enqueue_style('lattice-tokens',   LATTICE_URI . 'assets/css/admin-tokens.css', [], LATTICE_VERSION);

        wp_enqueue_style('lattice-iterator', LATTICE_URI . 'assets/css/iterator.css', ['lattice-tokens'], LATTICE_VERSION);

    }

    public function render_controller () {

        $this->enqueue_style();
        wp_enqueue_script('lattice-iterator', LATTICE_URI . 'assets/js/iterator.js', [], LATTICE_VERSION, true);
        wp_localize_script('lattice-iterator', 'iterator_params', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce("iterator_{$this->key}"),
            'key'           => $this->key,
            'index'         => $this->index,
            'print_results' => $this->print_results,
            'doing_cron'    => $this->is_doing_cron(),
            'elapsed'       => $this->get_elapsed(),
            'labels'        => $this->labels,
        ]);

        $ui_options = $this->get_ui_options();
        $ui_options['iterator'] = $this;

        echo "<div class='lattice'>"; // the token scope: admin-tokens.css styles nothing outside it

        Iterator_UI::render($ui_options);

        echo "</div>";

    }

    //

    public function warm_up () {

        $this->get_store();
        $this->index = (int) $this->store['index'];

    }

    public function get_progress () {
    
        $total = $this->get_total_option();

        if (!$total) return $this->get_status() === 'complete' ? 1.0 : 0.0;

        return min(1.0, (int) $this->get_index() / $total);
    
    }

    // The run's figures: counts derive from the id arrays, the times are the record's or null for a run that predates them.
    public function get_counts () {

        $store = $this->get_store();

        return [
            'processed' => is_array($store['processed'] ?? null) ? count($store['processed']) : 0,
            'skipped'   => is_array($store['skipped'] ?? null)   ? count($store['skipped'])   : 0,
            'failed'    => is_array($store['failed'] ?? null)    ? count($store['failed'])    : 0,
        ];

    }

    public function get_elapsed () : float {

        $elapsed = $this->get_store()['elapsed'] ?? 0;

        return is_numeric($elapsed) ? (float) $elapsed : 0.0;

    }

    public function get_started_at () : ?int {

        $at = $this->get_store()['started_at'] ?? null;

        return is_int($at) ? $at : null;

    }

    public function get_updated_at () : ?int {

        $at = $this->get_store()['updated_at'] ?? null;

        return is_int($at) ? $at : null;

    }

    public function get_finished_at () : ?int {

        $at = $this->get_store()['finished_at'] ?? null;

        return is_int($at) ? $at : null;

    }

    // Seconds remaining at the run's pace: server time per item, or for a cron run, whose pace is the scheduler's gap between
    // ticks rather than the work inside them, the wall time from the start to the last tick (the record, not the clock, so the
    // page reads the same figure until the next tick). Null until a batch has run against a known total.
    public function get_eta ($wall = false) : ?int {

        $total = $this->get_total();
        $index = $this->get_index();
        $since = $this->get_started_at();
        $until = $this->get_updated_at();
        $spent = $wall ? (is_int($since) && is_int($until) ? (float) ($until - $since) : 0.0) : $this->get_elapsed();

        if (is_null($total) || $index <= 0 || $index >= $total || $spent <= 0) return null;

        return (int) round(($total - $index) * $spent / $index);

    }

    public function format_time (int $timestamp, string $format = 'H:i') {

        return (new DateTime('@' . $timestamp))->setTimezone(new DateTimeZone((string) $this->get_timezone()))->format($format);

    }

    public function get_status () {

        $status = $this->get_store()['status'] ?? null;

        return is_string($status) ? $status : 'idle';

    }

    // The run record: `total` absent = never computed, null = computed and unknown, int = known.
    public function has_total () {

        return array_key_exists('total', $this->get_store());

    }

    public function get_total () {

        $total = $this->get_store()['total'] ?? null;

        return is_int($total) ? $total : null;

    }

    // Writes bare rather than through update_store(), which would store a null $index from the AJAX total task and re-merge this request's log.
    public function refresh_total () {

        $this->get_index();

        $this->store['total'] = $this->normalise_total($this->get_total_items());

        update_option($this->get_option_key(), $this->store, false);

        return $this->store['total'];

    }

    protected function normalise_total ($total) {

        return is_numeric($total) ? (int) $total : null;

    }

    // The base property's keys and the keys get_default_store() declares are per-run; anything else in the store (a CSV
    // upload, a consumer's settings) survives.
    public function reset_progress () {

        $this->get_store();

        $this->store = array_merge($this->store, $this->default_store, $this->get_default_store());
        $this->index = 0;
        unset($this->store['total']);

        update_option($this->get_option_key(), $this->store, false);
        $this->mark('--- reset ---');

        return $this->store;

    }

    public function is_complete () {

        return $this->get_status() === 'complete';

    }

    public function get_option_key ($suffix = false) : string {

        $key = "iterator_" . $this->key;
        if ($suffix) $key .= ' ' . $suffix;

        return $key;

    }

    // Start's first call: a retry queue a stopped chain left behind would otherwise be drained at a frozen index.
    public function get_total_items_wrap () {

        $this->get_store();
        $this->store['retry'] = [];

        return $this->refresh_total();

    }

    public function get_default_store () {

        return $this->default_store;

    }

    public function get_store () {

        if (is_null($this->store)) {

            $store = get_option($this->get_option_key(), $this->get_default_store());
            if (!is_array($store) || !array_key_exists('index', $store)) $store = $this->get_default_store();

            $this->migrate_store($store);

            $this->store = $store;
            $this->pre_get_store($this->store);

        }

        return $this->store;

    }

    public function update_store () {

        $this->get_store();

        $this->store['index']  = $this->index;
        $this->store['log']    = array_slice(array_merge($this->store['log'], $this->log), -(int) static::LOG_TAIL);
        $this->store['errors'] = array_slice(array_merge($this->store['errors'], $this->errors), -(int) static::LOG_TAIL);

        // The write race closes here: a request that lost the lock while in flight discards its snapshot. Its lines still go to the
        // file, since the items' side effects happened; a takeover reprocesses the batch from the winner's index.
        if (!$this->check_lock(true)) {

            $this->error($this->lock_refusal()['message'] . ' This batch is not recorded.', true);
            $this->mark('--- discarded: lock lost ---');
            $this->flush_buffer();

            return false;

        }

        $this->flush_buffer();

        $this->pre_update_store($this->store);

        update_option($this->get_option_key(), $this->store, false);

        return true;

    }

    // The total lived in its own autoloaded option until the run record; a store without a status key predates it.
    protected function migrate_store (array &$store) {

        $legacy = get_option($this->get_option_key('total'), false);
        if ($legacy !== false) delete_option($this->get_option_key('total'));

        // The cron flag lived in its own option until the run lock; a true flag at upgrade is a cron run in progress, taken as its lock.
        $cron = get_option($this->get_option_key('cron'), null);

        if ($cron !== null) {

            delete_option($this->get_option_key('cron'));

            if ($cron) { $token = $this->token; $this->take_lock('cron'); $this->token = $token; }

        }

        if (array_key_exists('status', $store)) return;

        $index = (int) $store['index'];
        $total = $legacy === false ? null : $this->normalise_total($legacy);

        // A legacy 0 at index 0 is not a count: iterate() once wrote the index as the total and reset() never cleared it.
        if ($legacy !== false && !($index === 0 && $total === 0)) $store['total'] = $total;

        if ($index === 0)                                   $store['status'] = 'idle';
        elseif (is_int($total) && $index >= $total)         $store['status'] = 'complete';
        else                                                $store['status'] = 'running';

        $store['stalled'] = false;

        update_option($this->get_option_key(), $store, false);

    }

    public function pre_get_store (&$store) {}
    public function pre_update_store (&$store) {}

    public function get_index () {
    
        if (is_null($this->index)) $this->warm_up();

        return $this->index;
    
    }

    public function get_total_option () {

        return $this->has_total() ? $this->get_total() : $this->refresh_total();
    
    }

    public function set_total_option ($total) {

        $this->get_index();

        $this->store['total'] = $this->normalise_total($total);

        update_option($this->get_option_key(), $this->store, false);
    
    }

    // One truth: a cron run is the lock held as cron and not stale.
    public function is_doing_cron () {

        $this->get_store();
        $lock = $this->read_lock();

        return $lock !== null && $lock['run_by'] === 'cron' && !$this->is_lock_stale($lock);

    }

    // Deprecated: the lock is the flag. True takes it as cron (refused under a live run), false releases it.
    public function set_doing_cron ($state) {

        if ($state) $this->take_lock('cron'); else $this->release_lock();

    }

    //

    // The run lock is its own row, iterator_{key} lock: {run, run_by, taken_at, seen_at}, taken over only when stale (the row helpers below).
    public function take_lock (string $run_by, bool $retry = true) {

        $token = bin2hex(random_bytes(8));
        $now   = time();
        $lock  = ['run' => $token, 'run_by' => $run_by, 'taken_at' => $now, 'seen_at' => $now, 'window' => $this->get_lock_window()];

        if ($this->row_insert($this->get_lock_name(), $lock)) return $this->token = $token;

        $held = $this->read_lock();

        if (!$held) return $retry ? $this->take_lock($run_by, false) : false; // released between the insert and the read
        if (!$this->is_lock_stale($held) || !$this->row_swap($this->get_lock_name(), $lock, $held)) return false;

        $this->mark("--- took over a stale {$held['run_by']} lock, last seen " . ($now - $held['seen_at']) . "s ago ---");

        return $this->token = $token;

    }

    public function release_lock () : void {

        $this->row_delete($this->get_lock_name());
        $this->token = null; // released, this request is bare again

    }

    public function read_lock () : ?array {

        $lock = $this->row_read($this->get_lock_name());

        if (!$lock || !is_string($lock['run'] ?? null) || !is_string($lock['run_by'] ?? null)) return null;

        $lock['taken_at'] = (int) ($lock['taken_at'] ?? 0);
        $lock['seen_at']  = (int) ($lock['seen_at'] ?? 0);

        return $lock;

    }

    // Whether this request may drive the run: it holds the lock, or nobody does (a stale lock is nobody's). A held lock is touched on
    // request. No row is free for a request that never took one (a direct call); a driver whose lock was deleted under it has lost it.
    public function check_lock (bool $touch = false) : bool {

        $lock = $this->read_lock();

        if (!$lock) return $this->token === null;

        if ($this->token !== null && $lock['run'] === $this->token) {

            if ($touch) $this->row_swap($this->get_lock_name(), array_replace($lock, ['seen_at' => time()]), $lock);

            return true;

        }

        return $this->is_lock_stale($lock);

    }

    public function holds_lock () : bool {

        $lock = $this->read_lock();

        return $this->token !== null && $lock !== null && $lock['run'] === $this->token;

    }

    // The page's read: who holds the run and whether the lock is stale, never the token.
    public function get_lock () : ?array {

        if (!$lock = $this->read_lock()) return null;

        return ['run_by' => $lock['run_by'], 'taken_at' => $lock['taken_at'], 'seen_at' => $lock['seen_at'], 'stale' => $this->is_lock_stale($lock)];

    }

    public function is_lock_stale (array $lock) : bool {

        return time() - (int) ($lock['seen_at'] ?? 0) > (int) ($lock['window'] ?? $this->get_lock_window()); // the taker's window: a CLI fire and an FPM page would not agree

    }

    // The window must outlast a batch, or a live batch is taken over mid-flight.
    public function get_lock_window () : int {

        if (is_int($this->lock_window) && $this->lock_window > 0) return $this->lock_window;

        $limit = (int) ini_get('max_execution_time');

        return $limit > 0 ? max((int) static::LOCK_STALE, $limit + 30) : (int) static::LOCK_STALE_UNLIMITED;

    }

    public function lock_refusal () : array {

        $lock   = $this->get_lock();
        $run_by = ($lock && !$lock['stale']) ? $lock['run_by'] : null;

        return [
            'status'  => $this->get_status(),
            'run_by'  => $run_by,
            'message' => $run_by ? "A run is in progress ({$run_by}); Stop it first, or wait." : 'No run is in progress; press Start.',
        ];

    }

    public function get_lock_name () : string {

        return $this->get_option_key('lock');

    }

    //

    // The lock and fire rows live in the options table but outside the options API: taken with INSERT IGNORE and the affected rows
    // (add_option() decides "exists" from the cache, so two readers of "absent" would both succeed), read and deleted by direct
    // queries (the options and notoptions caches would answer this request's earlier copy), and rewritten by compare-and-swap
    // against the row just read, so two takers of one stale row cannot both win.
    protected function row_insert (string $name, array $value) : bool {

        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, serialize($value)));
        $this->forget_row_cache($name);

        return $rows === 1;

    }

    protected function row_read (string $name) : ?array {

        global $wpdb;

        $raw   = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name));
        $value = is_string($raw) ? maybe_unserialize($raw) : null;

        return is_array($value) ? $value : null;

    }

    protected function row_swap (string $name, array $value, array $expected) : bool {

        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", serialize($value), $name, serialize($expected)));
        $this->forget_row_cache($name);

        return $rows === 1;

    }

    protected function row_delete (string $name) : void {

        global $wpdb;

        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $name));
        $this->forget_row_cache($name);

    }

    protected function forget_row_cache (string $name) : void {

        wp_cache_delete($name, 'options');

        $notoptions = wp_cache_get('notoptions', 'options');

        if (is_array($notoptions) && isset($notoptions[$name])) {

            unset($notoptions[$name]);
            wp_cache_set('notoptions', $notoptions, 'options');

        }

    }

    public function get_timezone () {
    
        return $this->timezone;
    
    }

    public function get_batch_size () {

        return $this->batch_size;

    }

    // The page's run options, for this request only: the cron loop keeps the class value. The dry-run flag reaches the record only
    // when the key is present ('1' or '0'), so a page that never sent it leaves a consumer's own setting alone.
    public function read_request (array $request) : void {

        if (is_scalar($request['batch_size'] ?? null) && $request['batch_size'] !== '') $this->set_batch_size(max(1, min(static::MAX_BATCH_SIZE, (int) $request['batch_size'])));

        if (in_array($request['dry_run'] ?? null, ['0', '1'], true)) {

            $this->get_store();
            $this->store['dry_run'] = $request['dry_run'] === '1';

        }

    }

    // Only a flag and a log prefix: the framework cannot know what process_item() writes, so a consumer asks before it writes.
    public function is_dry_run () : bool {

        return (bool) ($this->get_store()['dry_run'] ?? false);

    }

    public function set_batch_size ($batch_size) {

        $this->batch_size = $batch_size;

    }

    //

    public function get_key ()         { return $this->key;         }
    public function get_description () { return $this->description; }

    //

    // A cancelled background run on a non-cron iterator leaves no loop firing every minute; a $cron iterator keeps its schedule.
    public function stop_cron () {

        $lock = $this->read_lock();
        if ($lock && $lock['run_by'] === 'cron') $this->release_lock();
        if (!$this->cron_condition()) wp_unschedule_hook($this->get_loop_action());

        return true;

    }

    // The chain's end, after the stopped batch has landed: the server knows the run stopped and the page says so. Only the
    // driver's token releases; a page without one changes nothing here.
    public function stop () {

        $released = $this->holds_lock();
        if ($released) $this->release_lock();

        return ['status' => $this->get_status(), 'released' => $released];

    }

    public function reset () {

        if (!$this->check_lock()) return $this->lock_refusal() + ['refused' => true];

        $this->release_lock();

        $this->store = $this->get_default_store();
        $this->index = 0;

        update_option($this->get_option_key(), $this->store, false);
        $this->mark('--- reset ---');

        return $this->store;

    }

    public function skip_item ($item = null) {

        if (is_null($item)) $item = $this->index;
        $this->store['skip'][] = is_int($item) ? $item : $this->get_item_id($item);

    }

    public function skip_items ($items = []) {

        if ($items) foreach ($items as $item) $this->skip_item($item);

    }

    //
    
    protected function log ($msg = '', $global = false) {

        $this->log[] = $this->line(print_r($msg, true), $global);

    }

    protected function error ($msg = '', $global = false) {

        $this->errors[] = $this->line("Error: " . print_r($msg, true), $global);

    }

    protected function line ($msg, $global = false) {

        $msg  = wp_kses(print_r($msg, true), static::LOG_TAGS);
        $line = $this->stamp() . ($this->is_dry_run() ? '[dry run] ' : '');

        if ($global) {

            $line .= $msg;

        } else {

            $line .= ucfirst((string) $this->labels['single']) . ' #' . esc_html((string) $this->item_id) . ': ' . $msg;

        }

        $this->buffer[] = $line;

        return $line;

    }

    protected function stamp () : string {

        return '[' . (new DateTime('now', new DateTimeZone((string) $this->get_timezone())))->format('Y-m-d H:i:s') . '] ';

    }

    // A stamped line straight to the file, outside the record: a reset is a new run in the same history.
    protected function mark (string $text) : void {

        if ($log = $this->get_log()) $log->write($this->stamp() . $text);

    }

    protected function flush_buffer () : void {

        if ($this->buffer && ($log = $this->get_log())) $log->write(implode(PHP_EOL, $this->buffer));
        $this->buffer = [];

    }

    // The directory: $log_directory, else the lattice.iterator.log_directory filter (one place for every iterator on a site), else
    // Log's own default when the error_log ini is absolute. A relative path would land in the cwd, so it is refused instead.
    public function get_log () : ?Log {

        if (!$this->log_file) return null;
        if ($this->log_handle !== false) return $this->log_handle;

        $directory = $this->log_directory ?: apply_filters('lattice.iterator.log_directory', null, $this);
        if (!$directory) $directory = dirname((string) ini_get('error_log'));

        if (!is_string($directory) || !path_is_absolute($directory)) {

            $problem = "declares \$log_file '{$this->log_file}' but no absolute directory resolves for it.";
            Strict::violation(static::class, $problem, "Declare \$log_directory, add a lattice.iterator.log_directory filter, or set an absolute error_log ini.");
            Strict::log_once(static::class, "{$problem} The run is not written to a file.");

            return $this->log_handle = null;

        }

        return $this->log_handle = Log::get_instance(['file' => $this->log_file, 'directory' => $directory, 'name' => $this->title]);

    }

}