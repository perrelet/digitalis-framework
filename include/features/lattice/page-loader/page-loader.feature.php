<?php

// Full-screen loading overlay with configurable spinner, shown during page load and optionally on exit.

namespace Digitalis;

class Page_Loader extends Feature {

    protected $spinner     = 'default';
    protected $color       = '#555555';
    protected $background  = '#ffffff';
    protected $speed       = 1;
    protected $entry_speed = 1;
    protected $exit_speed  = 0;

    public function __construct () {

        $this->add_action('wp_body_open', 'render_loader', 0);
        $this->add_action('wp_enqueue_scripts', 'enqueue');

    }

    public function render_loader () {

        $bg    = esc_attr($this->background);
        $entry = (float) $this->entry_speed;
        $exit  = (float) $this->exit_speed;

        ?>
        <div id="page-loader" aria-hidden="true">
            <div class="page-loader-spinner"></div>
        </div>
        <style>
            #page-loader {
                position: fixed; inset: 0; z-index: 9999;
                display: flex; align-items: center; justify-content: center;
                background: <?= $bg ?>;
                pointer-events: none;
                transition: opacity <?= $entry ?>s ease;
            }
            body.loaded #page-loader { opacity: 0; }
            body.loaded.loader-done #page-loader { display: none; }
            <?php if ($exit): ?>
            body.unloading #page-loader { opacity: 1; display: flex; transition-duration: <?= $exit ?>s; }
            <?php endif; ?>
            .no-js #page-loader { display: none !important; }
            <?= $this->get_spinner_css() ?>
        </style>
        <script>document.body.classList.add('js'); document.body.classList.remove('no-js');</script>
        <?php

    }

    protected function get_spinner_css () {

        $file = __DIR__ . '/spinners/' . basename($this->spinner) . '.css';

        if (!file_exists($file)) $file = __DIR__ . '/spinners/default.css';

        return str_replace(
            ['%%COLOR%%', '%%BG%%', '%%SPEED%%'],
            [esc_attr($this->color), esc_attr($this->background), (float) $this->speed],
            file_get_contents($file)
        );

    }

    public function enqueue () {

        $handle = 'digitalis-page-loader';

        wp_enqueue_script($handle, plugin_dir_url(__FILE__) . 'page-loader.js', [], null, true);

        wp_localize_script($handle, 'digitalis_page_loader', [
            'entrySpeed' => (float) $this->entry_speed,
            'exit'       => ((float) $this->exit_speed > 0) ? 1 : 0,
        ]);

    }

}
