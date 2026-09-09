<?php

namespace Lattice;

class Deprecated_Hooks {

    protected static $prefixes = [
        'lattice.instantiate'        => 'Digitalis/Instantiate/',
        'lattice.class'              => 'Digitalis/Class/',
        'lattice.post_type'          => 'Digitalis/Post_Type/',
        'lattice.taxonomy'           => 'Digitalis/Taxonomy/',
    ];

    protected static $exact = [
        'lattice.instantiate'        => 'Digitalis/Instantiate/',
        'lattice.db.wpdb'            => 'Digitalis/DB/WPDB',
        'lattice.field_group.field'  => 'Digitalis/Field_Group/Field',
    ];

    public static function resolve ($name) {

        if (isset(self::$exact[$name])) return [self::$exact[$name]];

        foreach (self::$prefixes as $new => $old) {

            if (!str_starts_with($name, "{$new}.")) continue;

            $path  = self::to_pascal_path(substr($name, strlen($new) + 1));
            $names = [$old . $path];

            // Hooks naming a framework class also answered to the pre-rename namespace.
            if (str_starts_with($path, 'Lattice/')) $names[] = $old . 'Digitalis/' . substr($path, 8);

            return $names;

        }

        return [];

    }

    protected static function to_pascal_path ($tail) {

        return implode('/', array_map(fn ($segment) => ucwords($segment, '_'), explode('.', $tail)));

    }

}
