<?php

namespace Digitalis;

class Bricks extends Builder {

    protected $slug = 'bricks';

    public static function instance_condition () : bool {
    
        $theme = wp_get_theme();

        return in_array('Bricks', [$theme->name, $theme->parent_theme]);
    
    }

    public function is_backend () : bool {

        return bricks_is_builder();

    }

    public function is_backend_content () : bool {

        return bricks_is_builder_iframe();

    }

    public function is_backend_ui () : bool {

        return bricks_is_builder_main();

    }

    // colors

    public function get_colors () : array {

        $bricks_palettes = get_option('bricks_color_palette');
        $colors          = [];

        if ($bricks_palettes) foreach ($bricks_palettes as $bricks_palette) {

            if ($bricks_palette['colors']) foreach ($bricks_palette['colors'] as $bricks_color) {
            
                $colors[] = [
                    'name'    => $bricks_color['name'],
                    'value'   => $bricks_color['raw'] ?? ($bricks_color['hex'] ?? ($bricks_color['rgb'] ?? '')),
                    'id'      => $bricks_color['id'],
                    'folder'  => [
                        'name' => $bricks_palette['name'],
                        'id'   => $bricks_palette['id'],
                    ],
                    'builder' => $this->slug,
                ];
            
            }

        }

        return $colors;

    }

    public function add_colors ($colors, $args = []) {

        $args['add'] = $colors;

        $this->modify_colors($args);

    }

    public function remove_colors ($colors, $args = []) {

        $args['remove'] = $colors;

        $this->modify_colors($args);

    }

    public function modify_colors ($args = []) {

        $args = $this->get_modify_colors_args($args);
    
        if ($args['save']) {

            $bricks_palettes = get_option('bricks_color_palette');

            if ($args['add'])    $bricks_palettes = $this->add_colors_to_option($bricks_palettes, $args['add'], $args);
            if ($args['remove']) $bricks_palettes = $this->remove_colors_from_option($bricks_palettes, $args['remove'], $args);

            update_option('bricks_color_palette', $bricks_palettes);

        } else {

            if ($args['add']) add_filter('option_bricks_color_palette', function ($bricks_palettes) use ($args) {

                return $this->add_colors_to_option($bricks_palettes, $args['add'], $args);
    
            });

            if ($args['remove']) add_filter('option_bricks_color_palette', function ($bricks_palettes) use ($args) {

                return $this->remove_colors_from_option($bricks_palettes, $args['remove'], $args);
    
            });

        }
    
    }

    protected function add_colors_to_option ($bricks_palettes, $colors, $args = []) {

        $folder_names = wp_list_pluck($bricks_palettes, 'name');
        $name_groups  = [];

        if ($bricks_palettes) foreach ($bricks_palettes as $id => $bricks_palette) {
            
            $name_groups[$id] = wp_list_pluck($bricks_palette['colors'], 'name');
            
        }

        if ($colors) foreach ($colors as $name => $color) {

            $folder_name = $color['folder'] ?? $args['folder'];
            $group_index = array_search($folder_name, $folder_names);

            if ($group_index === false) {

                $bricks_palettes[] = [
                    'name'   => $folder_name,
                    'id'     => static::generate_id($folder_name),
                    'colors' => [],
                ];

                $folder_names[] = $folder_name;
                $name_groups[]  = [$color['name']];
                $group_index    = count($name_groups) - 1;

            }

            $search = array_search($color['name'], $name_groups[$group_index]);

            if (($search !== false) && !$args['overwrite']) continue;

            $bricks_color = [
                'name' => $color['name'],
                'id'   => $color['id'] ?? static::generate_id($color['name']),
            ];

            if (strpos($color['value'], 'var(') === false) {

                $bricks_color['hex'] = $color['value'];

            } else {

                $bricks_color['raw'] = $color['value'];

            }

            if ($search === false) {

                $bricks_palettes[$group_index]['colors'][] = $bricks_color;

            } else {

                $bricks_palettes[$group_index]['colors'][$search] = $bricks_color;

            }
        
        }

        return $bricks_palettes;

    }

    protected function remove_colors_from_option ($bricks_palettes, $colors, $args = []) {
    
        $group_id = null;
        $names    = null;

        if ($bricks_palettes) foreach ($bricks_palettes as $id => $bricks_palette) {

            if ($bricks_palette['name'] == $args['folder']) {

                $group_id = $id;
                $names = wp_list_pluck($bricks_palette['colors'], 'name');
                break;

            }
            
        }

        if ($group_id && $colors) foreach ($colors as $name) {

            $search = array_search($name, $names);

            if ($search !== false) unset($bricks_palettes[$group_id]['colors'][$search]);

        }

        $bricks_palettes[$group_id]['colors'] = array_values($bricks_palettes[$group_id]['colors']); // Reindex array to avoid error in builder on `unset($bricks_vars[$search])`

        return $bricks_palettes;
    
    }

    // variables

    public function get_variables () : array {

        $bricks_vars = get_option('bricks_global_variables');
        $variables   = [];

        if ($bricks_vars) foreach ($bricks_vars as $bricks_var) {
        
            $variable = [
                'name'    => $bricks_var['name'],
                'value'   => $bricks_var['value'],
                'id'      => $bricks_var['id'],
                'builder' => $this->slug,
            ];

            if (isset($bricks_var['category'])) $variable['folder'] = $bricks_var['category'];

            $variables[] = $variable;
        
        }

        return $variables;

    }

    public function add_variables ($variables, $args = []) {

        $args['add'] = $variables;

        $this->modify_variables($args);

    }

    public function remove_variables ($variables, $args = []) {

        $args['remove'] = $variables;

        $this->modify_variables($args);

    }

    public function modify_variables ($args = []) {

        $args = $this->get_modify_variables_args($args);
    
        if ($args['save']) {

            $bricks_vars = get_option('bricks_global_variables');

            if ($args['add'])    $bricks_vars = $this->add_variables_to_option($bricks_vars, $args['add'], $args);
            if ($args['remove']) $bricks_vars = $this->remove_variables_from_option($bricks_vars, $args['remove'], $args);

            update_option('bricks_global_variables', $bricks_vars);

        } else {

            if ($args['add']) add_filter('option_bricks_global_variables', function ($bricks_vars) use ($args) {

                return $this->add_variables_to_option($bricks_vars, $args['add'], $args);
    
            });

            if ($args['remove']) add_filter('option_bricks_global_variables', function ($bricks_vars) use ($args) {

                return $this->remove_variables_from_option($bricks_vars, $args['remove'], $args);
    
            });

        }
    
    }

    protected function add_variables_to_option ($bricks_vars, $variables, $args = []) {
    
        $names        = wp_list_pluck($bricks_vars, 'name');
        $folders      = null;
        $folder_names = null;

        if ($variables) foreach ($variables as $var => $variable) {

            $search = array_search($variable['name'], $names);

            if (($search !== false) && !$args['overwrite']) continue;

            $bricks_var = [
                'name'  => $variable['name'],
                'value' => $variable['value'],
                'id'    => $variable['id'] ?? static::generate_id($variable['name']),
            ];

            $folder_name = $variable['folder'] ?? $args['folder'];

            if ($folder_name) {

                if (is_null($folders)) {

                    $folders      = $this->get_variable_folders();
                    $folder_names = wp_list_pluck($folders, 'name');

                }

                $folder_search = array_search($folder_name, $folder_names);

                if ($folder_search === false) {

                    $folder = [
                        'name' => $folder_name,
                        'id'   => static::generate_id($folder_name),
                    ];

                    $this->add_variable_folders([$folder], $args);
                    
                    $folder_names[] = $folder_name;
                    $folders[]      = $folder;

                } else {

                    $folder = $folders[$folder_search];

                }

                $bricks_var['category'] = $folder['id'];

            }

            if ($search === false) {

                $bricks_vars[] = $bricks_var;

            } else {

                $bricks_vars[$search] = $bricks_var;

            }

        }

        return $bricks_vars;

    }

    protected function remove_variables_from_option ($bricks_vars, $variables, $args = []) {
    
        $names = wp_list_pluck($bricks_vars, 'name');

        if ($variables) foreach ($variables as $name) {

            $search = array_search($name, $names);

            if ($search !== false) unset($bricks_vars[$search]);

        }

        $bricks_vars = array_values($bricks_vars); // Reindex array to avoid error in builder on `unset($bricks_vars[$search])`

        return $bricks_vars;
    
    }

    // variable folders

    public function get_variable_folders () : array {

        return get_option('bricks_global_variables_categories');

    }

    public function add_variable_folders ($folders, $args = []) {

        $args['add'] = $folders;

        $this->modify_variable_folders($args);

    }

    public function remove_variable_folders ($folders, $args = []) {

        $args['remove'] = $folders;

        $this->modify_variable_folders($args);

    }

    public function modify_variable_folders ($args = []) {

        $args = $this->get_modify_variable_folders_args($args);
    
        if ($args['save']) {

            $categories = get_option('bricks_global_variables_categories');

            if ($args['add'])    $categories = $this->add_variable_folders_to_option($categories, $args['add'], $args);
            if ($args['remove']) $categories = $this->remove_variable_folders_from_option($categories, $args['remove'], $args);

            update_option('bricks_global_variables_categories', $categories);

        } else {

            if ($args['add']) add_filter('option_bricks_global_variables_categories', function ($categories) use ($args) {

                return $this->add_variable_folders_to_option($categories, $args['add'], $args);
    
            });

            if ($args['remove']) add_filter('option_bricks_global_variables_categories', function ($categories) use ($args) {

                return $this->remove_variable_folders_from_option($categories, $args['remove'], $args);
    
            });

        }
    
    }

    protected function add_variable_folders_to_option ($bricks_cats, $folders, $args = []) {
    
        $names = wp_list_pluck($bricks_cats, 'name');

        if ($folders) foreach ($folders as $name => $folder) {

            $search = array_search($folder['name'], $names);

            if (($search !== false) && !$args['overwrite']) continue;

            $bricks_cat = [
                'name' => $folder['name'],
                'id'   => $folder['id'] ?? static::generate_id($folder['name']),
            ];

            if ($search === false) {

                $bricks_cats[] = $bricks_cat;

            } else {

                $bricks_cats[$search] = $bricks_cat;

            }

        }

        return $bricks_cats;

    }

    protected function remove_variable_folders_from_option ($bricks_cats, $folders, $args = []) {
    
        $names = wp_list_pluck($bricks_cats, 'name');

        if ($folders) foreach ($folders as $name) {

            $search = array_search($name, $names);

            if ($search !== false) unset($bricks_cats[$search]);

        }

        $bricks_cats = array_values($bricks_cats); // Reindex array to avoid error in builder on `unset($bricks_vars[$search])`

        return $bricks_cats;
    
    }

    //

    protected static function generate_id ($name) {
    
        return 'df-' . preg_replace('/[^\w-]/', '', $name);
    
    }

}