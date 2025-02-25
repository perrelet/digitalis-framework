<?php

namespace Digitalis\Component;

abstract class Table extends \Digitalis\Component {

    protected static $template = 'table';

    protected static $defaults = [
        'rows'           => [],
        'first_row'      => true,
        'first_col'      => false,
        'last_col'       => false,
        'last_row'       => false,
        'data_labels'    => false,
        'row_classes'    => [],
        'row_atts'       => [],
        'col_classes'    => [],
        'col_atts'       => [],
        'cell_atts'      => [],
        'attributes'     => [
            'role' => 'presentation',
        ],
    ];

    protected static $merge = ['row_classes', 'row_atts', 'col_classes', 'col_atts'];

    protected static function condition ($p) {
        
        return is_array($p['rows']);
    
    }

    public static function generate_col_atts (&$p) {
    
        static::generate_shelf_classes($p, 'col');
        static::gather_shelf_atts($p, 'col');
        static::generate_shelf_atts($p, 'col');
    
    }

    public static function generate_row_atts (&$p) {
    
        static::generate_shelf_classes($p, 'row');
        static::gather_shelf_atts($p, 'row');
        static::generate_shelf_atts($p, 'row');
    
    }

    public static function generate_cell_atts (&$p) {

        if ($p['data_labels'] && $p['rows']) foreach ($p['rows'] as $i => $row) {

            if ($row) foreach ($row as $j => $cell) {

                if (!isset($p['cell_atts'][$i][$j])) $p['cell_atts'][$i][$j] = [];
                if (isset($p['cell_atts'][$i][$j]['data-label'])) continue;

                $p['cell_atts'][$i][$j]['data-label'] = trim(htmlspecialchars(strip_tags($cell)));
            
            }

        }
    
        $atts = [];

        if ($p["cell_atts"]) foreach ($p["cell_atts"] as $i => $row) {

            if ($row) foreach ($row as $j => $cell_atts) {
            
                if (!isset($atts[$i]))     $atts[$i]     = [];
                if (!isset($atts[$i][$j])) $atts[$i][$j] = '';

                if ($cell_atts) foreach ($cell_atts as $att_name => $att_value) {

                    $atts[$i][$j] .= " {$att_name}='{$att_value}'";
    
                }
            
            }

        }
        
        $p["cell_atts"] = $atts;
    
    }

    public static function generate_shelf_classes (&$p, $shelf = 'col') {
    
        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as &$classes) {
        
            if (!is_array($classes)) $classes = [$classes];
            $classes = implode(' ', $classes);
        
        }
    
    }

    public static function gather_shelf_atts (&$p, $shelf = 'col') {

        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as $i => &$classes) {
        
            if (!isset($p["{$shelf}_atts"][$i])) $p["{$shelf}_atts"][$i] = [];

            $p["{$shelf}_atts"][$i]['class'] = $classes;
        
        }
    
    }

    public static function generate_shelf_atts (&$p, $shelf = 'col') {

        $atts = [];

        if ($p["{$shelf}_atts"]) foreach ($p["{$shelf}_atts"] as $i => $shelf_atts) {

            if (!isset($atts[$i])) $atts[$i] = '';

            if ($shelf_atts) foreach ($shelf_atts as $att_name => $att_value) {

                $atts[$i] .= " {$att_name}='{$att_value}'";

            }

        }
        
        $p["{$shelf}_atts"] = $atts;

    }

    public static function params ($p) {

        static::generate_col_atts($p);
        static::generate_row_atts($p);
        static::generate_cell_atts($p);

        $p = parent::params($p);

        return $p;

    }

}