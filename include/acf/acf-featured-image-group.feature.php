<?php

namespace Digitalis\Feature;

use Digitalis\Feature;

class ACF_Featured_Image_Group extends Feature {

    public function get_hooks () {

        return [
            function () {
                add_filter('acf/include_fields', function() {
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
                        'fields'                => [
                            [
                                'label'         => 'Image',
                                'type'          => 'image',
                                'name'          => '_thumbnail_id',
                                'key'           => '_thumbnail_id',
                            ]
                        ],
                    ]);
                });
            },
        ];
        
    }
}