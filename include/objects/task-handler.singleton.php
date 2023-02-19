<?php

namespace Digitalis;

class Task_Handler extends Singleton {

    protected $tasks = [];

    protected $option_name = 'digitalis_tasks';

    public function __construct () {

        add_action('current_screen', [$this, 'maybe_process_tasks']);

    }

    public function add_task ($slug, $callback) {

        $this->tasks[$slug] = $callback;

    }

    public function purge_history () {

        delete_option($this->option_name);

    }

    //

    public function maybe_process_tasks ($screen) {

        if ($screen->id === 'plugins') $this->process_tasks();

    }

    public function process_tasks () {

        if ($history = get_option($this->option_name)) foreach ($history as $slug => $timestamp) {
            
            if (!isset($this->tasks[$slug])) unset($history[$slug]);

        }

        if ($this->tasks) foreach ($this->tasks as $slug => $callback) {

            if (isset($history[$slug]) && $history[$slug]) continue;

            if (is_callable($callback)) call_user_func($callback);

            $history[$slug] = time();

        }

        update_option($this->option_name, $history, false);

    }

}

Task_Handler::get_instance();

