<?php

if (defined('DIGITALIS_FRAMEWORK_VERSION')) return;

define('DIGITALIS_FRAMEWORK_VERSION',   '0.1.1');
define('DIGITALIS_FRAMEWORK_PATH',      plugin_dir_path( __FILE__ ));
define('DIGITALIS_FRAMEWORK_URI',       plugin_dir_url(__FILE__));

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/base.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/query.wp-query.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/model.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/has-instances.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/singleton.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/component.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/integration.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/theme.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/view.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/shortcode.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/task-handler.singleton.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/loaders/can-load.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/loaders/has-components.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/loaders/has-integrations.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/loaders/has-taxonomies.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/loaders/has-post-types.trait.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder-manager.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder.integration.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/admin/iterator.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/user-iterator.iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/updater.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user-taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/has-wp-post.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post.model.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/acf/bidirectional-relationship.integration.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/is-woo-customer.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order-item.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/product-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woo-account-page.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce.theme.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce-clean.theme.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/field.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/field-group.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/input.field.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/button.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox-group.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox-buttons.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/date.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/date-picker.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/radio.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/radio-buttons.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/range.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/select.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/select-nice.field.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/archive.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/query-filters.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/utils/utility.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/utils/list-utility.utility.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/functions.php';

add_filter('sassy-variables', function ($variables) {

    $variables['digitalis_path'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_PATH) . '"';
    $variables['digitalis_uri'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_URI) . '"';

    return $variables;

});