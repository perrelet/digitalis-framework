<?php

declare(strict_types=1);

namespace Lattice;

use Closure;

// Each run() is its own frame, so an app booting inside another app's batch switches on before its own boot_*().
final class Batch {

    private static array $frames = [];

    public static function run (callable $build) : void {

        self::$frames[] = [];
        $depth = array_key_last(self::$frames);

        try {

            $build();

            while (self::$frames[$depth]) {
                $switch_on = array_shift(self::$frames[$depth]);
                $switch_on();
            }

        } finally {

            array_pop(self::$frames);

        }

    }

    public static function defer (Closure $switch_on) : void {

        if (!self::$frames) {
            $switch_on();
            return;
        }

        self::$frames[array_key_last(self::$frames)][] = $switch_on;

    }

}
