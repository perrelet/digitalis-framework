<?php

namespace Digitalis;

abstract class Admin_Sub_Page extends Admin_Page {

    protected $parent = 'admin-menu-page';

    public function admin_menu () {

        add_submenu_page(
            $this->parent,
            $this->title,
            $this->menu_title,
            $this->capability,
            $this->slug,
            [$this, 'callback_wrap'],
            $this->position,
        );

    }

}