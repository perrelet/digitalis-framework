<?php

namespace Digitalis;

abstract class View {

    protected static $defaults = [];
    protected static $params = [];
    protected static $template = null;
    protected static $template_path = __DIR__ . "/../../templates/";

    protected static $indexes = [];

    public static function params ($params) { return $params; }

    public static function render ($params = [], $print = true) {

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 0;
        self::$indexes[static::class]++;
        $params['index'] = self::$indexes[static::class];

        if (method_exists(static::class, 'footer') && !has_action('wp_print_footer_scripts', [static::class, 'footer'])) add_action('wp_print_footer_scripts', [static::class, 'footer']);

        if (!$print) ob_start();

        static::$params = wp_parse_args($params, static::$defaults);
        static::$params = static::params(static::$params);

        if (is_null(static::$template)) {

            static::view(static::$params);

        } else {

            $path = static::$template_path . static::$template . '.php';

            if (file_exists($path)) {

                extract(static::$params, EXTR_SKIP);
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