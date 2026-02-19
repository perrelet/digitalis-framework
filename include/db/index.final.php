<?php

namespace Digitalis\DB;

final class Index {

    public function __construct (
        public /* readonly */ string  $type,
        public /* readonly */ array   $columns,
        public /* readonly */ ?string $name = null
    ) {}

    public static function primary (array $columns) : self {

        return new self('PRIMARY', $columns, null);

    }

    public static function unique (string $name, array $columns) : self {

        return new self('UNIQUE', $columns, $name);

    }

    public static function index (string $name, array $columns) : self {

        return new self('INDEX', $columns, $name);

    }

    public function to_sql () : string {

        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", $this->columns));

        if ($this->type === 'PRIMARY') return "PRIMARY KEY ({$cols})";
        if ($this->type === 'UNIQUE')  return "UNIQUE KEY `{$this->name}` ({$cols})";

        return "KEY `{$this->name}` ({$cols})";

    }

}