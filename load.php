<?php

if (defined('DIGITALIS_FRAMEWORK_VERSION')) return;

define('DIGITALIS_FRAMEWORK_VERSION',   '0.2.0');
define('DIGITALIS_FRAMEWORK_PATH',      plugin_dir_path( __FILE__ ));
define('DIGITALIS_FRAMEWORK_URI',       plugin_dir_url(__FILE__));

require DIGITALIS_FRAMEWORK_PATH . 'include/traits/autoloader.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/deprecated/loaders.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/inherit-props.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/base.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/model.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/factory.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/singleton.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/service.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/app.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/query.wp-query.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/query-vars.class.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/route.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/component.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/integration.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/feature.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/theme.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/view.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/view-route.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/acf-block.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/shortcode.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/cron-scheduler.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/task-handler.singleton.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder-manager.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder.integration.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/iterator.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/csv-iterator.iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/post-iterator.iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/user-iterator.iterator.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/admin/updater.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/admin-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/posts-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/users-table.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post-status.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user-taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user-role.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/has-wp-post.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post.model.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/acf/bidirectional-relationship.feature.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/acf/acf-featured-image-group.feature.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/element.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/elements/htmx.element.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/elements/table.element.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/field.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/field-group.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/input.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/hidden.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/button.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/submit.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox-group.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/checkbox-buttons.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/date.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/date-picker.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/date-range.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/radio.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/radio-buttons.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/range.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/select.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/select-nice.field.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/archive.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/query-filters.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/acf/acf-ajax-form.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/utils/utility.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/utils/list-utility.utility.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/functions.php';

add_action('woocommerce_loaded', function () {

    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/is-woo-customer.trait.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order-item.model.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order-status.post-status.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order.abstract.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/product-type.abstract.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woo-account-page.abstract.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce.theme.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce-clean.theme.php';

});

add_filter('sassy-variables', function ($variables) {

    $variables['digitalis_path'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_PATH) . '"';
    $variables['digitalis_uri'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_URI) . '"';

    return $variables;

});