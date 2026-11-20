<?php

declare(strict_types=1);

namespace Lattice;

// A write that outlives the request runs only when its stamp changed; each owner keeps its own option, and the legacy 'hash' and 'version' entries count as stamps, so no history is lost.
final class Lasting {

    public static function sync (string $option, array $stamps, callable $apply, ?callable $remove = null, ?callable $present = null) : void {

        $stored = get_option($option, []);
        if (!is_array($stored)) $stored = [];

        $dirty = false;

        foreach ($stored as $key => $entry) {

            if (array_key_exists($key, $stamps)) continue;

            if ($remove) $remove((string) $key);
            unset($stored[$key]);
            $dirty = true;

        }

        foreach ($stamps as $key => $stamp) {

            $entry = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $last  = $entry['stamp'] ?? $entry['hash'] ?? $entry['version'] ?? null;

            if (($last === $stamp) && (!$present || $present((string) $key))) continue;
            if (!$apply((string) $key, $stamp)) continue;

            $stored[$key] = ['stamp' => $stamp, 'time' => time()];
            $dirty        = true;

        }

        if ($dirty) update_option($option, $stored, false);

    }

}
