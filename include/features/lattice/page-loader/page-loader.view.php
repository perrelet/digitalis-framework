<?php

namespace Digitalis;

class Page_Loader_View extends View {

    protected static $defaults = [
        'content'        => '',
        'spinner'        => 'default',
        'color'          => '#555555',
        'background'     => '#ffffff',
        'speed'          => 1,
        'entry_speed'    => 1,
        'exit_speed'     => 0,
        'failsafe_speed' => 5,
        'backstop_speed' => 10,
    ];

    // Subclasses change get_content() and params(); view() holds the failsafes page-loader.js relies on.
    public function view () {

        $entry    = (float) $this['entry_speed'];
        $exit     = (float) $this['exit_speed'];
        $failsafe = (float) $this['failsafe_speed'];
        $backstop = (float) $this['backstop_speed'];

        ?>
        <div id="page-loader" aria-hidden="true" data-entry="<?= esc_attr($entry) ?>" data-exit="<?= esc_attr($exit) ?>" data-failsafe="<?= esc_attr($failsafe) ?>" data-backstop="<?= esc_attr($backstop) ?>">
            <?= $this->get_content() ?>
        </div>
        <style>
            #page-loader {
                position: fixed; inset: 0; z-index: 9999;
                display: flex; align-items: center; justify-content: center;
                background: <?= esc_attr($this['background']) ?>;
                pointer-events: none;
                transition: opacity <?= $entry ?>s ease;
                animation: page-loader-failsafe <?= $failsafe ?>s ease-out forwards;
            }
            @keyframes page-loader-failsafe {
                0%, 80% { opacity: 1; visibility: visible; }
                100%    { opacity: 0; visibility: hidden; }
            }
            #page-loader[data-entry="0"]          { display: none; animation: none; }
            body.loaded #page-loader              { animation: none; opacity: 0; }
            body.loaded.loader-done #page-loader  { display: none; }
            <?php if ($exit): ?>
            body.unloading #page-loader {
                display: flex;
                visibility: visible;
                animation: page-loader-in <?= $exit ?>s ease forwards;
            }
            @keyframes page-loader-in {
                from { opacity: 0; }
                to   { opacity: 1; }
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
            <?php if (!$this['content']) echo $this->get_spinner_css(); ?>
        </style>
        <noscript><style>#page-loader { display: none !important; }</style></noscript>
        <script>document.body.classList.add('js'); document.body.classList.remove('no-js');</script>
        <?php

    }

    public function get_content () {

        return $this['content'] ? (string) $this['content'] : '<div class="page-loader-spinner"></div>';

    }

    protected function get_spinner_css () {

        $file = __DIR__ . '/spinners/' . basename($this['spinner']) . '.css';

        if (!file_exists($file)) $file = __DIR__ . '/spinners/default.css';

        return str_replace(
            ['%%COLOR%%', '%%BG%%', '%%SPEED%%'],
            [esc_attr($this['color']), esc_attr($this['background']), (float) $this['speed']],
            file_get_contents($file)
        );

    }

}
