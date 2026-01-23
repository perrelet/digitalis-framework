<?php

namespace Digitalis;

class Turn_Off_Emojicons extends Feature {

    public function __construct () {

        add_action('init', [$this, 'disable_emojis'], 0);

        // Dequeue late, safely
        add_action('wp_enqueue_scripts',    [$this, 'dequeue_emoji_assets'], PHP_INT_MAX);
        add_action('admin_enqueue_scripts', [$this, 'dequeue_emoji_assets'], PHP_INT_MAX);
        add_action('login_enqueue_scripts', [$this, 'dequeue_emoji_assets'], PHP_INT_MAX);

    }

    public function disable_emojis () : void {

        remove_action('wp_head',             'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('embed_head',          'print_emoji_detection_script');

        remove_action('wp_print_styles',     'wp_enqueue_emoji_styles');
        remove_action('admin_print_styles',  'wp_enqueue_emoji_styles');

        remove_filter('the_content_feed',    'wp_staticize_emoji');
        remove_filter('comment_text_rss',    'wp_staticize_emoji');
        remove_filter('wp_mail',             'wp_staticize_emoji_for_email');

        add_filter('tiny_mce_plugins', function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
        });

        add_filter('option_use_smilies', '__return_false');

        add_filter('emoji_svg_url', '__return_false');

        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ($relation_type !== 'dns-prefetch') return $urls;

            // Don't call apply_filters('emoji_svg_url', ...) here, just remove known host.
            return array_values(array_filter($urls, function ($url) {
                return is_string($url) ? (strpos($url, 's.w.org') === false) : true;
            }));
        }, 10, 2);
    }

    public function dequeue_emoji_assets () : void {

        // Scripts
        wp_dequeue_script('wp-emoji-release');
        wp_deregister_script('wp-emoji-release');

        // Styles (handle name varies; cover common ones)
        foreach (['wp-emoji-styles', 'emoji'] as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }

    }

}
