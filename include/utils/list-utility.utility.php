<?php

namespace Digitalis;

class List_Utility extends Utility {

    public static $null_option = 'None';
    public static $null_key = 0;
    public static $list = [];

    public static function get_primary_keys () {

        return [];

    }

    public static function get_primary_labels () {

        return [];

    }

    public static function get_list ($null_option = false) {

        $primary_keys = static::get_primary_keys();
        $primary_labels = static::get_primary_labels();

        if (!$primary_keys && !$primary_labels && !$null_option) return static::$list;

        $list = [];

        if ($null_option) $list[static::$null_key] = is_string($null_option) ? $null_option : static::$null_option;

        if (!is_array($primary_keys))   $primary_keys = [$primary_keys];
        if (!is_array($primary_labels)) $primary_labels = [$primary_labels];

        if ($primary_keys)      $list = array_merge($list, static::lookup($primary_keys));
        if ($primary_labels)    $list = array_merge($list, static::reverse_lookup($primary_labels));

        $list = array_merge($list, static::$list);

        return $list;

    }

    public static function lookup ($keys) {

        if (is_array($keys)) {

            $list = [];

            if ($keys) foreach ($keys as $key) $list[$key] = static::lookup($key);

            return $list;

        } else {

            return static::$list[$keys] ?? null;

        }

    }

    public static function reverse_lookup ($labels) {

        if (is_array($labels)) {

            $list = [];

            if ($labels) foreach ($labels as $label) {

                $key = static::reverse_lookup($label);
                if ($key !== false) $list[$key] = $label;

            }

            return $list;

        } else {

            return array_search($labels, static::$list);

        }

    }

}