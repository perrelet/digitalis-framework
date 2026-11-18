<?php

namespace Digitalis;

require_once __DIR__ . '/page-loader.view.php';

class Page_Loader extends Feature {

    protected $id             = 'page-loader';
    protected $view           = Page_Loader_View::class;
    protected $content        = '';
    protected $spinner        = 'default';
    protected $color          = '#555555';
    protected $background     = '#ffffff';
    protected $speed          = 1;
    protected $entry_speed    = 1;
    protected $exit_speed     = 0;
    protected $failsafe_speed = 5;
    protected $backstop_speed = 10;

    public function get_hooks () {

        return [
            'wp_body_open'       => ['render_loader', 0],
            'wp_enqueue_scripts' => 'enqueue',
        ];

    }

    public function render_loader () {

        echo new $this->view([
            'content'        => $this->content,
            'spinner'        => $this->spinner,
            'color'          => $this->color,
            'background'     => $this->background,
            'speed'          => $this->speed,
            'entry_speed'    => $this->entry_speed,
            'exit_speed'     => $this->exit_speed,
            'failsafe_speed' => $this->failsafe_speed,
            'backstop_speed' => $this->backstop_speed,
        ]);

    }

    public function enqueue () {

        wp_enqueue_script('digitalis-page-loader', plugin_dir_url(__FILE__) . 'page-loader.js', [], null, true);

    }

}
