<?php

namespace Digitalis;

abstract class Archive extends Component {

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

    public function get_content () {

        ob_start();

        if (!$this['items_only']) {

            if ($controls = $this->get_controls()) {

                Field_Group::render([
                    'fields'    => $controls,
                    'id'        => "{$this['id']}-controls",
                    'classes'   => [
                        'archive-controls',
                        "{$this['id']}-controls",
                    ],
                    'tag'       => 'form',
                ]);
    
            }

            echo  "<div class='digitalis-loader'>{$this->get_loader()}</div>";
            echo  "<div class='{$this['child_classes']['items']}'>";

        }

        $query = null;

        if (is_null($this['items'])) $this['items'] = $this->get_items($this['query_vars'], $query, $this['skip_main']);

        if ($this['items']) {

            $this->before_items();
            $this->render_items($this['items']);
            $this->after_items();

            if ($this['pagination']) {

                $page_links = $this->get_page_links($query);
                $this->filter_page_links($page_links);
                $this->render_pagination($page_links);

            }

        } else {

            if ($this['no_items'] !== false) {

                $this->render_no_items();

            }

        }

        if (!$this['items_only']) {
            
            echo  "</div>";

        }

        $content = ob_get_contents();
        ob_end_clean();

        return $content;

    }

    public function get_items ($query_vars, &$query, $skip_main) {

        if ($this['item_model'] && ($call = [$this['item_model'], 'query']) && is_callable($call)) {

            return call_user_func_array($call, [$query_vars, &$query, $skip_main]);

        }

    }

    public function before_items () {}
    public function after_items ()  {}

    public function render_items ($items) {
        
        foreach ($items as $i => $item) $this->render_item($item, $i);
        
    }

    public function render_item ($item, $i) {

        //
    
    }

    public function render_no_items () {
        
        echo "<div class='no_items'>{$this['no_items']}</div>";
        
    }

    public function get_page_links ($query) {
    
        //
    
    }

    public function filter_page_links (&$page_links) {

        //

    }

    public function render_pagination ($page_links) {

        if ($page_links) echo  "<div class='pagination-wrap'>" . implode("\n", $page_links) . "</div>";
    
    }

    public function get_controls () {
    
        return $this['controls'];
    
    }

    public function get_loader () {

        switch ($this['loader_type']) {

            case "image":

                $url = file_exists(DIGITALIS_FRAMEWORK_PATH . "assets/img/loaders/" . $this['loader']) ?
                    DIGITALIS_FRAMEWORK_URI . "assets/img/loaders/" . $this['loader'] :
                    $this['loader'];

                return "<img role='progressbar' aria-valuetext='Loading' loading='lazy' alt='Loading Icon' src='{$url}'>";

            case "file":

                return file_exists($this['loader']) ? file_get_contents($this['loader']) : '';

            case "callback":

                return is_callable($this['loader']) ? call_user_func($this['loader']) : '';

            case "html":
            default:

                return $this['loader'];

        }

    }

}