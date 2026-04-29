<?php

namespace Digitalis;

trait Resolvable {

    protected static $context   = null;
    protected static $post_type = null;
    protected static $taxonomy  = null;
    protected static $term      = null;
    protected static $priority  = null;

    public static function get_context ()   { return static::$context;   }
    public static function get_post_type () { return static::$post_type; }
    public static function get_taxonomy ()  { return static::$taxonomy;  }
    public static function get_term ()      { return static::$term;      }
    public static function get_priority ()  { return static::$priority;  }

    public static $context_weights = [
        'archive'    => 10,
        'single'     => 20,
        'home'       => 20,
        'author'     => 30,
        'taxonomy'   => 30,
        'page'       => 30,
        'search'     => 30,
        'front_page' => 40,
        '404'        => 40,
    ];

    public static function get_specificity ($request_contexts = []) {

        if (!is_null(static::$priority)) return static::$priority;

        $s = 0;

        if (static::$context) {
            $matched = array_intersect((array) static::$context, $request_contexts);
            $s += $matched ? max(array_map(fn ($c) => static::$context_weights[$c] ?? 5, $matched)) : 0;
        }

        if (static::$post_type) $s += 10;
        if (static::$taxonomy)  $s += 10;
        if (static::$term)      $s += 10;

        return $s;

    }

}
