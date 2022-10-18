<?php

if (defined('DIGITALIS_FRAMEWORK_VERSION')) return;

define('DIGITALIS_FRAMEWORK_VERSION', 	'0.0.0');
define('DIGITALIS_FRAMEWORK_PATH', 	plugin_dir_path( __FILE__ ) );

require DIGITALIS_FRAMEWORK_PATH . 'has-components.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-integrations.trait.php';

require DIGITALIS_FRAMEWORK_PATH . 'base.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'post-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'taxonomy.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'component.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'integration.abstract.php';