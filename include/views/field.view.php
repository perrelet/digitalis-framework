<?php

namespace Digitalis;

class Field extends View {

    protected static $template = 'input';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'key'           => 'field-key',
        'id'            => null,
        'type'          => 'text',
        'default'       => '',
        'value'         => null,
        'classes'       => [],
        'styles'        => [],
        'row_classes'   => [],
        'row_styles'    => [],
        'options'       => [],
        'label'         => false,
        'placeholder'   => false,
        'attributes'    => [],
        'wrap'          => true,
        'width'         => 1,
    ];
    protected static $merge = ['classes', 'styles', 'row_classes', 'row_styles', 'attributes'];

    public static function generate_classes ($classes) {

        return implode(' ', $classes);

    }

    public static function generate_styles ($css) {

        $styles = '';
        if ($css) foreach ($css as $property => $value) $styles .= "{$property}: {$value};";
        return $styles;

    }

    public static function get_field_classes ($p) {

        $classes = [
            "digitalis-field",
            "field",
            "field-{$p['type']}",
        ];

        if ($p['classes']) $classes = array_merge($classes, $p['classes']);

        return $classes;

    }

    public static function get_row_classes ($p) {

        $classes = [
            "row",
            "field-row",
            "row-{$p['type']}",
            "row-{$p['key']}",
            "row-" . strtolower(str_replace(['_', '\\'], '-', str_replace('Digitalis\\Field\\', '', static::class))),
        ];

        if ($p['row_classes']) array_merge($classes, $p['row_classes']);

        return $classes;

    }

    public static function get_field_styles ($p) {

        $styles = [];
        if ($p['styles']) array_merge($styles, $p['styles']);
        return $styles;

    }

    public static function get_row_styles ($p) {

        $styles = [];

        if ($p['width'] != 1) $styles['flex'] = $p['width'];
        if ($p['row_styles']) array_merge($styles, $p['row_styles']);

        return $styles;

    }

    public static function generate_attributes ($p) {

        $p['attributes']['class'] = $p['classes'];
        $p['attributes']['style'] = $p['styles'];

        if ($p['placeholder']) $p['attributes']['placeholder'] = $p['placeholder'];

        $attributes = '';
        if ($p['attributes']) foreach ($p['attributes'] as $att_name => $att_value) $attributes .= " {$att_name}='{$att_value}'";
        return $attributes;

    }

    protected static function generate_option_attributes ($p) {

        if ($p['option_atts']) foreach ($p['option_atts'] as &$atts) {

            $atts['html'] = '';
        
            if ($atts) foreach ($atts as $att_name => $att_value) {
            
                if ($att_name == 'html') continue;

                $atts['html'] .= " {$att_name}='{$att_value}'";
            
            }
        
        }

        return $p['option_atts'];
    
    }

    protected static function checked ($value, $current, $strict = false, $attribute = 'checked') {

        if (is_array($current)) {

            return in_array($value, $current, $strict) ? [$attribute => $attribute] : '';

        } else {

            if ($strict) {

                return $value === $current ? [$attribute => $attribute] : '';

            } else {

                return $value == $current ? [$attribute => $attribute] : '';

            }

        }

    }

    protected static function selected ($value, $current, $strict = false) {

        return static::checked($value, $current, $strict, 'selected');

    }

    public static function params ($p) {

        $key = $p['key'];

        if (is_null($p['id'])) $p['id'] = $key . '-field';

        if ($p['type'] == 'hidden') $p['wrap'] = false;

        $p['value'] = is_null($p['value']) ? ($_REQUEST[$key] ?? $p['default']) : $p['value'];

        $p['classes']       = static::generate_classes(static::get_field_classes($p));
        $p['row_classes']   = static::generate_classes(static::get_row_classes($p));
        $p['styles']        = static::generate_styles(static::get_field_styles($p));
        $p['row_styles']    = static::generate_styles(static::get_row_styles($p));
        $p['attributes']    = static::generate_attributes($p);

        return $p;

    }

    /* protected static function before_first ($p) {

        echo '<link href="https://cdn.jsdelivr.net/npm/nice-select2@2.1.0/dist/css/nice-select2.min.css" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.1/dist/css/datepicker.min.css" rel="stylesheet">';

        wp_enqueue_script('nice-select2', 'https://cdn.jsdelivr.net/npm/nice-select2@2.1.0/dist/js/nice-select2.min.js', [], false, true);
        wp_enqueue_script('vanillajs-datepicker', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.1/dist/js/datepicker-full.min.js', [], false, true);

        wp_enqueue_script('eventropy-query-module', EVENTROPY_URI . 'assets/js/eventropy-query.js', [], EVENTROPY_VERSION, true);
        wp_localize_script('eventropy-query-module', 'query_params', [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'event_list_id'     => $p['event_list_id'],
        ]);

    }  */

    protected static function before ($p) {

        if (!$p['wrap']) return;

        echo "<div class='{$p['row_classes']}' style='{$p['row_styles']}'>";

            if ($p['label']) echo "<label for='{$p['id']}'>{$p['label']}</label>";

                echo "<div class='field-wrap'>";

    }            

    protected static function after ($p) {

        if (!$p['wrap']) return;

            echo "</div>";
        echo "</div>";

    }

}