<?php

namespace Lattice;

use ReflectionClass;
use ReflectionMethod;

final class Strict {

    private static array $violations = [];
    private static array $walked     = [];
    private static int   $depth      = 0;

    public static function enabled () : bool {

        if (defined('LATTICE_STRICT')) return (bool) LATTICE_STRICT;

        return defined('WP_DEBUG') && WP_DEBUG;

    }

    public static function fail (string $class, string $problem, string $fix) : void {

        if (!self::enabled()) return;

        throw new Strict_Violation(self::message($class, $problem, $fix));

    }

    // Audits accumulate across a walk so one boot lists everything; outside a walk they surface at once.
    public static function violation (string $class, string $problem, string $fix) : void {

        if (!self::enabled()) return;

        self::$violations[] = self::message($class, $problem, $fix);

        if (self::$depth === 0) self::flush();

    }

    public static function audit (string $class) : void {

        if (!self::enabled()) return;

        self::$walked[$class] = true;

        foreach (array_reverse(class_parents($class)) as $ancestor) {

            if (!method_exists($ancestor, 'strict_audit'))                                            continue;
            if ((new ReflectionMethod($ancestor, 'strict_audit'))->getDeclaringClass()->name !== $ancestor) continue;

            $ancestor::strict_audit($class);

        }

    }

    public static function was_walked (string $class) : bool {

        return isset(self::$walked[$class]);

    }

    public static function begin_walk () : void {

        self::$depth++;

    }

    public static function end_walk (bool $completed) : void {

        self::$depth--;

        if ($completed && (self::$depth === 0)) self::flush();

    }

    private static function flush () : void {

        if (!self::$violations) return;

        $list = self::$violations;
        self::$violations = [];

        throw new Strict_Violation(count($list) . " strict violation(s):\n  " . implode("\n  ", $list));

    }

    private static function message (string $class, string $problem, string $fix) : string {

        $file = class_exists($class, false) ? (new ReflectionClass($class))->getFileName() : '';
        $at   = $file ? ' (' . basename(dirname($file)) . '/' . basename($file) . ')' : '';

        return "{$class}{$at}: {$problem} {$fix}";

    }

}
