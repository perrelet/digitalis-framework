<?php

// Hide the WP admin bar on the frontend, revealing on hover.

namespace Digitalis;

class Hide_Admin_Bar extends Feature {

    public function __construct () {

        $this->add_action('wp_head', 'style');

    }

    public function style () {

        if (!is_admin_bar_showing()) return;

        ?><style>
        body.admin-bar { margin-top: -32px !important; }
        @media screen and (max-width: 782px) { body.admin-bar { margin-top: 0 !important; } }
        @media screen and (min-width: 782px) { #wpadminbar { transition: opacity 0.5s; opacity: 0; } #wpadminbar:hover { opacity: 1; } }
        </style><?php

    }

}
