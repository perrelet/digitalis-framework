<?php

namespace Digitalis;

trait Has_Version {

    protected static array $writing_version = [];

    protected static function get_version_key () : string {

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

    // Anyone who read mid-write is stale too.
    public function finish_version () : void {

        if (!isset(static::$writing_version[$this->get_id()])) return;

        unset(static::$writing_version[$this->get_id()]);

        $this->step_version($this->get_version());

    }

    public function duplicate ($overrides = [], $exclude_meta = []) {

        return parent::duplicate($overrides, array_merge((array) $exclude_meta, [static::get_version_key()]));

    }

    public function raise_version () : void {

        if (isset(static::$writing_version[$this->get_id()])) return;

        $this->step_version($this->get_version());

        static::$writing_version[$this->get_id()] = true;

    }

    // update_meta ignores a previous value of 0, so versions start at 1.
    protected function step_version (int $seen) : bool {

        return $seen ? (bool) $this->update_meta(static::get_version_key(), $seen + 1, $seen) : (bool) $this->add_meta(static::get_version_key(), 1, true);

    }

}
