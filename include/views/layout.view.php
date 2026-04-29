<?php

namespace Digitalis;

class Layout extends View {

    use Resolvable;

    protected static $defaults = [
        'header' => Header::class,
        'body'   => null,
        'footer' => Footer::class,
        'modals' => Modals::class,
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
        <head><?php wp_head(); ?></head>
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
