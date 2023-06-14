<?php

namespace Digitalis;

abstract class Query_Filters extends Field_Group {

    protected static $defaults = [
        'archive_id'        => 'digitalis-archive',
        'module_version'    => DIGITALIS_FRAMEWORK_VERSION,
        'module_url'        => DIGITALIS_FRAMEWORK_URI . 'modules/query.js',
        'action'            => 'query_[post_type]',
        'fields'            => [],
        'tag'               => 'form',
        'attributes'        => [],
        'js_params'         => [],
    ];

    protected static function before_first ($p) {

        if (!$p['module_url']) return;

        $js_params = wp_parse_args($p['js_params'], [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'action'            => $p['action'],
            'archive_id'        => $p['archive_id'],
        ]);

        $handle = static::class . '-query-module';

        wp_enqueue_script($handle, $p['module_url'], [], $p['module_version'], true);
        wp_localize_script($handle, 'query_params', $js_params);

    }

}