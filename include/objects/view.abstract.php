<?php

namespace Digitalis;

abstract class View {

    protected static $defaults = [];
    protected static $template = null;
    protected static $template_path = __DIR__ . "/../../templates/";

    public static function params ($params) { return $params; }

    public static function render ($params = [], $print = true) {

        if (!$print) ob_start();

        $params = wp_parse_args($params, static::$defaults);
        $params = static::params($params);

        if (is_null(static::$template)) {

            static::view($params);

        } else {

            $path = static::$template_path . static::$template . '.php';

            if (file_exists($path)) {

                extract($params, EXTR_SKIP);
                require $path;

            }

        }

        if (!$print) {
            $html = ob_get_contents();
            ob_end_clean();
            return $html;
        }

    }

    protected static function view ($params = []) {}

}