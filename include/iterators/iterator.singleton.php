<?php

namespace Digitalis;

use DateTime;
use DateTimeZone;

abstract class Iterator extends Singleton {

    protected $title              = 'Iterator';
    protected $key                = 'digitalis_iterator';
    protected $batch_size         = 1;
    protected $capability         = 'administrator';
    protected $menu_slug          = null;
    protected $parent_menu_slug   = 'tools.php';
    protected $timezone           = 'UCT';
    protected $description        = false;
  
    protected $halt_on_fail       = false;
    protected $dynamic_total      = false;
    protected $print_results      = false;
  
    protected $cron               = false;
    protected $cron_time          = '00:00:00';
    protected $cron_schedule      = 'daily';
    protected $cron_loop_schedule = 'every_minute';

    protected $cron_run_action  = false;
    protected $cron_loop_action = false;

    protected $labels = [
        'single'    => 'item',
        'plural'    => 'items',
    ];

    protected $default_store = [
        'index'     => 0,
        'processed' => [],
        'skip'      => [],
        'failed'    => [],
        'log'       => [],
        'errors'    => [],
    ];

    protected $items = [];
    protected $store;
    protected $index;
    protected $item_id;

    protected $log = [];
    protected $errors = [];

    public function get_items () { return []; }

    public function process_item ($item) { 

        return true;
    
    }

    public function get_total_items () {

        return 1000;

    }

    public function get_item_id ($item) {

        return $this->index;

    }

    public function cron_condition () {
    
        return $this->cron;
    
    }

    //

    public function init () {

        add_action('admin_menu', [$this, 'add_admin_page'], 99);
        add_action("wp_ajax_iterator_{$this->key}", [$this, 'ajax']);

        if ($this->cron_condition()) {

            $cron_action_start = $this->get_option_key() . '_start';
            $cron_action_loop  = $this->get_option_key() . '_loop';

            add_action($cron_action_start, [$this, 'cron_start']);
            add_action($cron_action_loop,  [$this, 'cron_loop']);

            if (!wp_next_scheduled($cron_action_start)) {

                $date_string = (new DateTime)->format('Y-m-d') . ' ' . $this->cron_time;
                $date_time = DateTime::createFromFormat('Y-m-d H:i:s', $date_string, new DateTimeZone($this->get_timezone()));

                wp_schedule_event($date_time->getTimestamp(), $this->cron_schedule, $cron_action_start);

            }

            if (!wp_next_scheduled($cron_action_loop)) {

                wp_schedule_event(time(), $this->cron_loop_schedule, $cron_action_loop);

            }

        }

    }

    //

    public function ajax () {

        if (!wp_verify_nonce($_REQUEST['nonce'], "iterator_{$this->key}")) {
			wp_send_json_error('Iterator Expired.', 401);
			wp_die(); 
		}

        if (!current_user_can($this->capability)) {
			wp_send_json_error('Unauthorized Access.', 401);
			wp_die(); 
		}

        if (!isset($_REQUEST['task'])) {
			wp_send_json_error('Task parameter is required.', 422);
			wp_die(); 
		}

        switch ($_REQUEST['task']) {

            case "total":
                wp_send_json($this->get_total_items_wrap());

            case "iterate":
                wp_send_json($this->iterate());

            case "reset":
                wp_send_json($this->reset());

            case "stop_cron":
                wp_send_json($this->stop_cron());

        }

    }

    public function cron_start () {

        $this->set_doing_cron(true);
        $this->reset();
        $this->iterate();
    
    }

    public function cron_loop () {
    
        if (!$this->is_doing_cron()) return;

        $this->iterate();
    
    }

    //

    public function iterate ($return_store = false) {

        $results = [
            'processed' => [],
            'failed'    => [],
            'skipped'   => [],
        ];

        $this->warm_up();
        $this->items = $this->get_items();

        if ($this->index == 0) $this->log('Starting process.', true);

        if ($this->items) foreach ($this->items as $item) {

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

            $this->index++;

        }

        $count = 0;
        foreach ($results as $key => $result) {

            $count += count($result);
            if (isset($this->store[$key])) $this->store[$key] = array_merge($this->store[$key], $result);

        }

        $this->log("Batch complete - {$count} {$this->labels['plural']} processed (" . count($results['skipped']) . " skipped, "  . count($results['failed']) . " failed)", true);

        if ($this->is_complete()) {

            $this->log('Process complete 🚀', true);
            $this->set_doing_cron(false);

        }

        $this->update_store();

        if ($return_store) {

            return $this->store;

        } else {

            $response = [
                'index'     => $this->index,
                'results'   => $results,
                'count'     => $count,
                'log'       => $this->log,
                'errors'    => $this->errors,
            ];

            if ($this->dynamic_total) $response['total'] = $this->get_total_items_wrap();

            return $response;

        }

    }

    //

    public function add_admin_page () {

        $menu_slug = $this->menu_slug ? $this->menu_slug : $this->get_option_key();

        add_submenu_page(
            $this->parent_menu_slug,
            $this->title,
            $this->title,
            $this->capability,
            $menu_slug,
            [$this, 'render_admin_page']
        );

    }

    public function render_admin_page () {

        echo "<div class='wrap'>";

            echo "<h1>{$this->title}</h1>";

            $this->render_controller();

        echo "</div>";

    }

    public function render_controller () {

        $this->warm_up();

        $total = $this->get_total_items_wrap();

        wp_enqueue_script('digitalis-iterator', DIGITALIS_FRAMEWORK_URI . 'assets/js/iterator.js', [], DIGITALIS_FRAMEWORK_VERSION, true);
        wp_localize_script('digitalis-iterator', 'iterator_params', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce("iterator_{$this->key}"),
            'key'           => $this->key,
            'index'         => $this->index,
            'batch_size'    => $this->batch_size,
            'halt_on_fail'  => $this->halt_on_fail,
            'dynamic_total' => $this->dynamic_total,
            'print_results' => $this->print_results,
            'doing_cron'    => $this->is_doing_cron(),
            'labels'        => $this->labels,
        ]);

        ?>

        <style><?= file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/iterator.css');?></style>

        <div class='digitalis-iterator iterator iterator-<?= $this->key; ?><?= $this->is_doing_cron() ? ' doing_cron running' : '' ?>'>
            <?php if ($this->description) echo "<div class='iterator-panel description'>{$this->description}</div>"; ?>
            <div class='iterator-panel controls'>
                <button data-task='start'>Start</button>
                <button data-task='stop'>Stop</button>
                <button data-task='reset'>Reset</button>
            </div>
            <div class='iterator-panel progress'>
                <div class='status-bar'>
                    <div class='index-total'>Progress: <span class='index'><?= $this->index; ?></span> / <span class='total'><?= $total; ?></span></div>
                    <div class="status"><?= $this->is_doing_cron() ? 'Running' : 'Ready' ?></div>
                </div>
                <div class='progress-track'>
                    <div class='progress-bar' style='width: <?= $total ? (100 * $this->index / $total) : 0; ?>%;'></div>
                </div>
                <div class='status-bar'>
                    <div class='percent'><?= $total ? floor(100 * $this->index / $total) : 0 ?>%</div>
                    <div class='time'><?= $this->is_doing_cron() ? 'Cron Task' : '00:00:00' ?></div>
                </div>
            </div>

            <div class='iterator-panel log-wrap'>
                <label>Batch Log:</label>
                <div class='iterator-log'>
                    <?php if ($this->store['errors']) echo "<div class='log-error'>" . implode("</div><div class='log-error'>", array_reverse($this->store['errors'])) . "</div>"; ?>
                    <?php if ($this->store['log']) echo "<div class='log-item'>" . implode("</div><div class='log-item'>", array_reverse($this->store['log'])) . "</div>"; ?>
                </div>
            </div>

        </div>

        <?php

    }

    //

    public function warm_up () {

        $this->get_store();
        $this->index = (int) $this->store['index'];

    }

    public function get_progress () {
    
        $total = $this->get_total_option();

        return $total ? ($this->get_index() / $total) : 0;
    
    }

    public function is_complete () {
    
        return $this->get_progress() >= 1;
    
    }

    public function get_option_key ($suffix = false) {

        $key = "iterator_" . $this->key;
        if ($suffix) $key .= ' ' . $suffix;

        return $key;

    }

    public function get_total_items_wrap () {

        $total = $this->get_total_items();
        $this->set_total_option($total);

        return $total;

    }

    public function get_store () {

        if (is_null($this->store)) {

            $this->store = get_option($this->get_option_key(), $this->default_store);
            $this->pre_get_store($this->store);

        }

        return $this->store;

    }

    public function update_store () {

        $this->get_store();

        $this->store['index']  = $this->index;
        $this->store['log']    = array_merge($this->store['log'], $this->log);
        $this->store['errors'] = array_merge($this->store['errors'], $this->errors);

        $this->pre_update_store($store);

        update_option($this->get_option_key(), $this->store, false);

    }

    public function pre_get_store (&$store) {}
    public function pre_update_store (&$store) {}

    public function get_index () {
    
        if (is_null($this->index)) $this->warm_up();

        return $this->index;
    
    }

    public function get_total_option () {
    
        $total = get_option($this->get_option_key('total'), false);

        if ($total === false) $total = $this->get_total_items_wrap();

        return $total;
    
    }

    public function set_total_option ($total) {
    
        update_option($this->get_option_key('total'), $total);
    
    }

    public function is_doing_cron () {
    
        return get_option($this->get_option_key('cron'), false);
    
    }

    public function set_doing_cron ($state) {
    
        update_option($this->get_option_key('cron'), (bool) $state);
    
    }

    public function get_timezone () {
    
        return $this->timezone;
    
    }

    public function set_batch_size ($batch_size) {

        $this->batch_size = $batch_size;

    }

    //

    public function stop_cron () {

        $this->set_doing_cron(false);

        return true;

    }

    public function reset () {

        $this->store = $this->default_store;
        $this->index = 0;

        update_option($this->get_option_key(), $this->store, false);

        return $this->store;

    }

    public function skip_item ($item) {

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
        
        $line = '[' . (new DateTime('now', new DateTimeZone($this->get_timezone())))->format('Y-m-d H:i:s') . '] ';

        if ($global) {

            $line .= print_r($msg, true);

        } else {

            $line .= ucfirst($this->labels['single']) . " #{$this->item_id}: " . print_r($msg, true);

        }

        return $line;

    }

}