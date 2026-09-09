<?php

namespace Lattice\Component;

class Menu_Drawer extends \Lattice\Component {

    protected static $template = 'menu-drawer';

    protected static $defaults = [

        // Composed Menu instance (required).
        'menu' => null,

        // Toggle button labels (swapped on open/close).
        'aria_label_open'  => 'Open menu',
        'aria_label_close' => 'Close menu',

        // Drawer position. Logical values — RTL-safe.
        'position' => 'inline-end',

        // Hide-above viewport width. Read by JS at init; CSS exposed for
        // override via the inline custom property on the drawer.
        'breakpoint' => '60rem',

        // Modal behaviours.
        'trap_focus'        => true,
        'lock_scroll'       => true,
        'close_on_navigate' => true,

        // DOM id of the drawer. Toggle's `aria-controls` references this.
        'id' => null,

    ];

    protected static $required    = ['menu'];
    protected static $skip_inject = ['menu'];

    public function params (&$p) {

        if ($p['id'] === null) $p['id'] = 'drawer-' . $this->get_index();

        parent::params($p);

    }

    public function condition () {

        return $this['menu'] instanceof Menu;

    }

    public function before_first () {

        wp_enqueue_style(
            'lattice-menu-drawer',
            LATTICE_URI . 'assets/css/menu-drawer.css',
            [],
            LATTICE_VERSION
        );

        wp_enqueue_script(
            'lattice-menu-drawer',
            LATTICE_URI . 'assets/js/menu-drawer.js',
            [],
            LATTICE_VERSION,
            ['in_footer' => true]
        );

    }

}
