<?php

namespace Digitalis;

abstract class Archive extends Component {

    protected static $params = []; // Because this view invokes another view, we need this in order to correctly LSB.

    protected static $template = 'archive';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/components/";

    protected static $defaults = [
        'id'            => 'digitalis-archive',
        'classes'       => ['digitalis-archive'],
        'query_vars'    => [],
        'skip_main'     => false,
        'items_only'    => false,
        'items'         => null,
        'no_items'      => 'No items found.',
        'pagination'    => true,
        'paginate_args' => [],
        'loader'        => 'sliding-dots.gif',
        'loader_type'   => 'image',
        'controls'      => [],
        'item_model'    => null,
        'child_classes' => [
            'items' => 'items',
        ],
    ];

    protected static $merge = [
        'classes',
        'query_vars',
    ];

    protected static $skip_inject = [
        'item_model',
    ];

    protected static $items = [];

    protected static function get_loader ($p) {

        switch ($p['loader_type']) {

            case "image":

                $url = file_exists(DIGITALIS_FRAMEWORK_PATH . "assets/img/loaders/" . $p['loader']) ?
                    DIGITALIS_FRAMEWORK_URI . "assets/img/loaders/" . $p['loader'] :
                    $p['loader'];

                return "<img role='progressbar' aria-valuetext='Loading' loading='lazy' alt='Loading Icon' src='{$url}'>";

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

        if (static::$params['item_model'] && ($call = [static::$params['item_model'], 'query']) && is_callable($call)) {

            return call_user_func_array($call, [$query_vars, &$query, $skip_main]);

        }

    }

    protected static function render_item ($item, $i) {

        //
    
    }

    protected static function before_items ($p) {}

    protected static function after_items ($p) {}

    protected static function render_items ($items) {
        
        if ($items) foreach ($items as $i => $item) static::render_item($item, $i);
        
    }

    protected static function render_no_items ($p) {
        
        echo "<div class='no_items'>{$p['no_items']}</div>";
        
    }

    protected static function get_controls ($p) {
    
        return $p['controls'];
    
    }

    protected static function get_page_links ($p, $query) {
    
        //
    
    }

    public static function filter_page_links (&$page_links) {

        //

    }

    protected static function render_pagination ($page_links) {

        if ($page_links) echo  "<div class='pagination-wrap'>" . implode("\n", $page_links) . "</div>";
    
    }

    public static function get_content ($p = []) {

        ob_start();

        if (!$p['items_only']) {

            if ($controls = static::get_controls($p)) {

                Field_Group::render([
                    'fields'    => $controls,
                    'id'        => "{$p['id']}-controls",
                    'classes'   => [
                        'archive-controls',
                        "{$p['id']}-controls",
                    ],
                    'tag'       => 'form',
                ]);
    
            }

            echo  "<div class='digitalis-loader'>" . static::get_loader($p) . "</div>";
            echo  "<div class='{$p['child_classes']['items']}'>";

        }

        $query = null;

        if (static::$items = (is_null($p['items']) ? static::get_items($p['query_vars'], $query, $p['skip_main']) : $p['items'])) {

            static::before_items($p);
            static::render_items(static::$items);
            static::after_items($p);

            if ($p['pagination']) {

                $page_links = static::get_page_links($p, $query);
                static::filter_page_links($page_links);
                static::render_pagination($page_links);

            }

        } else {

            if ($p['no_items'] !== false) {

                static::render_no_items($p);

            }

        }

        if (!$p['items_only']) {
            
            echo  "</div>";

        }

        $content = ob_get_contents();
        ob_end_clean();

        return $content;

    }

}