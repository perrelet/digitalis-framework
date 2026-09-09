<?php

if (defined('LATTICE_VERSION')) return;

define('LATTICE_VERSION',       '0.4.0');
define('LATTICE_PATH',          plugin_dir_path( __FILE__ ));
define('LATTICE_LIBRARY_PATH',  plugin_dir_path( __FILE__ ) . 'include/features/');
define('LATTICE_URI',           plugin_dir_url(__FILE__));

require LATTICE_PATH . 'compat/digitalis-namespace.php';

require LATTICE_PATH . 'include/core/utility.abstract.php';
require LATTICE_PATH . 'include/core/hook.utility.php';
require LATTICE_PATH . 'include/core/call.utility.php';
require LATTICE_PATH . 'include/core/list-utility.utility.php';

require LATTICE_PATH . 'compat/digitalis-hooks.php';

require LATTICE_PATH . 'include/core/autoloader.trait.php';
require LATTICE_PATH . 'include/core/auto-instantiate.trait.php';
require LATTICE_PATH . 'include/core/is-stashable.trait.php';
require LATTICE_PATH . 'include/core/resolvable.trait.php';

require LATTICE_PATH . 'include/core/design-pattern.abstract.php';
require LATTICE_PATH . 'include/core/creational.design-pattern.php';
require LATTICE_PATH . 'include/core/dependency-injection.trait.php';
require LATTICE_PATH . 'include/core/factory.abstract.php';
require LATTICE_PATH . 'include/core/singleton.abstract.php';

require LATTICE_PATH . 'include/db/db-context.final.php';
require LATTICE_PATH . 'include/db/column.final.php';
require LATTICE_PATH . 'include/db/index.final.php';
require LATTICE_PATH . 'include/db/table.abstract.php';
require LATTICE_PATH . 'include/db/table-registry.final.php';
require LATTICE_PATH . 'include/db/schema.abstract.php';
require LATTICE_PATH . 'include/db/migration.abstract.php';
require LATTICE_PATH . 'include/db/migration-logger.class.php';
require LATTICE_PATH . 'include/db/option-migration-logger.migration-logger.php';
require LATTICE_PATH . 'include/db/schema-context.final.php';
require LATTICE_PATH . 'include/db/migration-runner.final.php';

require LATTICE_PATH . 'include/integrations/acf/has-acf-fields.trait.php';

require LATTICE_PATH . 'include/models/has-wp-hooks.trait.php';
require LATTICE_PATH . 'include/models/has-wp-meta.trait.php';
require LATTICE_PATH . 'include/models/has-wp-model.trait.php';

require LATTICE_PATH . 'include/models/has-wp-comment.trait.php';
require LATTICE_PATH . 'include/models/has-wp-post.trait.php';
require LATTICE_PATH . 'include/models/has-wp-term.trait.php';
require LATTICE_PATH . 'include/models/has-wp-user.trait.php';

require LATTICE_PATH . 'include/models/inherit-props.trait.php';
require LATTICE_PATH . 'include/models/model.abstract.php';
require LATTICE_PATH . 'include/services/service.abstract.php';
require LATTICE_PATH . 'include/services/log.service.php';
require LATTICE_PATH . 'include/services/visitor.service.php';
require LATTICE_PATH . 'include/routing/request-resolver.singleton.php';
require LATTICE_PATH . 'include/core/app.abstract.php';
require LATTICE_PATH . 'include/views/design-system.abstract.php';
require LATTICE_PATH . 'include/query/query.wp-query.php';
require LATTICE_PATH . 'include/query/query-vars.class.php';
require LATTICE_PATH . 'include/query/query-manager.singleton.php';
require LATTICE_PATH . 'include/query/query-profile.factory.php';
require LATTICE_PATH . 'include/routing/rest-url-builder.singleton.php';
require LATTICE_PATH . 'include/routing/route.factory.php';
require LATTICE_PATH . 'include/hooks/feature.abstract.php';
require LATTICE_PATH . 'include/hooks/integration.abstract.php';
require LATTICE_PATH . 'include/hooks/plugin-integration.abstract.php';
require LATTICE_PATH . 'include/hooks/theme.abstract.php';
require LATTICE_PATH . 'include/views/view.abstract.php';
require LATTICE_PATH . 'include/routing/view-route.abstract.php';
require LATTICE_PATH . 'include/views/acf-block.factory.php';
require LATTICE_PATH . 'include/views/shortcode.abstract.php';
require LATTICE_PATH . 'include/views/attributes.class.php';
require LATTICE_PATH . 'include/views/element.class.php';

require LATTICE_PATH . 'include/services/cron-scheduler.abstract.php';
require LATTICE_PATH . 'include/services/task-handler.singleton.php';

require LATTICE_PATH . 'include/integrations/acf/acf-row.model.php';


require LATTICE_PATH . 'include/integrations/editors/control-mapper.interface.php';
require LATTICE_PATH . 'include/integrations/editors/control-mapper.abstract.php';
require LATTICE_PATH . 'include/integrations/editors/editor.interface.php';
require LATTICE_PATH . 'include/integrations/editors/editor.singleton.php';
require LATTICE_PATH . 'include/integrations/editors/editor-element-generator.interface.php';
require LATTICE_PATH . 'include/integrations/editors/editor-element-generator.abstract.php';
require LATTICE_PATH . 'include/integrations/editors/editor-manager.singleton.php';

require LATTICE_PATH . 'include/iterators/iterator.singleton.php';
require LATTICE_PATH . 'include/iterators/csv-iterator.iterator.php';
require LATTICE_PATH . 'include/iterators/post-iterator.iterator.php';
require LATTICE_PATH . 'include/iterators/order-iterator.iterator.php';
require LATTICE_PATH . 'include/iterators/product-iterator.post-iterator.php';
require LATTICE_PATH . 'include/iterators/user-iterator.iterator.php';

require LATTICE_PATH . 'include/admin/updater.abstract.php';
require LATTICE_PATH . 'include/admin/admin-page.abstract.php';
require LATTICE_PATH . 'include/admin/admin-sub-page.abstract.php';
require LATTICE_PATH . 'include/admin/commands-page.abstract.php';
require LATTICE_PATH . 'include/admin/logs-page.abstract.php';
require LATTICE_PATH . 'include/admin/admin-table.abstract.php';
require LATTICE_PATH . 'include/admin/screen-table.abstract.php';
require LATTICE_PATH . 'include/admin/posts-table.abstract.php';
require LATTICE_PATH . 'include/admin/users-table.abstract.php';
require LATTICE_PATH . 'include/admin/terms-table.abstract.php';
require LATTICE_PATH . 'include/admin/attachment-table.abstract.php';
require LATTICE_PATH . 'include/admin/wc-orders.abstract.php';
require LATTICE_PATH . 'include/admin/meta-box.abstract.php';
require LATTICE_PATH . 'include/admin/profile-section.abstract.php';
require LATTICE_PATH . 'include/registration/post-type.abstract.php';
require LATTICE_PATH . 'include/registration/post-status.abstract.php';
require LATTICE_PATH . 'include/registration/taxonomy.abstract.php';
require LATTICE_PATH . 'include/registration/user-taxonomy.abstract.php';
require LATTICE_PATH . 'include/registration/user-role.abstract.php';
require LATTICE_PATH . 'include/models/wp-model.model.php';
require LATTICE_PATH . 'include/models/comment.model.php';
require LATTICE_PATH . 'include/models/post.model.php';
require LATTICE_PATH . 'include/models/page.post.php';
require LATTICE_PATH . 'include/models/revision.post.php';
require LATTICE_PATH . 'include/models/attachment.post.php';
require LATTICE_PATH . 'include/models/nav-menu-item.post.php';
require LATTICE_PATH . 'include/models/term.model.php';
require LATTICE_PATH . 'include/models/nav-menu.term.php';
require LATTICE_PATH . 'include/models/user.model.php';
require LATTICE_PATH . 'include/models/options.model.php';
require LATTICE_PATH . 'include/core/transients.utility.php';

\Lattice\Post::static_init();
\Lattice\Page::static_init();
\Lattice\Revision::static_init();
\Lattice\Attachment::static_init();

//require LATTICE_PATH . 'include/features/oxygen/remove-woo-styles.feature.php';
//require LATTICE_PATH . 'include/features/woo/product-gallery-fallback.feature.php';

require LATTICE_PATH . 'include/integrations/acf/bidirectional-relationship.feature.php';

require LATTICE_PATH . 'include/views/component.view.php';
require LATTICE_PATH . 'include/views/components/htmx.component.php';
require LATTICE_PATH . 'include/views/components/link.component.php';
require LATTICE_PATH . 'include/views/components/table.component.php';
require LATTICE_PATH . 'include/views/components/field-group.component.php';
require LATTICE_PATH . 'include/views/components/form.field-group.php';
require LATTICE_PATH . 'include/views/components/menu-item.component.php';
require LATTICE_PATH . 'include/views/components/menu.component.php';
require LATTICE_PATH . 'include/views/components/menu-drawer.component.php';
require LATTICE_PATH . 'include/views/components/menu-active-state.utility.php';

require LATTICE_PATH . 'include/views/field.view.php';

require LATTICE_PATH . 'include/views/fields/input.field.php';
require LATTICE_PATH . 'include/views/fields/hidden.field.php';
require LATTICE_PATH . 'include/views/fields/hidden-group.field-group.php';
require LATTICE_PATH . 'include/views/fields/file.field.php';
require LATTICE_PATH . 'include/views/fields/password.field.php';
require LATTICE_PATH . 'include/views/fields/textarea.field.php';
require LATTICE_PATH . 'include/views/fields/number.field.php';
require LATTICE_PATH . 'include/views/fields/button.field.php';
require LATTICE_PATH . 'include/views/fields/submit.field.php';
require LATTICE_PATH . 'include/views/fields/checkbox.field.php';
require LATTICE_PATH . 'include/views/fields/checkbox-group.field.php';
require LATTICE_PATH . 'include/views/fields/checkbox-buttons.field.php';
require LATTICE_PATH . 'include/views/fields/date.field.php';
require LATTICE_PATH . 'include/views/fields/date-picker.field.php';
require LATTICE_PATH . 'include/views/fields/date-range.field.php';
require LATTICE_PATH . 'include/views/fields/radio.field.php';
require LATTICE_PATH . 'include/views/fields/radio-buttons.field.php';
require LATTICE_PATH . 'include/views/fields/range.field.php';
require LATTICE_PATH . 'include/views/fields/select.field.php';
require LATTICE_PATH . 'include/views/fields/select-nice.field.php';

require LATTICE_PATH . 'include/views/header.component.php';
require LATTICE_PATH . 'include/views/footer.component.php';
require LATTICE_PATH . 'include/views/modals.component.php';
require LATTICE_PATH . 'include/views/layout.view.php';
require LATTICE_PATH . 'include/views/page-view.view.php';

require LATTICE_PATH . 'include/views/archive.view.php';
require LATTICE_PATH . 'include/views/post-archive.archive.php';
require LATTICE_PATH . 'include/views/term-archive.archive.php';
require LATTICE_PATH . 'include/views/query-filters.view.php';
require LATTICE_PATH . 'include/views/debug.view.php';
require LATTICE_PATH . 'include/views/debug-code-block.view.php';
require LATTICE_PATH . 'include/views/iterator-ui.view.php';

require LATTICE_PATH . 'include/integrations/acf/acf-ajax-form.view.php';
require LATTICE_PATH . 'include/integrations/acf/acf-option-pages.singleton.php';

require LATTICE_PATH . 'include/functions.php';

add_action('plugins_loaded', function () {
//add_action('woocommerce_loaded', function () {

    require LATTICE_PATH . 'include/integrations/woocommerce/is-woo-customer.trait.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/customer.user.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/order-item.model.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/order-status.post-status.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/order.abstract.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/product-type.abstract.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/woo-account-page.factory.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/woocommerce.theme.php';
    require LATTICE_PATH . 'include/integrations/woocommerce/woocommerce-clean.theme.php';

}, 0);

add_action('init', function () {

    if (defined('BRICKS_VERSION')) require LATTICE_PATH . 'include/integrations/editors/bricks-element.abstract.php';

}, 11);

add_filter('sassy-variables', function ($variables) {

    $variables['digitalis_path'] = '"' . str_replace('\\', '/', LATTICE_PATH) . '"';
    $variables['digitalis_uri'] = '"' . str_replace('\\', '/', LATTICE_URI) . '"';

    return $variables;

});

//

require LATTICE_PATH . 'include/deprecated/loaders.php';
require LATTICE_PATH . 'include/deprecated/view.abstract.php';
require LATTICE_PATH . 'include/deprecated/component.view.php';
require LATTICE_PATH . 'include/deprecated/route.factory.php';