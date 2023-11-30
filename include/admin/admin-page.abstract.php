<?php

namespace Digitalis;

abstract class Admin_Page {

    use Factory;

    protected $slug       = 'admin-menu-page';
    protected $title      = 'Page Title';
    protected $menu_title = 'Menu Title';
    protected $capability = 'manage_options';
    protected $icon       = 'dashicons-marker';
    protected $position   = null;

    public function __construct () {
    
        add_action('admin_menu', [$this, 'admin_menu'], 1000);
    
    }

    public function admin_menu () {

        add_menu_page(
            $this->title,
            $this->menu_title,
            $this->capability,
            $this->slug,
            [$this, 'callback_wrap'],
            $this->icon,
            $this->position,
        );

    }

    public function callback_wrap () {
    
        echo "<div class='wrap'>";
            $this->callback();
        echo "</div>";
    
    }

    public function callback () {
    
        
    
    }

}