<?php

declare(strict_types=1);

namespace Lattice;

// Rows of the iterator screen's log, top to bottom: the batch's narration, its failed ids, then errors and log lines newest first.
// `batch` and `failed` are plain text; `errors` and `log` are stored lines, filtered through `tags` as they were when written.
class Iterator_Log_Lines extends View {

    protected static $template = 'iterator-log-lines';
    protected static $template_path = LATTICE_PATH . "templates/digitalis/ui/";

    protected static $defaults = [
        'batch'  => [],
        'failed' => [],
        'errors' => [],
        'log'    => [],
        'tags'   => Iterator::LOG_TAGS,
    ];

}
