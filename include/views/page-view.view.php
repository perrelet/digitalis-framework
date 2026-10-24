<?php

declare(strict_types=1);

namespace Lattice;

abstract class Page_View extends View {

    use Resolvable;

    protected static $layout = [];

    public static function get_layout_overrides () {

        return static::$layout;

    }

}
