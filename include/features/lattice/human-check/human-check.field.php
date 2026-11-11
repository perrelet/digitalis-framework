<?php

namespace Digitalis\Field;

use Digitalis\Lattice_Human_Check;

class Human_Check extends \Digitalis\Field {

    protected static $template = null;

    protected static $defaults = [
        'action' => null,
        'label'  => false,
        'widget' => [],
    ];

    public function params (&$p) {

        $widget = Lattice_Human_Check::inst()->get_widget((string) $p['action'], (array) $p['widget']);

        $p['widget_config'] = $widget;

        parent::params($p);

        $p['element']->set_tag('div');
        $p['element']->add_class('human-check');

        foreach (['name', 'value', 'type'] as $attribute) if (isset($p['element'][$attribute])) unset($p['element'][$attribute]);

        if (!$widget) return;

        $p['element']['tabindex']                = '-1';
        $p['element']['data-human-check']        = wp_json_encode($widget);

    }

    public function condition () {

        return (bool) $this['widget_config'];

    }

    public function view () {

        echo $this['element'];
        echo wp_get_script_tag(['src' => Lattice_Human_Check::inst()->get_loader_url(), 'async' => true]);

    }

}
