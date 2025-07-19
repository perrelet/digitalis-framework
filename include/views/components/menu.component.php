<?php

namespace Digitalis;

class Menu extends Component {

    protected static $template = 'menu';

    protected static $defaults = [
        'id'                 => 'digitalis-menu',
        'items'              => [],
        'aria_label'         => 'Menu',
        'role'               => 'menubar',
        'direction'          => 'row',
        'z-index'            => 1,
        'mobile'             => true,
        'breakpoint'         => '1000px',
        'hamburger_params' => [
            'text'       => '<div></div><div></div><div></div>',
            'position'   => 'full-screen',
            'classes'    => ['hamburger'],
            'role'       => 'button',
            'aria_label' => null,
            'child'      => null,
            'attributes' => [
                'data-hamburger' => 'true',
            ],
        ],
        'mobile_menu_params' => [
            'direction' => 'column',
        ],
        'mobile_item_params' => [
            'triggers'   => ['click', 'keys'],
        ],
        'menu_item_class' => Menu_Item::class,
        'tag'             => 'digitalis-nav',
        'classes'         => ['digitalis-menu'],
        'is_mobile'       => false,
        'level'           => 1,
    ];

    protected static $merge    = ['hamburger_params', 'mobile_menu_params', 'mobile_item_params']; 
    protected static $elements = ['list'];

    protected static $mobile = false;

    public function params (&$p) {

        parent::params($p);

        if ($p['level'] == 1) {

            $p['items']                       = $this->get_items();
            $p['mobile_menu_params']['items'] = $this->get_mobile_items();

        }

        if (($p['view_index'] > 1) || ($p['level'] > 1)) {
            
            $p['element']->set_id("{$p['id']}-{$p['view_index']}-{$p['level']}" . ($p['is_mobile'] ? '-mobile' : ''));

        }

        $p['list']->add_style('--menu-breakpoint', $p['breakpoint']);

        foreach ($p['items'] as $i => &$item) {

            if (is_array($item)) {

                $item['menu_class'] = static::class;
                $item = new $p['menu_item_class']($item);

            }

            if (!($item instanceof View)) continue;

            $item->set_param('level', $p['level']);

        }

        $p['element']['aria-label']     = $p['aria_label'];
        $p['element']['data-is-mobile'] = $p['is_mobile'] ? 'true' : 'false';

        //$p['styles']['--z-index']          = $p['z_index'];

        $p['list']['data-level'] = $p['level'];
        $p['list']['role']       = $p['role'];

        if ($p['direction']) {

            $p['list']->add_style('--menu-direction', $p['direction']);
            $p['list']['data-direction'] = $p['direction'];

        }

        if (!$p['is_mobile'] && ($p['level'] == 1)) {

            echo "<link rel='stylesheet' href='" . DIGITALIS_FRAMEWORK_URI . "assets/css/menu.css?ver=" . DIGITALIS_FRAMEWORK_VERSION . "'>";
            echo "<script src='" . DIGITALIS_FRAMEWORK_URI . "assets/js/menu.js?ver=" . DIGITALIS_FRAMEWORK_VERSION . "'></script>";

        }

        if (!$p['is_mobile'] && $p['mobile'] && ($p['level'] == 1)) {

            $p['mobile_menu_params'] = wp_parse_args($p['mobile_menu_params'], $p);

            $p['mobile_menu_params'] = $this->walk_menus($p['mobile_menu_params'],
                function (&$menu) {
                    $menu['is_mobile'] = true;
                },
                function (&$item) use ($p) {
                    $item['is_mobile'] = true;
                    foreach ($p['mobile_item_params'] as $key => $value) {
                        $item[$key] = $value;
                    }
                }
            );

            $p['hamburger_params'] = wp_parse_args($p['mobile_item_params'], $p['hamburger_params']);

            if (is_null($p['hamburger_params']['aria_label'])) $p['hamburger_params']['aria_label'] = 'Open mobile ' . $p['aria_label'];
            if (is_null($p['hamburger_params']['child']))      $p['hamburger_params']['child'] = new static($p['mobile_menu_params']);

            $p['hamburger_params']['a_attributes']['data-carat'] = 'false';
            $p['hamburger_params']['level'] = $p['level'];

            $p['items'][] = (new $p['menu_item_class']($p['hamburger_params']));

            echo "<style>";
                echo "@media only screen and (min-width: {$p['breakpoint']}) {";
                    echo "#{$p['element']->get_id()} > ul > .hamburger { display: none; } ";
                echo "}";
                echo "@media only screen and (max-width: {$p['breakpoint']}) {";
                    echo "#{$p['element']->get_id()} > ul > *:not(.hamburger) { display: none; } ";
                echo "}";
            echo "</style>";

        }

        $type = $p['is_mobile'] ? 'mobile' : 'desktop';

        $methods = [
            "menu",
            "{$type}_menu",
            "level_{$p['level']}_menu",
            "{$type}_level_{$p['level']}_menu",
        ];
        foreach ($methods as $method) if (method_exists(static::class, $method)) (static::class)::$method($p);

        foreach ($p['items'] as &$item) {

            $type = ($p['is_mobile'] || ($item['attributes']['data-hamburger'] ?? false)) ? 'mobile' : 'desktop';

            $methods = [
                "item",
                "{$type}_item",
                "level_{$p['level']}_item",
                "level_{$p['level']}_item_{$i}",
                "{$type}_level_{$p['level']}_item",
                "{$type}_level_{$p['level']}_item_{$i}",
            ];
            foreach ($methods as $method) if (method_exists(static::class, $method)) (static::class)::$method($item);

        }
    
    }

    protected function get_items () {
    
        return $this['items'];
    
    }

    protected function get_mobile_items () {
    
        return $this['items'];
    
    }

    protected function walk_menus ($menu_or_items, $menu_callback = null, $item_callback = null) {

        if (isset($menu_or_items['items'])) {

            if ($menu_callback) $menu_callback($menu_or_items);
            $menu_or_items['items'] = $this->walk_menus($menu_or_items['items'], $menu_callback, $item_callback);

        } else {

            if (is_array($menu_or_items) && array_is_list($menu_or_items)) foreach ($menu_or_items as &$item) {

                if (isset($item['child'])) {
    
                    if ($item_callback) $item_callback($item);
                    $item['child'] = $this->walk_menus($item['child'], $menu_callback, $item_callback);
    
                }
    
            }

        }

        return $menu_or_items;
    
    }

    public function condition () {
    
        return $this->params['items'];
    
    }

}