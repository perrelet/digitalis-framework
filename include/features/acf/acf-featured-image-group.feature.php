<?php

namespace Digitalis\ACF;

use Digitalis\Feature;

class Featured_Image_Group extends Feature {

    protected $field = [];

    public function run () {

        add_action('acf/include_fields', function() {

            $field = wp_parse_args($this->field, [
                'label' => 'Image',
                'type'  => 'image',
                'name'  => '_thumbnail_id',
                'key'   => '_thumbnail_id',
            ]);

            acf_add_local_field_group([
                'key'                   => 'post-featured-image',
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
                'fields'                => [$field],
            ]);
        });
        
    }
}