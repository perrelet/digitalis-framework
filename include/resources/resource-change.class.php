<?php

namespace Digitalis;

final class Resource_Change {

    public array  $changes      = [];
    public array  $consequences = [];
    public string $fingerprint  = '';

    public function __construct (
        public readonly Model_Resource $resource,
        public readonly string         $operation,
        public readonly ?object        $before,
        public readonly object         $after,
        public readonly array          $values = [],
        public readonly array          $input  = [],
        public readonly string         $why    = '',
        public readonly string         $via    = '',
    ) {}

    public function describe () : array {

        return [
            'dry_run'      => true,
            'resource'     => $this->resource->get_name(),
            'id'           => $this->before?->get_id(),
            'version'      => $this->before?->get_version(),
            'operation'    => $this->operation,
            'changes'      => $this->changes,
            'consequences' => $this->consequences,
            'fingerprint'  => $this->fingerprint,
        ];

    }

}
