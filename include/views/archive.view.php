<?php

namespace Digitalis;

use WP_Query;

abstract class Archive extends View {

    protected static $params = []; // Because this view invokes another view, we need this in order to correctly LSB.
    protected static $template = null;

    protected static $defaults = [
        'id'            => 'digitalis-archive',
        'classes'       => [],
        'query_vars'    => [],
        'skip_main'     => false,
        'posts_only'    => false,
        'items'         => null,
        'no_posts'      => 'No posts found.',
        'pagination'    => true,
        'paginate_args' => [],
        'loader'        => 'sliding-dots.gif',
        'loader_type'   => 'image',
        'controls'      => [],
        'post_class'    => null,
    ];

    protected static $merge = [
        'classes',
        'query_vars',
    ];

    protected static $skip_inject = [
        'post_class',
    ];

    protected static $items = [];

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

    protected static function get_items ($query_vars, &$query, $skip_main) {

        if (static::$params['post_class'] && ($call = [static::$params['post_class'], 'query']) && is_callable($call)) {

            return call_user_func_array($call, [$query_vars, &$query, $skip_main]);

        }

    }

    protected static function render_item ($item) {

        //
    
    }

    protected static function render_items ($items) {
        
        if ($items) foreach ($items as $item) static::render_item($item);
        
    }

    protected static function render_no_posts ($p) {
        
        echo "<div class='no_posts'>{$p['no_posts']}</div>";
        
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

    public static function filter_page_links (&$page_links) {
    
        //
    
    }

    public static function view ($p = []) {

        $classes = static::generate_classes(static::get_classes($p));

        if (!$p['posts_only']) {

            echo "<div id='{$p['id']}' class='$classes'>";

            if ($p['controls']) {

                Field_Group::render([
                    'fields'    => $p['controls'],
                    'id'        => "{$p['id']}-controls",
                    'classes'   => [
                        'archive-controls',
                        "{$p['id']}-controls",
                    ],
                    'tag'       => 'form',
                ]);
    
            }

            echo "<div class='digitalis-loader'>" . static::get_loader($p) . "</div>";
            echo "<div class='posts'>";

        }

        $query;

        if (static::$items = (is_null($p['items']) ? static::get_items($p['query_vars'], $query, $p['skip_main']) : $p['items'])) {

            static::render_items(static::$items);

            if ($p['pagination'] && ($query instanceof WP_Query) && ($query->max_num_pages > 1)) {

                $page_links = paginate_links(wp_parse_args($p['paginate_args'], [
                    'current'   => max(1, $query->get('paged')),
                    'total'     => $query->max_num_pages,
                    'type'      => 'array',
                ]));

                static::filter_page_links($page_links);

                if ($page_links) echo "<div class='pagination-wrap'>" . implode("\n", $page_links) . "</div>";

            }

        } else {

            static::render_no_posts($p);

        }

        if (!$p['posts_only']) {
            
            echo "</div>";
            echo "</div>";

        }

    }

}