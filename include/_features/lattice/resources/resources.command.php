<?php

declare(strict_types=1);

namespace Lattice;

use WP_CLI;

class Resources_Command extends Command {

    const CONTROL = '[--version=<n>] [--why=<why>] [--input=<file>] [--confirm=<fingerprint>] [--dry-run]';

    protected $name        = 'lattice';
    protected $subcommands = [
        'guide'   => ['The resources this account can reach, their fields and actions.', ''],
        'journal' => ['Changes, newest first.', '[--resource=<resource>] [--id=<id>] [--set=<set>] [--proposer=<user>] [--since=<row>] [--page=<page>]'],
        'undo'    => ['Reverse a journal row.', '<row> ' . self::CONTROL],
    ];

    // The fixed three, then one command per resource.
    protected function add () : void {

        parent::add();

        foreach (Resources::inst()->get_resources() as $name => $resource) WP_CLI::add_command("{$this->name} {$name}", fn ($args, $assoc) => $this->run($name, $args, $assoc), [
            'shortdesc' => "list, get, create, update, trash, untrash, view or an action on {$resource->get_label()}.",
            'synopsis'  => '<operation> [<id>] [<view>] [--status=<status>] [--search=<search>] [--page=<page>] ' . static::CONTROL . ' [--<field>=<value>]',
        ]);

    }

    public function guide ($args, $assoc) {

        $this->answer(Resources::inst()->guide());

    }

    public function journal ($args, $assoc) {

        $this->answer(Resources::inst()->journal([
            'resource'  => (string) ($assoc['resource'] ?? ''),
            'record_id' => (string) ($assoc['id'] ?? ''),
            'set'       => $assoc['set'] ?? null,
            'proposer'  => $assoc['proposer'] ?? null,
            'since'     => (int) ($assoc['since'] ?? 0),
            'page'      => (int) ($assoc['page'] ?? 1),
        ]));

    }

    public function undo ($args, $assoc) {

        $this->answer(Resources::inst()->undo((int) $args[0], $this->get_input($assoc), 'cli'));

    }

    public function run (string $name, array $args, array $assoc) : void {

        [$operation, $id] = [$args[0], $args[1] ?? null];

        if (is_null($id) && !in_array($operation, ['list', 'create'], true)) $this->fail("Give the record's id: wp lattice {$name} {$operation} <id>");

        $api = Resources::inst();

        // The synopsis takes any flag for an action's input, so WP-CLI no longer stops a mistyped one: a write must not go ahead without its --dry-run.
        $extra = array_diff_key($assoc, array_flip(['input', 'version', 'why', 'confirm', 'dry-run', 'status', 'search', 'page']));

        if ($extra && in_array($operation, ['list', 'get', 'create', 'update', 'trash', 'untrash', 'view'], true)) $this->fail('Unknown: --' . implode(', --', array_keys($extra)));

        $this->answer(match ($operation) {
            'list'   => $api->list($name, $assoc),
            'get'    => $api->get($name, $id),
            'create' => $api->create_record($name, $this->get_input($assoc), 'cli'),
            'update' => $api->update($name, $id, $this->get_input($assoc), 'cli'),
            'trash'   => $api->trash($name, $id, $this->get_input($assoc), 'cli'),
            'untrash' => $api->untrash($name, $id, $this->get_input($assoc), 'cli'),
            'view'   => $api->view($name, $id, (string) ($args[2] ?? '')),
            default  => $api->act($name, $id, $operation, $this->get_input($assoc) + $extra, 'cli'),
        });

    }

    protected function get_input (array $assoc) : array {

        $input = [];

        if (isset($assoc['input'])) {

            $json  = file_get_contents($assoc['input'] === '-' ? 'php://stdin' : (string) $assoc['input']);
            $input = json_decode((string) $json, true);

            if (!is_array($input)) $this->fail('--input is not a JSON object.');

        }

        foreach (['version', 'why', 'confirm'] as $key) if (isset($assoc[$key])) $input[$key] = $assoc[$key];

        if (!empty($assoc['dry-run'])) $input['dry_run'] = true;

        return $input;

    }

}
