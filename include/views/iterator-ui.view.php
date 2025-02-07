<?php

namespace Digitalis;

class Iterator_UI extends View {

    protected static $template = 'iterator';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/ui/";

    protected static $defaults = [
        'iterator' => null,
    ];

    protected static function condition ($p) {
    
        return ($p['iterator'] instanceof Iterator);
    
    }

    public static function params ($p) {

        if (($p['iterator'] instanceof Iterator)) {

            $p['store'] = $p['iterator']->get_store();
            $p['index'] = $p['iterator']->get_index();
            $p['total'] = $p['iterator']->get_total_items_wrap();

        }
    
        return $p;
    
    }

    protected static function before_first ($p) {
    
        echo "<style>" . file_get_contents(DIGITALIS_FRAMEWORK_PATH . '/assets/css/iterator.css') . "</style>";
    
    }

}