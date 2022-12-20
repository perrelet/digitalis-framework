<?php

namespace Digitalis;

class Oxygen extends Builder {

    public static function get_slug () {

        return "oxygen";

    }

    public static function get_name () {

        return "Oxygen Builder";

    }

    public static function is_loaded () {

        return defined("CT_VERSION");

    }

    public static function is_backend () {

        return self::is_loaded() && defined("SHOW_CT_BUILDER");

    }

    public static function is_backend_iframe () {

        return self::is_backend() && defined("OXYGEN_IFRAME");

    }

    //

    public static function install_classes () {

        $existing_classes = get_option('ct_components_classes', []);
        $existing_folders = get_option('ct_style_folders', []);

        $folder_name = 'digitalis';

        $classes = [];

        foreach (self::$utility_classes as $class) {

            if (isset($existing_classes[$class])) {

                $existing_classes[$class]['parent'] = $folder_name;
                $existing_classes[$class]['original']['selector-locked'] = 'true';

            } else {

                $classes[$class] = [
                    'parent' => $folder_name,
                    'original' => [
                        'selector-locked' => 'true',
                    ],
                ];

            }

        }

        $folders = [
            $folder_name => [
                'key'       => $folder_name,
                'status'    => 1
            ],
        ];

        $classes = array_merge($existing_classes, $classes);
        $folders = array_merge($existing_folders, $folders);

        /* jprint($classes);
        jprint($folders); */

        update_option('ct_components_classes', $classes, get_option("oxygen_options_autoload"));
        update_option('ct_style_folders', $folders);

    }

}