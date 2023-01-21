<?php

if (defined('DIGITALIS_FRAMEWORK_VERSION')) return;

define('DIGITALIS_FRAMEWORK_VERSION', 	'0.0.1');
define('DIGITALIS_FRAMEWORK_PATH', 	    plugin_dir_path( __FILE__ ) );

require DIGITALIS_FRAMEWORK_PATH . 'can-load.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-components.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-integrations.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-post-types.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-taxonomies.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'has-wp-post.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'is-woo-customer.trait.php';

require DIGITALIS_FRAMEWORK_PATH . 'base.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'singleton.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'post-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'component.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'integration.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'acf/bidirectional-relationship.integration.php';
require DIGITALIS_FRAMEWORK_PATH . 'product-type.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'builders/builder.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'builders/oxygen.builder.php';

require DIGITALIS_FRAMEWORK_PATH . 'updater.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'theme.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'woocommerce.theme.php';
require DIGITALIS_FRAMEWORK_PATH . 'woocommerce-clean.theme.php';

require DIGITALIS_FRAMEWORK_PATH . 'user.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'functions.php';