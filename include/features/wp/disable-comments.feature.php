<?php

namespace Digitalis\WP;

class Disable_Comments extends \Digitalis\Feature {

    public function __construct () {
    
        $this->add_action('init', function () {

            foreach (get_post_types() as $post_type) {

                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }

            }

        });

        $this->add_filter('comments_open',  '__return_false',       20, 2);
        $this->add_filter('pings_open',     '__return_false',       20, 2);
        $this->add_filter('comments_array', '__return_empty_array', 10, 2);

        $this->add_action('admin_menu', function () {

            remove_menu_page('edit-comments.php');

        });

        $this->add_action('admin_bar_menu', function ($wp_admin_bar) {

            $wp_admin_bar->remove_menu('comments');

        }, PHP_INT_MAX);

        $this->add_action('admin_init', function () {

            global $pagenow;
            
            if ($pagenow === 'edit-comments.php') {
                wp_safe_redirect(admin_url());
                exit;
            }

        });

    }

}