<?php

namespace Digitalis;

class Field extends Component {

    protected static $template = 'input';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'name'              => null,
        'key'               => null, // deprecated
        'id'                => null,
        'label'             => false,
        'default'           => '',
        'value'             => null,
        'value_callback'    => false,
        'condition'         => null,
        'classes'           => ['digitalis-field', 'field'],
        'styles'            => [],
        'attributes'        => [],
        'row_classes'       => ['row', 'field-row'],
        'row_styles'        => [],
        'row_attributes'    => [],
        'options'           => [],
        'option_atts'       => [],
        'placeholder'       => false,
        'once_atts'         => [],
        'wrap'              => true,
        'after_row_open'    => '',
        'after_wrap_open'   => '',
        'before_wrap_close' => '',
        'before_row_close'  => '',
        'width'             => 1,
        'required'          => null,
        'disabled'          => null,
        'readonly'          => null,
        'form'              => null,
    ];

    protected static $merge = [
        'once_atts'
    ];

    protected static $elements = ['row', 'label', 'wrapper'];

    public function params (&$p) {

        $slug = $this->get_class_slug();

        if (!$p['id']) $p['id'] = ($p['name'] ?? "{$slug}-{$p['view_index']}") . '-field';
        $p['attributes']['data-field-id'] = $p['id'];

        if ($p['key'])  $p['name'] = $p['key'];
        if ($p['name']) $p['attributes']['name'] = $p['name'];

        $p['value'] = $this->get_value($p);
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

        $p['once_atts'] = $this->get_once_attributes($p);

        parent::params($p);

        $p['pre_once_atts'] = clone $p['attributes'];
        $p['element']->add_attrs($p['once_atts']);

    }

    public function get_once_attributes () {

        $attributes = new Attributes($this->once_atts);

        if ($this->condition) {

            $attributes['data-field-condition'] = json_encode($this->condition);

            wp_enqueue_script('digitalis-fields', DIGITALIS_FRAMEWORK_URI . "assets/js/fields.js", [], DIGITALIS_FRAMEWORK_VERSION, [
                'in_footer' => true,
            ]);

        }

        return $attributes;

    }

    public function get_class_slug () {
    
        return strtolower(str_replace(['_', '\\'], '-', str_replace('Digitalis\\Field\\', '', static::class)));
    
    }

    public function get_value () {
        
        return is_null($this['value']) ? $this->query_value($this['name'], $this['default']) : $this['value'];

    }

    protected function query_value ($request_key, $default = '', $query_var = null) {

        if (is_null($query_var)) $query_var = $request_key;

        return $this->sanitize_value($_REQUEST[$request_key] ?? ($query_var ? get_query_var($query_var, $default) : $default));
    
    }

    protected function sanitize_value ($value) {
    
        return sanitize_text_field($value);
    
    }

    protected function checked ($value, $current, $strict = false, $attribute = 'checked') {

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

    protected function selected ($value, $current, $strict = false) {

        return $this->checked($value, $current, $strict, 'selected');

    }

    public function before () {

        if ($this->wrap) {

            echo $this->row->open();
            $this->after_row_open();

        }

        if ($this->label->get_content()) echo $this->label;

        if ($this->wrap) {

            echo $this->wrapper->open();
            $this->after_wrap_open();

        }

    }

    public function after () {

        if ($this->wrap) {

            $this->before_wrap_close();
            echo $this->wrapper->close(); 

            $this->before_row_close();
            echo $this->row->close();

        }

    }

    public function after_row_open () {

        if ($this->after_row_open) echo $this->after_row_open;

    }

    public function after_wrap_open () {

        if ($this->after_wrap_open) echo $this->after_wrap_open;

    }

    public function before_wrap_close () {

        if ($this->before_wrap_close) echo $this->before_wrap_close;

    }

    public function before_row_close () {

        if ($this->before_row_close) echo $this->before_row_close;

    }

}