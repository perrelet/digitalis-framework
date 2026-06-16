<?php

// Full-screen loading overlay with configurable spinner, shown during page load
// and optionally on exit. Hides on whichever of these fires first:
//
//   1. DOMContentLoaded         (primary; JS, fastest path)
//   2. failsafe_speed timeout   (JS safety net for stalled sub-resources)
//   3. CSS @keyframes auto-fade (CSS-only failsafe if JS never runs at all)
//
// The two failsafes share the same `failsafe_speed` so timings agree whether
// JS reaches us or not.

namespace Digitalis;

class Page_Loader extends Feature {

    protected $spinner        = 'default';
    protected $color          = '#555555';
    protected $background     = '#ffffff';
    protected $speed          = 1;
    protected $entry_speed    = 1;
    protected $exit_speed     = 0;
    protected $failsafe_speed = 5;

    public function __construct () {

        $this->add_action('wp_body_open',       'render_loader', 0);
        $this->add_action('wp_enqueue_scripts', 'enqueue');

    }

    public function render_loader () {

        $bg       = esc_attr($this->background);
        $entry    = (float) $this->entry_speed;
        $exit     = (float) $this->exit_speed;
        $failsafe = (float) $this->failsafe_speed;

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
                /* CSS-only failsafe: if external JS never executes (blocked by
                   an extension, network failure, syntax error in a sibling
                   script, etc.) this animation hides the loader anyway at the
                   matched timing. JS path overrides via animation:none below. */
                animation: page-loader-failsafe <?= $failsafe ?>s ease-out forwards;
            }
            @keyframes page-loader-failsafe {
                0%, 80% { opacity: 1; visibility: visible; }
                100%    { opacity: 0; visibility: hidden; }
            }
            /* JS-driven path. animation:none cancels the failsafe so the
               explicit transition takes over. */
            body.loaded #page-loader            { animation: none; opacity: 0; }
            body.loaded.loader-done #page-loader { display: none; }
            <?php if ($exit): ?>
            /* Exit animation on unload. Re-shows the loader; animation:none
               keeps the failsafe from re-engaging. */
            body.unloading #page-loader {
                animation: none;
                visibility: visible;
                opacity: 1;
                display: flex;
                transition-duration: <?= $exit ?>s;
            }
            <?php endif; ?>
            @media (prefers-reduced-motion: reduce) {
                #page-loader,
                .page-loader-spinner {
                    animation-duration:  0.01ms !important;
                    transition-duration: 0.01ms !important;
                }
                #page-loader { animation-fill-mode: forwards !important; }
            }
            @media print {
                #page-loader { display: none !important; }
            }
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
            'failsafeMs' => (int) round((float) $this->failsafe_speed * 1000),
            'exit'       => ((float) $this->exit_speed > 0) ? 1 : 0,
        ]);

    }

}
