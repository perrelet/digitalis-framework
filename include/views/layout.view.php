<?php

namespace Digitalis;

class Layout extends View {

    use Resolvable;

    protected static $defaults = [
        'header'   => Header::class,
        'body'     => null,
        'footer'   => Footer::class,
        'modals'   => Modals::class,
        'viewport' => 'width=device-width, initial-scale=1',
    ];

    public function params (&$p) {

        foreach ($p as &$value) {
            if (is_string($value) && class_exists($value)) {
                $value = new $value();
            }
        }

        parent::params($p);

    }

    public function view () { ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <?php if ($this['viewport']): ?><meta name="viewport" content="<?= esc_attr($this['viewport']) ?>"><?php endif; ?>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class(); ?>>
            <?php wp_body_open(); ?>
            <?php if ($this['header']) echo $this['header']; ?>
            <?php if ($this['body'])   echo $this['body']; ?>
            <?php if ($this['footer']) echo $this['footer']; ?>
            <?php if ($this['modals']) echo $this['modals']; ?>
            <?php wp_footer(); ?>
        </body>
        </html>
    <?php }

}
