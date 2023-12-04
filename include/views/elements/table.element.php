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
        'col_classes'    => [],
        'col_atts' => [],
        'attributes'     => [
            'role' => 'presentation',
        ],
    ];

    protected static $merge = ['col_classes', 'col_atts'];

    protected static function condition ($p) {
        
        return is_array($p['rows']);
    
    }

    public static function generate_col_classes (&$p) {
    
        if ($p['col_classes']) foreach ($p['col_classes'] as $col => &$classes) {
        
            if (!is_array($classes)) $classes = [$classes];
            $classes = implode(' ', $classes);
        
        }
    
    }

    public static function gather_col_atts (&$p) {

        if ($p['col_classes']) foreach ($p['col_classes'] as $col => &$classes) {
        
            if (!isset($p['col_atts'][$col])) $p['col_atts'][$col] = [];

            $p['col_atts'][$col]['class'] = $classes;
        
        }
    
    }

    public static function generate_col_atts (&$p) {

        $atts = [];

        if ($p['col_atts']) foreach ($p['col_atts'] as $col => $col_atts) {

            if (!isset($atts[$col])) $atts[$col] = '';

            if ($col_atts) foreach ($col_atts as $att_name => $att_value) {

                $atts[$col] .= " {$att_name}='{$att_value}'";

            }

        }
        
        $p['col_atts'] = $atts;

    }

    public static function params ($p) {

        static::generate_col_classes($p);
        static::gather_col_atts($p);
        static::generate_col_atts($p);

        $p = parent::params($p);

        return $p;

    }

}