<?php

namespace Digitalis;

class Field extends View {

    protected static $template = 'input';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'key'            => 'field-key',
        'id'             => null,
        'type'           => 'text',
        'default'        => '',
        'value'          => null,
        'value_callback' => false,
        'classes'        => [],
        'styles'         => [],
        'row_classes'    => [],
        'row_styles'     => [],
        'options'        => [],
        'option_atts'    => [],
        'label'          => false,
        'placeholder'    => false,
        'attributes'     => [],
        'once_atts'      => [],
        'wrap'           => true,
        'width'          => 1,
    ];

    protected static $merge = [
        'classes',
        'styles',
        'row_classes',
        'row_styles',
        'attributes',
        'once_atts'
    ];

    public static function generate_classes ($classes) {

        return implode(' ', $classes);

    }

    public static function generate_styles ($css) {

        $styles = '';
        if ($css) foreach ($css as $property => $value) $styles .= "{$property}: {$value};";
        return $styles;

    }

    public static function get_field_classes (&$p) {

        if ($p['classes'] && !is_array($p['classes'])) $p['classes'] = [$p['classes']];

        $p['classes'][] = 'digitalis-field';
        $p['classes'][] = 'field';
        $p['classes'][] = "field-{$p['type']}";

    }

    public static function get_row_classes (&$p) {

        if ($p['row_classes'] && !is_array($p['row_classes'])) $p['row_classes'] = [$p['row_classes']];

        $p['row_classes'][] = 'row';
        $p['row_classes'][] = 'field-row';
        $p['row_classes'][] = "row-{$p['type']}";
        $p['row_classes'][] = "row-{$p['key']}";
        $p['row_classes'][] = "row-" . strtolower(str_replace(['_', '\\'], '-', str_replace('Digitalis\\Field\\', '', static::class)));

    }

    public static function get_field_styles (&$p) {

        // ..

    }

    public static function get_row_styles (&$p) {

        if ($p['width'] != 1) $p['row_styles']['flex'] = $p['width'];

    }

    public static function generate_attributes ($attributes) {

        $html = '';
        if ($attributes) foreach ($attributes as $att_name => $att_value) $html .= " {$att_name}='{$att_value}'";
        return $html;

    }

    public static function get_attributes (&$p) {

        $p['attributes']['class']         = $p['classes'];
        $p['attributes']['style']         = $p['styles'];
        $p['attributes']['data-field-id'] = $p['id'];

        if ($p['placeholder']) $p['attributes']['placeholder'] = $p['placeholder'];

    }

    public static function get_once_attributes (&$p) {

        if (isset($p['condition'])) {

            $p['once_atts']['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
                'in_footer' => true,
            ]);

        }

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

        if (is_null($p['id']) && $key) $p['id'] = $key . '-field';

        if ($p['type'] == 'hidden') $p['wrap'] = false;

        $p['value'] = static::get_value($p);
        if ($p['value_callback']) $p['value'] = $p['value_callback']($p['value'], $p);

        static::get_field_classes($p);
        static::get_row_classes($p);
        static::get_field_styles($p);
        static::get_row_styles($p);

        $p['classes']     = static::generate_classes($p['classes']);
        $p['row_classes'] = static::generate_classes($p['row_classes']);
        $p['styles']      = static::generate_styles($p['styles']);
        $p['row_styles']  = static::generate_styles($p['row_styles']);

        static::get_attributes($p);
        static::get_once_attributes($p);

        $p['attributes']  = static::generate_attributes($p['attributes']);
        $p['once_atts']   = static::generate_attributes($p['once_atts']);

        return $p;

    }

    protected static function get_value ($p) {
        
        return is_null($p['value']) ? static::query_value($p['key'], $p['default']) : $p['value'];

    }

    protected static function query_value ($request_key, $default = '', $query_var = null) {

        if (is_null($query_var)) $query_var = $request_key;

        return static::sanitize_value($_REQUEST[$request_key] ?? ($query_var ? get_query_var($query_var, $default) : $default));
    
    }

    protected static function sanitize_value ($value) {
    
        return sanitize_text_field($value);
    
    }

    protected static function before ($p) {

        if ($p['wrap']) echo "<div" . ($p['id'] ? " id='{$p['id']}-row'" : '') . " class='{$p['row_classes']}' style='{$p['row_styles']}'>";

            if ($p['label']) echo "<label for='{$p['id']}'>{$p['label']}</label>";

                if ($p['wrap']) echo "<div" . ($p['id'] ? " id='{$p['id']}-wrap'" : '') . " class='field-wrap'>";

    }            

    protected static function after ($p) {

        if (!$p['wrap']) return;

            echo "</div>";
        echo "</div>";

    }

}