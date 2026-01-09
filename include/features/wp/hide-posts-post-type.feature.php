<?php

namespace Digitalis\WP;

class Hide_Posts_Post_Type extends \Digitalis\Feature {

    public function get_default_priority () {

        return PHP_INT_MAX;

    }

    public function run () {

        $this->add_action('admin_menu', function () {

            remove_menu_page('edit.php'); 

        });

        $this->add_action('admin_bar_menu', function ($admin_bar) {

            $admin_bar->remove_node('new-post');

        });

        $this->add_action('wp_dashboard_setup', function () {

            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
            remove_meta_box('dashboard_primary',     'dashboard', 'side');

        });

        $this->add_action('admin_init', function () {

            global $pagenow;

            $is_posts_list  = $pagenow === 'edit.php'      && (!isset($_GET['post_type']) || $_GET['post_type'] === 'post');
            $is_post_new    = $pagenow === 'post-new.php'  && (!isset($_GET['post_type']) || $_GET['post_type'] === 'post');
            $is_post_edit   = $pagenow === 'post.php'      && (isset($_GET['post']) && get_post_type((int) $_GET['post']) === 'post');

            if ($is_posts_list || $is_post_new || $is_post_edit) {
                wp_safe_redirect(admin_url());
                exit;
            }

        }, 1);

    }

}