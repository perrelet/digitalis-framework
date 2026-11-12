<?php

declare(strict_types=1);

namespace Lattice;

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

    public function get_url ($blog_id = null) {

        $parent = str_contains((string) $this->parent, '.php') ? $this->parent : 'admin.php';

        return get_admin_url($blog_id, add_query_arg('page', $this->slug, $parent));

    }

}