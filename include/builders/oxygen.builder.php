<?php

namespace Digitalis;

class Oxygen extends Builder {

    protected $slug = 'oxygen';

    public static function instance_condition () : bool {
    
        return is_plugin_active('oxygen/functions.php');
    
    }

    public function is_backend () : bool {

        return defined("SHOW_CT_BUILDER");

    }

    public function is_backend_content () : bool {

        return $this->is_backend() && defined("OXYGEN_IFRAME");

    }

    public function is_backend_ui () : bool {

        return $this->is_backend() && !defined("OXYGEN_IFRAME");

    }

    // TODO: Implement the builder interface to its specifications

    public function get_classes () : array {
    
        $classes = get_option('ct_components_classes', []);

        return $classes;
    
    }

    // TODO: Implement the builder interface to its specifications

    public function add_classes ($install_classes, $args = []) {

        $args = $this->get_add_classes_args($args);

        $existing_classes = get_option('ct_components_classes', []);
        $existing_folders = get_option('ct_style_folders', []);

        $folder_name = $args['folder'];

        $classes = [];

        if ($install_classes) foreach ($install_classes as $class) {

            $lock = $args['lock'] ? 'true' : 'false';

            if (isset($existing_classes[$class])) {

                if ($args['overwrite']) {

                    $existing_classes[$class]['parent'] = $folder_name;
                    $existing_classes[$class]['original']['selector-locked'] = $lock;

                }

            } else {

                $classes[$class] = [
                    'parent' => $folder_name,
                    'original' => [
                        'selector-locked' => $lock,
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

    public function remove_classes ($remove_classes, $args = []) {

        $existing_classes = get_option('ct_components_classes', []);

        if ($remove_classes) foreach ($remove_classes as $class) {

            if (isset($existing_classes[$class])) unset($existing_classes[$class]);

        }

        update_option('ct_components_classes', $existing_classes, get_option("oxygen_options_autoload"));

    }

    //

    public function get_colors () : array {
    
        // TODO
    
    }

    public function add_colors ($colors, $args = []) {

        $args = wp_parse_args($args, [
            'overwrite' => true,
            'folder'    => 'digitalis',
        ]);

        if (!$existing_colors = oxy_get_global_colors()) return;

        //dprint($existing_colors);

        $set_names = wp_list_pluck($existing_colors['sets'], 'name');
        $color_names = wp_list_pluck($existing_colors['colors'], 'name');

        //dprint($set_names);
        //dprint($color_names);
        
        $set_index = array_search($args['folder'], $set_names);
        $set_id = null;

        if ($set_index === false) {

            $existing_colors['setsIncrement']++;
            $set_id = $existing_colors['setsIncrement'];

            $existing_colors['sets'][] = [
                'id'    => $set_id,
                'name'  => $args['folder'],
            ];

        } else {

            $set_id = $existing_colors['sets'][$set_index]['id'];

        }

        if ($colors) foreach ($colors as $color => $name) {
            
            $color_index = array_search($name, $color_names);

            if ($color_index === false) {

                $existing_colors['colorsIncrement']++;

                $existing_colors['colors'][] = [
                    'id'    => $existing_colors['colorsIncrement'],
                    'name'  => $name,
                    'value' => $color,
                    'set'   => $set_id,
                ];

            } else {

                if ($args['overwrite']) {

                    $existing_colors['colors'][$color_index]['value']   = $color;
                    $existing_colors['colors'][$color_index]['set']     = $set_id;

                }

            }
            
        }

        //dprint($existing_colors);

        update_option('oxygen_vsb_global_colors', $existing_colors);

    }

    public function remove_colors ($colors, $args = []) {

        if (!$existing_colors = oxy_get_global_colors()) return;

        //dprint($existing_colors);

        $set_names = wp_list_pluck($existing_colors['sets'], 'name');
        $color_names = wp_list_pluck($existing_colors['colors'], 'name');

        if ($colors) foreach ($colors as $color => $name) {

            $color_index = array_search($name, $color_names);
            if ($color_index !== false) unset($existing_colors['colors'][$color_index]);
            
        }

        // Remove empty sets

        /* $set_counts = [];
        $set_indexes = [];

        if ($sets = $existing_colors['sets']) foreach ($sets as $i => $set) {

            $set_id = $set['id'];
            $set_counts[$set_id] = 0;
            $set_indexes[$set_id] = $i;

        }

        if ($existing_colors['colors']) foreach ($existing_colors['colors'] as $color) {

            $set_id = $color['set'];
            if (isset($set_counts[$set_id])) $set_counts[$set_id]++;

        }

        if ($set_counts) foreach ($set_counts as $set_id => $set_count) {
            
            if ($set_count == 0) unset($existing_colors['sets'][$set_indexes[$set_id]]);
            
        } */

        //

        //dprint($existing_colors);

        update_option('oxygen_vsb_global_colors', $existing_colors);

    }

}