<?php

if (defined('DIGITALIS_FRAMEWORK_VERSION')) return;

define('DIGITALIS_FRAMEWORK_VERSION',   '0.3.0');
define('DIGITALIS_FRAMEWORK_PATH',      plugin_dir_path( __FILE__ ));
define('DIGITALIS_LIBRARY_PATH',        plugin_dir_path( __FILE__ ) . 'include/features/');
define('DIGITALIS_FRAMEWORK_URI',       plugin_dir_url(__FILE__));

require DIGITALIS_FRAMEWORK_PATH . 'include/utils/utility.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/utils/call.utility.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/utils/list-utility.utility.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/traits/autoloader.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/traits/auto-instantiate.trait.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/patterns/design-pattern.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/patterns/creational.design-pattern.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/patterns/dependency-injection.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/patterns/factory.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/patterns/singleton.abstract.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/inherit-props.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/model.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/service.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/log.service.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/app.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/design-system.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/query.wp-query.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/query-vars.class.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/route.factory.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/integration.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/plugin-integration.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/feature.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/theme.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/view.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/view-route.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/acf-block.factory.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/shortcode.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/attributes.class.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/element.class.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/objects/cron-scheduler.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/objects/task-handler.singleton.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder.interface.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder-manager.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/builders/builder.integration.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/iterator.singleton.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/csv-iterator.iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/post-iterator.iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/product-iterator.post-iterator.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/iterators/user-iterator.iterator.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/admin/updater.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/admin-page.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/admin-sub-page.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/commands-page.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/logs-page.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/admin-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/screen-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/posts-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/users-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/terms-table.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/wc-orders.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/admin/meta-box.feature.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/has-wp-post.trait.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post-type.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post-status.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user-taxonomy.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user-role.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/user.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/post.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/attachment.post.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/term.model.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/options.utility.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/wordpress/transients.utility.php';

//require DIGITALIS_FRAMEWORK_PATH . 'include/features/oxygen/remove-woo-styles.feature.php';
//require DIGITALIS_FRAMEWORK_PATH . 'include/features/woo/product-gallery-fallback.feature.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/acf/bidirectional-relationship.feature.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/component.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/components/htmx.component.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/components/link.component.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/components/table.component.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/components/field-group.component.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/components/form.field-group.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/field.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/input.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/hidden.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/hidden-group.field-group.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/file.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/textarea.field.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/fields/number.field.php';
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
require DIGITALIS_FRAMEWORK_PATH . 'include/views/post-archive.archive.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/term-archive.archive.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/query-filters.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/debug.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/debug-code-block.view.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/views/iterator-ui.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/acf/acf-ajax-form.view.php';

require DIGITALIS_FRAMEWORK_PATH . 'include/functions.php';

add_action('plugins_loaded', function () {
//add_action('woocommerce_loaded', function () {

    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/is-woo-customer.trait.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order-item.model.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order-status.post-status.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/order.abstract.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/product-type.abstract.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woo-account-page.factory.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce.theme.php';
    require DIGITALIS_FRAMEWORK_PATH . 'include/woocommerce/woocommerce-clean.theme.php';

}, 0);

add_action('init', function () {

    if (defined('BRICKS_VERSION')) require DIGITALIS_FRAMEWORK_PATH . 'include/objects/bricks-element.abstract.php';

}, 11);

add_filter('sassy-variables', function ($variables) {

    $variables['digitalis_path'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_PATH) . '"';
    $variables['digitalis_uri'] = '"' . str_replace('\\', '/', DIGITALIS_FRAMEWORK_URI) . '"';

    return $variables;

});

//

require DIGITALIS_FRAMEWORK_PATH . 'include/deprecated/loaders.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/deprecated/view.abstract.php';
require DIGITALIS_FRAMEWORK_PATH . 'include/deprecated/component.view.php';