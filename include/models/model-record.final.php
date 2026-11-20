<?php

declare(strict_types=1);

namespace Lattice;

// One stored entity in a request: the views of it (User, Customer, Account) bind their shared properties to $state by reference.
final class Model_Record {

    public array $state   = [];
    public array $views   = [];
    public int   $counter = 0;

    public function __construct (public readonly string $storage, public readonly int $site) {}

    public static function find (string $storage, int|string $id) : ?self {

        return Registry::get('record', $storage . '@' . get_current_blog_id(), $id);

    }

    public function keep (int|string $id) : void {

        Registry::set('record', $this->storage . '@' . $this->site, $id, $this);

    }

}
