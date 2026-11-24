<?php

declare(strict_types=1);

namespace Lattice;

class Hook extends Utility {

    const DELIMITER = '.';

    public static function name (...$parts) {

        $flat = [];

        array_walk_recursive($parts, function ($part) use (&$flat) { $flat[] = (string) $part; });

        return static::sanitize(implode(self::DELIMITER, array_filter($flat, fn (string $s) => $s !== '')));

    }

    public static function sanitize (string $name) {

        $d = preg_quote(self::DELIMITER, '/');

        $name = str_replace('\\', self::DELIMITER, $name);
        $name = preg_replace('/[^\w' . $d . ']+/', self::DELIMITER, $name);
        $name = strtolower(trim($name, self::DELIMITER));

        return preg_replace('/' . $d . '{2,}/', self::DELIMITER, $name);

    }

    public static function filter (string|array $name, mixed $value, mixed ...$args) {

        return apply_filters(static::name($name), $value, ...$args);

    }

    public static function action (string|array $name, mixed ...$args) {

        do_action(static::name($name), ...$args);

    }

    // Legacy names: bin/lattice-1.0.php's LEGACY_HOOK_* tables hold the same 0.x names.

    private const LEGACY_EXACT = [
        'Digitalis/Instantiate/'      => 'lattice.instantiate',
        'Digitalis/DB/WPDB'           => 'lattice.db.wpdb',
        'Digitalis/Field_Group/Field' => 'lattice.field_group.field',
        'Digitalis/Form/Params'       => 'lattice.form.params',
    ];

    private const LEGACY_PREFIXES = [
        'Digitalis/Instantiate/' => 'lattice.instantiate',
        'Digitalis/Class/'       => 'lattice.class',
        'Digitalis/Post_Type/'   => 'lattice.post_type',
        'Digitalis/Taxonomy/'    => 'lattice.taxonomy',
    ];

    public static function audit_legacy_names () : void {

        Strict::begin_walk();

        foreach (array_keys($GLOBALS['wp_filter'] ?? []) as $legacy) {

            if (!$name = self::find_lattice_name((string) $legacy)) continue;

            Strict::report(self::class, "a callback is hooked to '{$legacy}', a 0.x hook name that never fires; its name is '{$name}'.", 'Rename the subscription (bin/lattice-1.0.php rewrites it).');

        }

        Strict::end_walk(true);

    }

    private static function find_lattice_name (string $legacy) : ?string {

        if (isset(self::LEGACY_EXACT[$legacy])) return self::LEGACY_EXACT[$legacy];

        foreach (self::LEGACY_PREFIXES as $old => $new) {

            if (!str_starts_with($legacy, $old)) continue;

            $tail = substr($legacy, strlen($old));

            return self::name($new, preg_match('#^Digitalis[/\\\\]#', $tail) ? 'Lattice' . substr($tail, 9) : $tail);

        }

        return null;

    }

}
