<?php

declare(strict_types=1);

namespace Lattice;

class Iterator_UI extends View {

    protected static $template = 'iterator';
    protected static $template_path = LATTICE_PATH . "templates/digitalis/ui/";

    protected static $defaults = [
        'iterator' => null,
        'before_description' => '',
        'after_description'  => '',
        'before_controls'    => '',
        'after_controls'     => '',
        'before_progress'    => '',
        'after_progress'     => '',
        'before_log'         => '',
        'after_log'          => '',
    ];

    public function params (array &$p) {

        if (($p['iterator'] instanceof Iterator)) {

            $iterator = $p['iterator'];

            // The total is computed once per run, not once per render.
            $p['total']   = $iterator->has_total() ? $iterator->get_total() : $iterator->refresh_total();
            $p['index']   = $iterator->get_index();
            $p['store']   = $iterator->get_store();
            $p['status']  = $iterator->get_status();
            $p['stalled'] = (bool) ($p['store']['stalled'] ?? false);

        }
    
    }

    public function condition () {
    
        return ($this['iterator'] instanceof Iterator);
    
    }

}