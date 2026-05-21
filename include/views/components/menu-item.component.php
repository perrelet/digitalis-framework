<?php

namespace Digitalis;

class Menu_Item extends Component {

    protected static $template = 'menu-item';

    protected static $defaults = [

        // Content
        'text'          => '',
        'url'           => null,
        'submenu'       => null,
        'content'       => null,   // mega-menu panel — stage 7
        'divider'       => false,
        'heading'       => null,
        'heading_level' => 3,
        'description'   => null,
        'target'        => null,

        // Active state
        'is_current'  => null,  // null = pipeline decides; bool = explicit override
        'is_ancestor' => null,
        'object_id'   => null,
        'object_type' => null,

        // i18n
        'toggle_label_format'   => 'Toggle %s submenu',  // set by parent Menu; default for standalone use
        'external_label_format' => '%s (opens in new tab)',

        // Behaviour (set by parent Menu)
        'expand_ancestor' => false,

        // Internal / coercion-time
        'level'        => 1,
        'item_index'   => 0,
        'parent_ul_id' => null,
        'menu_class'   => Menu::class,

        // Source pass-through
        'wp_post' => null,

        // Component-base
        'tag' => 'li',

    ];

    protected static $skip_inject = ['menu_class'];

    public function params (&$p) {

        // Defensive coercion: only fires if this Menu_Item was instantiated standalone,
        // outside a parent Menu's eager coercion. In normal nested usage this is a no-op.
        if (is_array($p['submenu'])) {

            $submenu_array             = $p['submenu'];
            $submenu_array['level']    = $p['level'] + 1;
            $submenu_array['landmark'] = false;

            if (!isset($submenu_array['orientation'])) $submenu_array['orientation'] = 'vertical';

            $menu_class   = $p['menu_class'];
            $p['submenu'] = new $menu_class($submenu_array);

        }

        // Decide shape.
        $p['shape'] = $this->decide_shape($p);

        // Compute own <li> id.
        $p['li_id'] = "{$p['parent_ul_id']}-item-{$p['item_index']}";

        // Pass parent_li_id down to nested Menus (submenu and/or content-Menu).
        if ($p['submenu'] instanceof Menu) $p['submenu']->parent_li_id = $p['li_id'];
        if ($p['content'] instanceof Menu) $p['content']->parent_li_id = $p['li_id'];

        // Derived flags.
        $has_submenu    = $p['submenu'] instanceof Menu;
        $has_panel      = ($p['content'] !== null) && !$has_submenu;
        $has_link       = in_array($p['shape'], ['link', 'link_disclosure', 'link_mega'], true);
        $has_button     = in_array($p['shape'], ['disclosure', 'link_disclosure', 'mega', 'link_mega'], true);
        $button_visible = in_array($p['shape'], ['disclosure', 'mega'], true);

        $p['has_submenu']           = $has_submenu;
        $p['has_panel']             = $has_panel;
        $p['button_visible_text']   = $button_visible;
        $p['is_external']           = ($p['target'] === '_blank');
        $p['describedby_on_link']   = ($p['description'] !== null) && $has_link;
        $p['describedby_on_button'] = ($p['description'] !== null) && !$has_link && $has_button;

        // <li> element setup.
        parent::params($p);

        $p['element']['id'] = $p['li_id'];

        // Pre-init the rendered-element slots so the template can echo unconditionally.
        $p['link']           = '';
        $p['button']         = '';
        $p['description_el'] = '';
        $p['panel']          = '';

        switch ($p['shape']) {

            case 'divider':

                $p['element']['role'] = 'separator';
                $p['element']->add_class('menu-divider');
                break;

            case 'heading':

                $p['element']['role'] = 'presentation';
                $p['element']->add_class('menu-heading');
                break;

            default:

                $p['element']->add_class('menu-item');

                // expand_ancestor: open submenus on the path to current at initial paint.
                $p['disclosure_open'] = ($has_submenu || $has_panel)
                    && $p['expand_ancestor']
                    && ($p['is_ancestor'] === true);

                $p['element']['data-state'] = match (true) {
                    !$has_submenu && !$has_panel => 'static',
                    $p['disclosure_open']        => 'open',
                    default                      => 'closed',
                };

                $p['element']['data-current']  = ($p['is_current']  === true) ? 'true' : 'false';
                $p['element']['data-ancestor'] = ($p['is_ancestor'] === true) ? 'true' : 'false';

                if ($has_submenu) $p['element']['data-has-submenu'] = 'true';
                if ($has_panel)   $p['element']['data-has-panel']   = 'true';

                // Build interactive sub-elements.
                if ($has_link)                   $p['link']           = $this->build_link($p);
                if ($has_button)                 $p['button']         = $this->build_button($p);
                if ($p['description'] !== null)  $p['description_el'] = $this->build_description($p);
                if ($has_panel)                  $p['panel']          = $this->build_panel($p);

                break;

        }

    }

    // ------------------------------------------------------------------
    // Element builders
    //
    // Subclass Menu_Item and override these to inject icons, badges,
    // extra attributes, etc. Each returns an Element that the template
    // echoes verbatim.
    // ------------------------------------------------------------------

    protected function build_link ($p) {

        $attrs = ['href' => $p['url']];

        if ($p['is_current'] === true)  $attrs['aria-current']     = 'page';
        if ($p['describedby_on_link'])  $attrs['aria-describedby'] = "{$p['li_id']}-desc";

        if ($p['is_external']) {
            $attrs['target']     = '_blank';
            $attrs['rel']        = 'noopener noreferrer';
            $attrs['aria-label'] = sprintf($p['external_label_format'], $p['text']);
        }

        return new Element('a', $attrs, esc_html($p['text']));

    }

    protected function build_button ($p) {

        $controls_id = $p['has_panel'] ? "{$p['li_id']}-panel" : "{$p['li_id']}-submenu";

        $content  = '';
        if ($p['button_visible_text']) $content .= "<span>" . esc_html($p['text']) . "</span>";
        $content .= "<span aria-hidden='true' class='chevron'></span>";

        $attrs = [
            'type'          => 'button',
            'aria-expanded' => $p['disclosure_open'] ? 'true' : 'false',
            'aria-controls' => $controls_id,
        ];

        if (!$p['button_visible_text']) {
            $attrs['aria-label'] = sprintf($p['toggle_label_format'], $p['text']);
        }

        if ($p['describedby_on_button']) {
            $attrs['aria-describedby'] = "{$p['li_id']}-desc";
        }

        return new Element('button', $attrs, $content);

    }

    protected function build_description ($p) {

        return new Element('span', [
            'class' => ['menu-item-description'],
            'id'    => "{$p['li_id']}-desc",
        ], esc_html($p['description']));

    }

    protected function build_panel ($p) {

        return new Element('div', [
            'role'       => 'region',
            'id'         => "{$p['li_id']}-panel",
            'aria-label' => $p['text'],
        ], (string) $p['content']);

    }

    // ------------------------------------------------------------------
    // Shape decision
    // ------------------------------------------------------------------

    protected function decide_shape ($p) {

        if ($p['divider'])          return 'divider';
        if ($p['heading'] !== null) return 'heading';

        $has_url     = ($p['url'] !== null) && ($p['url'] !== '');
        $has_submenu = $p['submenu'] instanceof Menu;
        $has_content = ($p['content'] !== null) && !$has_submenu;

        return match (true) {
            $has_url && $has_submenu => 'link_disclosure',
            $has_url && $has_content => 'link_mega',
            $has_submenu             => 'disclosure',
            $has_content             => 'mega',
            $has_url                 => 'link',
            default                  => 'link',  // empty; condition() filters
        };

    }

    public function condition () {

        if ($this['divider'])          return true;
        if ($this['heading'] !== null) return true;
        if ($this['content'] !== null) return true;
        if (!empty($this['text']))     return true;

        return false;

    }

}
