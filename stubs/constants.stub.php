<?php
// Constants the framework reads but never defines, declared for PHPStan only (scanFiles: read, never run, so the values are placeholders;
// the site-varying names are typed, not valued, through dynamicConstantNames in phpstan.neon.dist). WordPress defines the first group at
// runtime (default-constants.php, wp-settings.php, wp-config.php); the stubs package mentions them in docblocks only. load.php defines the
// LATTICE_* pair as bare calls, which PHPStan reads but does not resolve (LATTICE_VERSION and LATTICE_LIBRARY_PATH it resolves from load.php).
define('ABSPATH', '/srv/www/');
define('WP_DEBUG', false);
define('WP_PLUGIN_DIR', '/srv/www/wp-content/plugins');
define('WPMU_PLUGIN_DIR', '/srv/www/wp-content/mu-plugins');
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '');
define('EP_ROOT', 64);
define('EP_PAGES', 4096);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MONTH_IN_SECONDS', 2592000);
define('YEAR_IN_SECONDS', 31536000);
define('LATTICE_PATH', '/srv/www/wp-content/plugins/lattice/');
define('LATTICE_URI', 'https://example.test/wp-content/plugins/lattice/');
