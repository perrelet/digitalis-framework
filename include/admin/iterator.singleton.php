<?php

namespace Digitalis;

abstract class Iterator extends Singleton {

    protected $title            = 'Iterator';
    protected $key              = 'digitalis_iterator';
    protected $batch_size       = 1;
    protected $capability       = 'administrator';

    protected $halt_on_fail     = false;
    protected $dynamic_total    = false;
    protected $print_results    = false;

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

    //

    public function init () {

        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action("wp_ajax_iterator_{$this->key}", [$this, 'ajax']);

        //if (isset($_GET['reset'])) $this->reset();

    }

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
                wp_send_json($this->get_total_items());

            case "iterate":
                wp_send_json($this->iterate());

            case "reset":
                wp_send_json($this->reset());

        }

    }

    public function iterate ($return_store = false) {

        $results = [
            'processed' => [],
            'failed'    => [],
            'skipped'   => [],
        ];

        $this->get_store();
        $this->items = $this->get_items();

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

            if ($this->dynamic_total) $response['total'] = $this->get_total_items();

            return $response;

        }

    }

    public function add_admin_page () {

        add_submenu_page(
            'tools.php',
            $this->title,
            $this->title,
            $this->capability,
            $this->get_option_key(),
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

        $this->get_store();
        $total = $this->get_total_items();

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
            'labels'        => $this->labels,
        ]);

        ?>

        <style><?= file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/iterator.css');?></style>

        <div class='digitalis-iterator iterator iterator-<?= $this->key; ?>'>
            <div class='controls'>
                <button data-task='start'>Start</button>
                <button data-task='stop'>Stop</button>
                <button data-task='reset'>Reset</button>
            </div>
            <div class='progress'>
                <div class='status-bar'>
                    <div class='index-total'>Progress: <span class='index'><?= $this->index; ?></span> / <span class='total'><?= $total; ?></span></div>
                    <div class="status">Ready</div>
                </div>
                <div class='progress-track'>
                    <div class='progress-bar' style='width: <?= $total ? (100 * $this->index / $total) : 0; ?>%;'></div>
                </div>
                <div class='status-bar'>
                    <div class='percent'><?= $total ? floor(100 * $this->index / $total) : 0 ?>%</div>
                    <div class='time'>00:00:00</div>
                </div>
            </div>

            <div class='log-wrap'>
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

    public function get_option_key () {

        return "iterator_" . $this->key;

    }

    public function get_store () {

        if (is_null($this->store)) {
            $this->store = get_option($this->get_option_key(), $this->default_store);
            $this->index = $this->store['index'];
        }

        return $this->store;

    }

    public function update_store () {

        $this->get_store();

        $this->store['index']   = $this->index;
        $this->store['log']     = array_merge($this->store['log'], $this->log);
        $this->store['errors']  = array_merge($this->store['errors'], $this->errors);

        update_option($this->get_option_key(), $this->store, false);

    }

    public function reset () {

        $this->store = $this->default_store;
        update_option($this->get_option_key(), $this->store, false);

        return $this->store;

    }

    public function skip_item ($item) {

        $this->store['skip'][] = is_int($item) ? $item : $this->get_item_id($item);

    }

    public function skip_items ($items = []) {

        if ($items) foreach ($items as $item) $this->skip_item($item);

    }

    public function set_batch_size ($batch_size) {

        $this->batch_size = $batch_size;

    }

    public function set_max_index ($max_index) {

        $this->max_index = $max_index;

    }

    //
    
    protected function log ($msg = '') {

        $this->log[] = $this->line($msg);

    }

    protected function error ($msg = '') {

        $this->errors[] = $this->line("Error: " . $msg);

    }

    protected function line ($msg) {

        return ucfirst($this->labels['single']) . " #{$this->item_id}: {$msg}";

    }

}