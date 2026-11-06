<?php

declare(strict_types=1);

namespace Lattice;

trait Has_Version {

    protected static array $writing_version = [];

    public static function get_version_key () : string {

        return '_lattice_version';

    }

    public function get_version () : int {

        return (int) $this->get_meta(static::get_version_key());

    }

    public function claim_version (int $seen) : bool {

        if (!$this->step_version($seen)) return false;

        static::$writing_version[$this->get_id()] = true;

        return true;

    }

    public function is_claimed () : bool {

        return isset(static::$writing_version[$this->get_id()]);

    }

    // Anyone who read mid-write is stale too.
    public function finish_version () : void {

        if (!$this->is_claimed()) return;

        unset(static::$writing_version[$this->get_id()]);

        $this->step_version($this->get_version());

    }

    // A claimed write that a guard refused outright: nothing changed, so a later undo must still find the version it knows.
    public function restore_version (int $seen) : void {

        if ($this->get_version() !== $seen + 2) return;

        $seen ? $this->update_meta(static::get_version_key(), $seen, $seen + 2) : $this->delete_meta(static::get_version_key());

    }

    public function raise_version () : void {

        if (!$this->is_claimed()) $this->step_version($this->get_version());

    }

    // update_meta ignores a previous value of 0, so versions start at 1.
    protected function step_version (int $seen) : bool {

        return $seen ? (bool) $this->update_meta(static::get_version_key(), $seen + 1, $seen) : (bool) $this->add_meta(static::get_version_key(), 1, true);

    }

}
