<?php

namespace Digitalis\DB;

final class Option_Migration_Logger extends Migration_Logger {

    private string $option_key;
    private int    $max_entries;

    public function __construct (string $option_key = 'digitalis_migrations_log', int $max_entries = 200) {

        $this->option_key   = $option_key;
        $this->max_entries  = $max_entries;

    }

    public function get_option_key () {

        return $this->option_key;

    }

    public function get_max_entries () {

        return $this->max_entries;

    }

    public function info (string $message, array $context = []) : void {

        $this->append('info', $message, $context);

    }

    public function warning (string $message, array $context = []) : void {

        $this->append('warning', $message, $context);

    }

    public function error (string $message, array $context = []) : void {

        $this->append('error', $message, $context);

    }

    public function clear() : void {

        delete_option($this->option_key);

    }

    public function get_entries () {

        return get_option($this->option_key, []);
    
    }

    private function append (string $level, string $message, array $context) : void {

        $entries = get_option($this->option_key, []);

        if (!is_array($entries)) $entries = [];

        $entries[] = [
            'ts'      => gmdate('c'),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        $count = count($entries);
        if ($count > $this->max_entries) $entries = array_slice($entries, $count - $this->max_entries);

        update_option($this->option_key, $entries, false);

    }

}