<?php

namespace Digitalis\Element;

abstract class Table extends \Digitalis\Element {

    protected static $template = 'table';

    protected static $defaults = [
        'rows'           => [],
        'first_row'      => true,
        'first_col'      => false,
        'last_col'       => false,
        'last_row'       => false,
        'row_classes'    => [],
        'row_atts'       => [],
        'col_classes'    => [],
        'col_atts'       => [],
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

    public static function generate_shelf_classes (&$p, $shelf = 'col') {
    
        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as $col => &$classes) {
        
            if (!is_array($classes)) $classes = [$classes];
            $classes = implode(' ', $classes);
        
        }
    
    }

    public static function gather_shelf_atts (&$p, $shelf = 'col') {

        if ($p["{$shelf}_classes"]) foreach ($p["{$shelf}_classes"] as $col => &$classes) {
        
            if (!isset($p["{$shelf}_atts"][$col])) $p["{$shelf}_atts"][$col] = [];

            $p["{$shelf}_atts"][$col]['class'] = $classes;
        
        }
    
    }

    public static function generate_shelf_atts (&$p, $shelf = 'col') {

        $atts = [];

        if ($p["{$shelf}_atts"]) foreach ($p["{$shelf}_atts"] as $col => $col_atts) {

            if (!isset($atts[$col])) $atts[$col] = '';

            if ($col_atts) foreach ($col_atts as $att_name => $att_value) {

                $atts[$col] .= " {$att_name}='{$att_value}'";

            }

        }
        
        $p["{$shelf}_atts"] = $atts;

    }

    public static function params ($p) {

        static::generate_col_atts($p);
        static::generate_row_atts($p);

        $p = parent::params($p);

        return $p;

    }

}