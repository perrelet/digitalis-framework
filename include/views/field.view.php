<?php

namespace Digitalis;

class Field extends Component {

    protected static $template = 'input';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'name'           => null,
        'key'            => null, // deprecated
        'id'             => null,
        'label'          => false,
        'default'        => '',
        'value'          => null,
        'value_callback' => false,
        'classes'        => ['digitalis-field', 'field'],
        'styles'         => [],
        'attributes'     => [],
        'row_classes'    => ['row', 'field-row'],
        'row_styles'     => [],
        'row_attributes' => [],
        'options'        => [],
        'option_atts'    => [],
        'placeholder'    => false,
        'once_atts'      => [],
        'wrap'           => true,
        'width'          => 1,
        'required'       => null,
        'disabled'       => null,
        'readonly'       => null,
        'form'           => null,
    ];

    protected static $merge = [
        'once_atts'
    ];

    protected static $elements = ['row', 'label', 'wrapper'];

    protected static function get_class_slug () {
    
        return strtolower(str_replace(['_', '\\'], '-', str_replace('Digitalis\\Field\\', '', static::class)));
    
    }

    public static function get_once_attributes ($p) {

        $attributes = new Attributes($p['once_atts']);

        if (isset($p['condition'])) {

            $attributes['data-field-condition'] = json_encode($p['condition']);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
                'in_footer' => true,
            ]);

        }

        return $attributes;

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

        $slug = static::get_class_slug();

        if (!$p['id']) $p['id'] = ($p['name'] ?? "{$slug}-{$p['index']}") . '-field';
        $p['attributes']['data-field-id'] = $p['id'];

        if ($p['key'])  $p['name'] = $p['key'];
        if ($p['name']) $p['attributes']['name'] = $p['name'];

        $p['value'] = static::get_value($p);
        if ($p['value_callback']) $p['value'] = $p['value_callback']($p['value'], $p);
        $p['attributes']['value'] = $p['value'];

        $p['classes'][] = "field-{$slug}";

        if ($p['wrap']) {

            $p['row_id']     = $p['id'] . '-row';
            $p['wrapper_id'] = $p['id'] . '-wrap';

            $p['row_classes'][] = "row-{$p['name']}";
            $p['row_classes'][] = "row-{$slug}";

            $p['wrapper_classes'][] = "field-wrap";

        }

        if ($p['label']) {

            $p['label_tag']               = 'label';
            $p['label_attributes']['for'] = $p['id'];
            $p['label_content']           = $p['label'];

        }

        if ($p['required'])    $p['attributes']['required']    = 'true';
        if ($p['disabled'])    $p['attributes']['disabled']    = 'true';
        if ($p['readonly'])    $p['attributes']['readonly']    = 'true';
        if ($p['form'])        $p['attributes']['form']        = $p['form'];
        if ($p['placeholder']) $p['attributes']['placeholder'] = $p['placeholder'];

        if ($p['width'] != 1) $p['row_styles']['flex'] = $p['width'];

        $p['once_atts'] = static::get_once_attributes($p);

        return parent::params($p);

    }

    protected static function get_value ($p) {
        
        return is_null($p['value']) ? static::query_value($p['name'], $p['default']) : $p['value'];

    }

    protected static function query_value ($request_key, $default = '', $query_var = null) {

        if (is_null($query_var)) $query_var = $request_key;

        return static::sanitize_value($_REQUEST[$request_key] ?? ($query_var ? get_query_var($query_var, $default) : $default));
    
    }

    protected static function sanitize_value ($value) {
    
        return sanitize_text_field($value);
    
    }

    protected static function before ($p) {

        if ($p['wrap']) {

            echo $p['row']->open();
            static::after_row_open($p);

        }

        if ($p['label']->get_content()) echo $p['label'];

        if ($p['wrap']) {

            echo $p['wrapper']->open();
            static::after_wrap_open($p);

        }

    }

    protected static function after ($p) {

        if ($p['wrap']) {

            static::before_wrap_close($p);
            echo $p['wrapper']->close(); 

            static::before_row_close($p);
            echo $p['row']->close();

        }

    }

    protected static function after_row_open    ($p) {}
    protected static function after_wrap_open   ($p) {}
    protected static function before_wrap_close ($p) {}
    protected static function before_row_close  ($p) {}

}