<?php

if (defined('LATTICE_VERSION')) return;

define('LATTICE_VERSION',       '0.4.0');
define('LATTICE_PATH',          plugin_dir_path( __FILE__ ));
define('LATTICE_LIBRARY_PATH',  plugin_dir_path( __FILE__ ) . 'include/_features/');
define('LATTICE_URI',           plugin_dir_url(__FILE__));

require LATTICE_PATH . 'compat/digitalis-namespace.php';

// Registers languages/ for the 'lattice' domain; since WP 6.7 this only records the path and the .mo loads on first use (eagerly before that; the registry exists since 6.1).
if (str_starts_with(wp_normalize_path(LATTICE_PATH), wp_normalize_path(trailingslashit(WPMU_PLUGIN_DIR)))) {
    load_muplugin_textdomain('lattice', plugin_basename(LATTICE_PATH) . '/languages');
} elseif (str_starts_with(wp_normalize_path(LATTICE_PATH), wp_normalize_path(trailingslashit(WP_PLUGIN_DIR)))) {
    load_plugin_textdomain('lattice', false, plugin_basename(LATTICE_PATH) . '/languages');
} elseif (isset($GLOBALS['wp_textdomain_registry'])) {
    $GLOBALS['wp_textdomain_registry']->set_custom_path('lattice', LATTICE_PATH . 'languages');
}

// Bootstrap: what autoload() itself reaches for before the sweep can run.
require LATTICE_PATH . 'include/core/utility.abstract.php';
require LATTICE_PATH . 'include/core/hook.utility.php';
require LATTICE_PATH . 'include/core/autoloader.trait.php';
require LATTICE_PATH . 'include/core/classmap.final.php';
require LATTICE_PATH . 'include/core/strict.final.php';
require LATTICE_PATH . 'include/core/strict-violation.logic-exception.php';
require LATTICE_PATH . 'compat/digitalis-hooks.php';

// Declared, not instantiated: the framework is a library of base classes, unlike a consuming plugin.
(new class { use Lattice\Autoloader; })
    ->use_classmap(LATTICE_PATH . '.classmap.php')
    ->autoload(LATTICE_PATH . 'include', true, 'php', $lattice_loaded, false);

if (Lattice\Strict::enabled()) Lattice\Query_Manager::strict_hooks();

add_action('plugins_loaded', function () {
//add_action('woocommerce_loaded', function () {

    require_once LATTICE_PATH . 'include/integrations/_woocommerce/is-woo-customer.trait.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/customer.user.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/order-item.model.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/order-status.post-status.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/order.abstract.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/product-type.abstract.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/woo-account-page.factory.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/woocommerce.theme.php';
    require_once LATTICE_PATH . 'include/integrations/_woocommerce/woocommerce-clean.theme.php';

}, 0);

add_action('init', function () {

    if (defined('BRICKS_VERSION')) require LATTICE_PATH . 'include/integrations/_bricks/bricks-element.abstract.php';

}, 11);

add_filter('sassy-variables', function ($variables) {

    $variables['digitalis_path'] = '"' . str_replace('\\', '/', LATTICE_PATH) . '"';
    $variables['digitalis_uri'] = '"' . str_replace('\\', '/', LATTICE_URI) . '"';

    return $variables;

});

//


if (defined('WP_CLI') && WP_CLI) {

    require LATTICE_PATH . 'include/_cli/classmap-cli.php';
    WP_CLI::add_command('lattice classmap', new Lattice\Classmap_CLI);

}
