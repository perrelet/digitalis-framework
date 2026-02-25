<?php

namespace Digitalis\DB;

final class Column {

    public function __construct (
        public /* readonly */ string $name,
        public /* readonly */ string $type_sql,
        public /* readonly */ bool   $nullable       = false,
        public /* readonly */ mixed  $default        = null,
        public /* readonly */ bool   $auto_increment = false
    ) {}

    public function to_sql () : string {

        $sql = "`{$this->name}` {$this->type_sql}" . ($this->nullable ? " NULL" : " NOT NULL");

        if ($this->default !== null) $sql .= " DEFAULT " . $this->format_default($this->default);
        if ($this->auto_increment)   $sql .= " AUTO_INCREMENT";

        return $sql;

    }

    private function format_default (mixed $value) : string {

        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value))                    return $value ? "1" : "0";

        $upper = strtoupper((string) $value);
        if (in_array($upper, ['CURRENT_TIMESTAMP'], true)) return $upper;

        $escaped = str_replace("'", "''", (string) $value);

        return "'{$escaped}'";

    }

}