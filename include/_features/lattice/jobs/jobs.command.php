<?php

declare(strict_types=1);

namespace Lattice;

use WP_CLI;

class Jobs_Command extends Command {

    protected $name        = 'lattice jobs';
    protected $subcommands = [
        'list'      => ['Jobs, newest first.', '[--state=<state>] [--limit=<limit>]', 'list_jobs'],
        'get'       => ['One job.', '<id>'],
        'run'       => ['Run what is due, with no budget unless given, or one job.', '[--budget=<seconds>] [--id=<id>]'],
        'cancel'    => ['Cancel a job.', '<id> [--why=<why>]'],
        'retry'     => ['Move a failed job back to pending.', '<id>'],
        'pause'     => ['Stop the worker.', ''],
        'resume'    => ['Start it again, from any reason.', '[--discard]'],
        'uninstall' => ['Clear the events, ready to remove the feature.', '[--drop]'],
    ];

    public function list_jobs ($args, $assoc) {

        if (!Jobs_Schema::is_current()) $this->fail('The jobs table is not current.');

        $rows = Jobs_Page::get_rows((string) ($assoc['state'] ?? ''), (int) ($assoc['limit'] ?? 50));

        if (!$rows) WP_CLI::success('No jobs.');
        else        $this->table($rows, ['id', 'type', 'state', 'attempts', 'done', 'total', 'run_after', 'error']);

    }

    public function get ($args, $assoc) {

        $this->answer($this->find($args[0])->get_row());

    }

    public function run ($args, $assoc) {

        $worker = Jobs_Worker::inst();

        if (!$worker->is_open()) $this->fail('The worker is paused, or the schema is not current.');

        if (isset($assoc['id'])) {

            if (!$this->find($assoc['id'])->run_one()) $this->fail('It could not be taken: it may be running, ended or of an unknown type.');

        } else {

            $worker->work(isset($assoc['budget']) ? (float) $assoc['budget'] : null);

        }

        WP_CLI::success('Done.');

    }

    public function cancel ($args, $assoc) {

        WP_CLI::success((string) $this->find($args[0])->cancel((string) ($assoc['why'] ?? '')));

    }

    public function retry ($args, $assoc) {

        if (!$this->find($args[0])->retry()) $this->fail('Refused.');

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

        if (!Jobs_Schema::is_current()) $this->fail('The jobs table is not current.');

        return Job::get_instance((int) $id) ?: $this->fail("No job #{$id}.");

    }

}
