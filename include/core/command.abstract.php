<?php

declare(strict_types=1);

namespace Lattice;

use WP_CLI;
use WP_Error;

// A `wp` command: the root in `$name`, the leaves in `$subcommands`, registered by `register()` on `cli_init` (a feature) or from
// load.php's guard (core). Helpers stay protected: WP-CLI reflects every public method of a whole-object command into a subcommand.
abstract class Command {

    protected $name        = '';
    protected $subcommands = []; // name => [about, synopsis] or [about, synopsis, method] when the name cannot be the method's (`list`)

    public static function register (mixed ...$args) : void {

        if (!defined('WP_CLI') || !WP_CLI) return;

        (new static(...$args))->add(); // @phpstan-ignore new.static (the arguments are the subclass constructor's, forwarded: the signatures are meant to differ)

    }

    // One leaf per row; with no rows the object whole, so WP-CLI reads its docblocks or `__invoke`.
    protected function add () : void {

        if (!$this->subcommands) {

            WP_CLI::add_command($this->name, $this);
            return;

        }

        foreach ($this->subcommands as $sub => $row) WP_CLI::add_command("{$this->name} {$sub}", [$this, (string) ($row[2] ?? $sub)], ['shortdesc' => $row[0], 'synopsis' => $row[1]]);

    }

    /** @return never The analysis runs as PHP 8.0 (phpstan.neon.dist), before the native type. */
    protected function fail (string $message) {

        WP_CLI::error($message);
        WP_CLI::halt(1); // error() has thrown or exited by here; the stub types it as returning

    }

    // JSON, pretty and unescaped; a WP_Error as its three keys, then exit 1.
    protected function answer (mixed $result) : void {

        $error = ($result instanceof WP_Error) ? ['code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : null;

        WP_CLI::line(wp_json_encode($error ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($error) WP_CLI::halt(1);

    }

    protected function table (array $rows, array $fields, string $format = 'table') : void {

        \WP_CLI\Utils\format_items($format, $rows, $fields);

    }

}
