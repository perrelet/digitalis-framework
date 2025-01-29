<?php

namespace Digitalis {

    class Debug_Code_Block extends View {

        protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/debug/";
        protected static $template      = 'debugger-code-block';

        protected static $defaults = [
            'label' => false,
            'code'  => '',
        ];

    }

}