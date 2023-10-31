<?php

namespace Digitalis;

abstract class Feature {

    public function get_hooks () {

        return [
            /* function () {
                add_filter('script_loader_tag',  function ($tag, $handle, $src) {
                    if (strpos($handle, '-module')) $tag = str_replace("<script", "<script type='module'", $tag);
                    return $tag;
                }, 10, 3);
            }, */
        ];

    }

    public function __construct () {

        $hooks = $this->get_hooks();
        if (!is_array($hooks)) $hooks = [$hooks];

        if ($hooks) foreach ($hooks as $closure) if (is_callable($closure)) $closure();

    }

    

}