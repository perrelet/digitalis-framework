<?php

namespace Digitalis;

use WP_Query;

abstract class Archive extends View {

    protected static $defaults = [
        'id'            => 'digitalis-archive',
        'classes'       => [],
        'query_args'    => [],
        'posts_only'    => false,
        'no_posts'      => 'No posts found.',
        'pagination'    => true,
        'loader'        => 'sliding-dots.gif',
        'loader_type'   => 'image',
    ];

    protected static $merge = [
        'classes',
        'query_args',
    ];

    protected static function get_loader ($p) {

        switch ($p['loader_type']) {

            case "image":

                $url = file_exists(DIGITALIS_FRAMEWORK_PATH . "assets/img/loaders/" . $p['loader']) ?
                    DIGITALIS_FRAMEWORK_URI . "assets/img/loaders/" . $p['loader'] :
                    $p['loader'];

                return "<img src='{$url}'>";

            case "file":

                return file_exists($p['loader']) ? file_get_contents($p['loader']) : '';

            case "callback":

                return is_callable($p['loader']) ? call_user_func($p['loader']) : '';

            case "html":
            default:

                return $p['loader'];

        }

    }

    protected static function get_items ($query_args, &$query) {

        // ..

    }

    protected static function render_item ($item) {

        // ..
    
    }

    //

    public static function get_classes ($p) {

        $classes = [
            "digitalis-archive",
        ];

        if ($p['classes']) $classes = array_merge($classes, $p['classes']);

        return $classes;

    }

    public static function generate_classes ($classes) {

        return implode(' ', $classes);

    }

    public static function view ($p = []) {

        $classes = static::generate_classes(static::get_classes($p));

        if (!$p['posts_only']) {

            echo "<div id='{$p['id']}' class='$classes'>";
            echo "<div class='digitalis-loader'>" . static::get_loader($p) . "</div>";
            echo "<div class='posts'>";

        }

        $query;

        if ($items = static::get_items($p['query_args'], $query)) {

            foreach ($items as $item) static::render_item($item);

            if ($p['pagination'] && ($query instanceof WP_Query) && ($query->max_num_pages > 1)) echo paginate_links([
                'current'   => max(1, $query->get('paged')),
                'total'     => $query->max_num_pages,
            ]);

        } else {

            echo "<div class='no_posts'>{$p['no_posts']}</div>";

        }

        if (!$p['posts_only']) {
            
            echo "</div>";
            echo "</div>";

        }

    }

}