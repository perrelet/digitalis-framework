<?php

declare(strict_types=1);

namespace Lattice;

// The failed ids as list items, for the iterator screen and for the answers its script paints. `failed` is Iterator::get_failed_items().
class Iterator_Failed_List extends View {

    protected static $template = 'iterator-failed-list';
    protected static $template_path = LATTICE_PATH . "templates/digitalis/ui/";

    protected static $defaults = [
        'failed' => ['count' => 0, 'items' => []],
    ];

}
