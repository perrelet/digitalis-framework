<?php

declare(strict_types=1);

namespace Lattice;

class Task_Handler extends Singleton {

    protected $tasks = [];

    protected $option_name = 'digitalis_tasks';

    protected function switch_on () : void {

        $this->add_action('admin_init', [$this, 'process_tasks'], 10, 1);

        parent::switch_on();

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

        Lasting::sync($this->option_name, array_map(fn ($task) => $task['version'], $this->tasks), function (string $slug) {

            try {

                $callback = $this->tasks[$slug]['callback'];
                if (is_callable($callback)) call_user_func($callback);

            } catch (\Throwable $e) {

                Log::w(sprintf("[%s] %s: %s in %s:%d\n%s", $slug, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()), 'task-handler.log');

                return false;

            }

            return true;

        });

    }

}

Task_Handler::get_instance();