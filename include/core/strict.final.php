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

    // For a __call fall-through: reproduces PHP's own text for an undefined, protected or private method so the magic hides nothing.
    public static function undefined_method (object $object, string $name) : void {

        if (method_exists($object, $name)) {

            $method = new ReflectionMethod($object, $name);
            $scope  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['class'] ?? null;

            throw new \Error('Call to ' . ($method->isPrivate() ? 'private' : 'protected') . " method {$method->class}::{$name}() from " . ($scope ? "scope {$scope}" : 'global scope'));

        }

        throw new \Error('Call to undefined method ' . get_class($object) . "::{$name}()");

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
