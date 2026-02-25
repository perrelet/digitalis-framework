<?php

namespace Digitalis;

class ACF_Block extends Factory {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'slug';

    protected $slug = 'custom-block';
    protected $view = View::class;

    protected $block    = [
        'title'           => 'Custom Block',
        'description'     => '',
        'category'        => 'common',
        'icon'            => '',
        'mode'            => 'preview',
        'keywords'        => [],
        'supports'        => [],
        'post_types'      => [],
        'enqueue_style'   => false,
        'enqueue_script'  => false,
        'enqueue_assets'  => false,
    ];

    protected $defaults = [];
    protected $fields   = [];

    protected $field_key_map = [];

    public function __construct () {
        
        add_action('acf/init', [$this, 'register']);
        add_action('acf/include_fields', [$this, 'include_fields']);
        
    }

    public function register () {

        $block = wp_parse_args($this->block, [
            'name'              => $this->slug,
            'render_callback'   => [$this, 'render'],
            //'enqueue_style'   => get_template_directory_uri() . '/template-parts/blocks/testimonial/testimonial.css',
        ]);
        
        acf_register_block_type($block);
        
    }

    public function get_fields () {
    
        return $this->fields;
    
    }

    public function include_fields () {

        $this->field_key_map = [];

        $field_group = [
            'key'                   => "digitalis_{$this->slug}",
            'title'                 => '',
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => '',
            'active'                => true,
            'description'           => '',
            'show_in_rest'          => 0,
            'fields'                => $this->get_fields(),
            'location'  => [
                [
                    [
                        'param'     => 'block',
                        'operator'  => '==',
                        'value'     => "acf/{$this->slug}",
                    ],
                ],
            ],
        ];

        foreach ($field_group['fields'] as $key => &$field) $this->prepare_field($key, $field);
        foreach ($field_group['fields'] as &$field)         $this->rewrite_conditional_logic($field);

        if ($field_group['fields']) acf_add_local_field_group($field_group);

    }

    protected function prepare_field ($key, &$field, $path = '') {

        if (!is_array($field)) $field = [
            'label' => $field,
        ];

        $name          = $field['name'] ?? $key;
        $full_path     = $path ? "{$path}__{$name}" : $name;
        $generated_key = "digitalis_{$this->slug}_" . sanitize_key($full_path);
        //$generated_key = "field_dummy-{$key}";

        $this->field_key_map[$name]      = $this->field_key_map[$name] ?? $generated_key;
        $this->field_key_map[$full_path] = $generated_key;

        $field = wp_parse_args($field, [
            'key'               => $generated_key,
            'name'              => $name,
            'label'             => '',
            'aria-label'        => '',
            'type'              => 'text',
            'instructions'      => '',
            'required'          => 0,
            'conditional_logic' => 0,
            'default_value'     => '',
            'maxlength'         => '',
            'placeholder'       => '',
            'prepend'           => '',
            'append'            => '',
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id'    => '',
            ],
        ]);

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as $sub_key => &$sub_field) {
                $sub_name = is_array($sub_field) ? ($sub_field['name'] ?? $sub_key) : $sub_key;
                $this->prepare_field($sub_name, $sub_field, $full_path);
            }
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout_key => &$layout) {
                $layout_name = is_array($layout) ? ($layout['name'] ?? $layout_key) : $layout_key;
                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    foreach ($layout['sub_fields'] as $sub_key => &$sub_field) {
                        $sub_name = is_array($sub_field) ? ($sub_field['name'] ?? $sub_key) : $sub_key;
                        $this->prepare_field($sub_name, $sub_field, "{$full_path}__layout_{$layout_name}");
                    }
                }
            }
        }

    }

    protected function rewrite_conditional_logic (&$field) {

        if (isset($field['conditional_logic']) && is_array($field['conditional_logic'])) {

            foreach ($field['conditional_logic'] as &$group) {

                foreach ($group as &$rule) {

                    if (!isset($rule['field']) || !is_string($rule['field'])) continue;

                    $ref = $rule['field'];

                    if (str_starts_with($ref, 'digitalis_')) continue;

                    if (isset($this->field_key_map[$ref])) $rule['field'] = $this->field_key_map[$ref];

                }

            }

        }

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as &$sub_field) {
                $this->rewrite_conditional_logic($sub_field);
            }
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as &$layout) {
                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    foreach ($layout['sub_fields'] as &$sub_field) {
                        $this->rewrite_conditional_logic($sub_field);
                    }
                }
            }
        }

    }

    public function render ($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = false, $context = false) {

        $params = wp_parse_args([
            'block'      => $block,
            'content'    => $content,
            'is_preview' => $is_preview,
            'wp_block'   => $wp_block,
            'context'    => $context,
        ], $this->defaults);

        foreach ($this->get_fields() as $key => $field) {

            $value = get_field($key);
            $params[$key] = (($field['null_on_false'] ?? false) && $value === false) ? null : $value;

        }

        if ($this->view) {

            $html = call_user_func("{$this->view}::render", $params, false);

        } else {

            $html = $this->view($params);

        }

        if ($is_preview) $html = str_replace("<a", "<a onclick='return false;'", $html);

        echo $html;
        
    }

    public function view ($params = []) {

        return '';

    }

}