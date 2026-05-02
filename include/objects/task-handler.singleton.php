<?php

namespace Digitalis;

class Task_Handler extends Singleton {

    protected $tasks = [];

    protected $option_name = 'digitalis_tasks';

    public function __construct () {

        add_action('admin_init', [$this, 'process_tasks']);

    }

    public function get_tasks () {

        return $this->tasks;

    }

    public function add_task ($slug, $callback, $version = 1) {

        $this->tasks[$slug] = [
            'callback' => $callback,
            'version'  => $version,
        ];

    }

    public function purge_history () {

        delete_option($this->option_name);

    }

    //

    public function process_tasks () {

        $history = get_option($this->option_name, []);
        $dirty   = false;

        // Prune tasks no longer registered

        foreach ($history as $slug => $entry) {
            if (!isset($this->tasks[$slug])) {
                unset($history[$slug]);
                $dirty = true;
            }
        }

        // Run new or updated tasks

        foreach ($this->tasks as $slug => $task) {

            $stored_version = isset($history[$slug]) ? $history[$slug]['version'] : 0;

            if ($stored_version >= $task['version']) continue;

            try {
                if (is_callable($task['callback'])) call_user_func($task['callback']);
            } catch (\Throwable $e) {
                Log::w(sprintf(
                    "[%s] %s: %s in %s:%d\n%s",
                    $slug,
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                ), 'task-handler.log');
                continue;
            }

            $history[$slug] = [
                'version' => $task['version'],
                'time'    => time(),
            ];
            $dirty = true;

        }

        if ($dirty) update_option($this->option_name, $history, false);

    }

}

Task_Handler::get_instance();