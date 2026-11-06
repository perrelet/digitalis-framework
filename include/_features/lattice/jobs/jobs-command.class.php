<?php

namespace Lattice;

use WP_CLI;

class Jobs_Command {

    public static function register () : void {

        $command = new static;

        foreach ([
            'list'      => ['Jobs, newest first.', '[--state=<state>] [--limit=<limit>]'],
            'get'       => ['One job.', '<id>'],
            'run'       => ['Run what is due, with no budget unless given, or one job.', '[--budget=<seconds>] [--id=<id>]'],
            'cancel'    => ['Cancel a job.', '<id> [--why=<why>]'],
            'retry'     => ['Move a failed job back to pending.', '<id>'],
            'pause'     => ['Stop the worker.', ''],
            'resume'    => ['Start it again, from any reason.', '[--discard]'],
            'uninstall' => ['Clear the events, ready to remove the feature.', '[--drop]'],
        ] as $name => [$about, $synopsis]) WP_CLI::add_command("lattice jobs {$name}", [$command, ($name === 'list') ? 'list_jobs' : $name], ['shortdesc' => $about, 'synopsis' => $synopsis]);

    }

    public function list_jobs ($args, $assoc) {

        if (!Jobs_Schema::is_current()) WP_CLI::error('The jobs table is not current.');

        $rows = Jobs_Page::get_rows((string) ($assoc['state'] ?? ''), (int) ($assoc['limit'] ?? 50));

        if (!$rows) WP_CLI::success('No jobs.');
        else        WP_CLI\Utils\format_items('table', $rows, ['id', 'type', 'state', 'attempts', 'done', 'total', 'run_after', 'error']);

    }

    public function get ($args, $assoc) {

        WP_CLI::line(wp_json_encode($this->find($args[0])->get_row(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    }

    public function run ($args, $assoc) {

        $worker = Jobs_Worker::inst();

        if (!$worker->is_open()) WP_CLI::error('The worker is paused, or the schema is not current.');

        if (isset($assoc['id'])) {

            if (!$this->find($assoc['id'])->run_one()) WP_CLI::error('It could not be taken: it may be running, ended or of an unknown type.');

        } else {

            $worker->work(isset($assoc['budget']) ? (float) $assoc['budget'] : null);

        }

        WP_CLI::success('Done.');

    }

    public function cancel ($args, $assoc) {

        WP_CLI::success((string) $this->find($args[0])->cancel((string) ($assoc['why'] ?? '')));

    }

    public function retry ($args, $assoc) {

        if (!$this->find($args[0])->retry()) WP_CLI::error('Refused.');

        WP_CLI::success('Pending again.');

    }

    public function pause ($args, $assoc) {

        Jobs_Worker::inst()->pause();

        WP_CLI::success('Paused.');

    }

    public function resume ($args, $assoc) {

        WP_CLI::success(sprintf('Resumed; %d discarded.', Jobs_Worker::inst()->resume(!empty($assoc['discard']))));

    }

    public function uninstall ($args, $assoc) {

        Jobs_Worker::inst()->uninstall(!empty($assoc['drop']));

        WP_CLI::success('Uninstalled.');

    }

    protected function find ($id) : Job {

        if (!Jobs_Schema::is_current()) WP_CLI::error('The jobs table is not current.');

        return Job::get_instance((int) $id) ?: WP_CLI::error("No job #{$id}.");

    }

}
