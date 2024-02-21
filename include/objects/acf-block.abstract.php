<?php

namespace Digitalis;

abstract class ACF_Block {

    protected $slug     = 'custom-block';
    protected $view     = View::class;

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

    public function include_fields () {

        $field_group = [
            'key'                   => "group_dummy-{$this->slug}",
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
            'fields'                => [],
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

        if ($this->fields) foreach ($this->fields as $key => $field) {

            if (!is_array($field)) $field = [
                'label' => $field,
            ];
            
            $field_group['fields'][] = wp_parse_args($field, [
                'key'               => "field_dummy-{$key}",
                'name'              => $key,
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
            
        }

        if ($field_group['fields']) acf_add_local_field_group($field_group);

    }

    public function render ($block, $content = '', $is_preview = false, $post_id = 0, $wp_block = false, $context = false) {

        $params = wp_parse_args([
            'block'      => $block,
            'content'    => $content,
            'is_preview' => $is_preview,
            'wp_block'   => $wp_block,
            'context'    => $context,
        ], $this->defaults);

        if ($this->fields) foreach ($this->fields as $key => $field) {

            $params[$key] = get_field($key);

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