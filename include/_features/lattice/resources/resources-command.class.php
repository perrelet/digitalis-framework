<?php

namespace Lattice;

use WP_CLI;
use WP_Error;

class Resources_Command {

    const CONTROL = '[--version=<n>] [--why=<why>] [--input=<file>] [--confirm=<fingerprint>] [--dry-run]';

    public static function register () : void {

        $command = new static;

        WP_CLI::add_command('lattice guide', [$command, 'guide'], ['shortdesc' => 'The resources this account can reach, their fields and actions.']);
        WP_CLI::add_command('lattice journal', [$command, 'journal'], ['shortdesc' => 'Changes, newest first.', 'synopsis' => '[--resource=<resource>] [--id=<id>] [--since=<row>] [--page=<page>]']);
        WP_CLI::add_command('lattice undo', [$command, 'undo'], ['shortdesc' => 'Reverse a journal row.', 'synopsis' => '<row> ' . static::CONTROL]);

        foreach (Resources::inst()->get_resources() as $name => $resource) WP_CLI::add_command("lattice {$name}", fn ($args, $assoc) => $command->run($name, $args, $assoc), [
            'shortdesc' => "list, get, create, update, view or an action on {$resource->get_label()}.",
            'synopsis'  => '<operation> [<id>] [<view>] [--status=<status>] [--search=<search>] [--page=<page>] ' . static::CONTROL,
        ]);

    }

    public function guide ($args, $assoc) {

        $this->answer(Resources::inst()->guide());

    }

    public function journal ($args, $assoc) {

        $this->answer(Resources::inst()->journal([
            'resource'  => (string) ($assoc['resource'] ?? ''),
            'record_id' => (string) ($assoc['id'] ?? ''),
            'since'     => (int) ($assoc['since'] ?? 0),
            'page'      => (int) ($assoc['page'] ?? 1),
        ]));

    }

    public function undo ($args, $assoc) {

        $this->answer(Resources::inst()->undo((int) $args[0], $this->get_input($assoc), 'cli'));

    }

    public function run (string $name, array $args, array $assoc) : void {

        [$operation, $id] = [$args[0], $args[1] ?? null];

        if (is_null($id) && !in_array($operation, ['list', 'create'], true)) WP_CLI::error("Give the record's id: wp lattice {$name} {$operation} <id>");

        $api = Resources::inst();

        $this->answer(match ($operation) {
            'list'   => $api->list($name, $assoc),
            'get'    => $api->get($name, $id),
            'create' => $api->create_record($name, $this->get_input($assoc), 'cli'),
            'update' => $api->update($name, $id, $this->get_input($assoc), 'cli'),
            'view'   => $api->view($name, $id, (string) ($args[2] ?? '')),
            default  => $api->act($name, $id, $operation, $this->get_input($assoc), 'cli'),
        });

    }

    protected function get_input (array $assoc) : array {

        $input = [];

        if (isset($assoc['input'])) {

            $json  = file_get_contents($assoc['input'] === '-' ? 'php://stdin' : $assoc['input']);
            $input = json_decode((string) $json, true);

            if (!is_array($input)) WP_CLI::error('--input is not a JSON object.');

        }

        foreach (['version', 'why', 'confirm'] as $key) if (isset($assoc[$key])) $input[$key] = $assoc[$key];

        if (!empty($assoc['dry-run'])) $input['dry_run'] = true;

        return $input;

    }

    protected function answer ($result) : void {

        $error = ($result instanceof WP_Error) ? ['code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : null;

        WP_CLI::line(wp_json_encode($error ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($error) WP_CLI::halt(1);

    }

}
