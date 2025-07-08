<?php

namespace Digitalis;

class Menu_Item extends Component {

    protected static $template = 'menu-item';

    protected static $defaults = [
        'text'          => 'Menu Item',
        'url'           => null,
        'child'         => null,
        'position'      => 'static', // static, relative, absolute, over, full-screen, left-screen, block-below, block-above,
        'aria_label'    => null,
        'role'          => 'menuitem',
        'tag'           => 'li',
        'triggers'      => ['click', 'hover', 'keys'],
        'in_delay'      => 0,
        'out_delay'     => 250,
        'a_classes'     => ['menu-item'],
        'menu_class'    => Menu::class,
        'child_wrap_classes' => ['child-wrap'],
        'close_button'  => null,     // null = auto
        'close_tag'     => 'div',
        'close_type'    => null,     // null = auto | 'all' | 'level'
        'close_position' => 'beside top right', // over | top | bottom | left | right | beside | before | after
        'close_classes' => ['close-menu-button'],
        'close_attributes' => [
            'aria-role'  => 'button',
            'tabindex'   => 0,
            'aria-label' => 'Close menu',
        ],
        'is_mobile' => false,
        'level' => 0,
    ];

    protected static $elements = ['a', 'child_wrap', 'close'];

    public function params (&$p) {

        parent::params($p);

        if (is_array($p['child'])) {

            $p['child']['menu_item_class'] = static::class;
            $p['child'] = new $p['menu_class']($p['child']);

        }

        if ($p['child']) {

            if ($p['child'] instanceof View) {

                $p['child']->set_param('level', $p['level'] + 1);

            }

            $p['a']['aria-haspopup']  = 'true';
            $p['a']['aria-expanded']  = 'false';
            $p['a']['data-in-delay']  = $p['in_delay'];
            $p['a']['data-out-delay'] = $p['out_delay'];

            if ($p['aria_label']) $p['a']['aria-label'] = $p['aria_label'];

            $p['fixed'] = in_array($p['position'], ['full-screen', 'left-screen']);
            $p['child_wrap']['data-position']  = $p['position'];
            $p['child_wrap']['data-fixed']     = $p['fixed'] ? 'true' : 'false';
            $p['child_wrap']['data-level']     = $p['level'];
            $p['child_wrap']['data-is-mobile'] = $p['is_mobile'] ? 'true' : 'false';

        } else {

            $p['fixed'] = false;

        }

        $p['attributes']['role'] = 'presentation';

        $p['a']->set_tag($p['url'] ? 'a' : 'span');
        $p['a']->set_content($p['text']);
        $p['a']['role']          = $p['role'];
        $p['a']['tabindex']      = 0;
        $p['a']['data-triggers'] = implode(' ', (array) $p['triggers']);

        if ($p['url']) $p['a']['href'] = $p['url'];

        if (is_null($p['close_button'])) $p['close_button'] = $p['fixed'];

        if ($p['close_button']) {

            if (is_null($p['close_type'])) $p['close_type'] = ($p['level'] == 1) ? 'all' : 'level';

            if ($p['close_type'] == 'all') {

                $close_js = "this.closest(`.child-wrap`).previousElementSibling.close();";

            } else {

                $close_js = "this.parentElement.previousElementSibling.close();";

            }

            $p['close']['onclick']    = $close_js;
            $p['close']['onkeypress'] = "if (event.key == `Enter`) {$close_js}";

            $p['child_wrap_attributes']['data-close-position'] = $p['close_position'];

        }
    
    }

}