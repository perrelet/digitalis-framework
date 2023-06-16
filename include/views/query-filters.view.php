<?php

namespace Digitalis;

abstract class Query_Filters extends Field_Group {

    protected static $defaults = [
        'archive_id'        => 'digitalis-archive',
        'selectors'         => [
            'archive'  => null,
            'posts'    => null,
            'controls' => null,
            'form'     => null,
        ],
        'module_version'    => DIGITALIS_FRAMEWORK_VERSION,
        'module_url'        => DIGITALIS_FRAMEWORK_URI . 'modules/query.js',
        'action'            => 'query_[post_type]',
        'classes'           => ['digitalis-filters'],
        'fields'            => [],
        'tag'               => 'form',
        'attributes'        => [],
        'js_params'         => [],
    ];

    protected static $merge = [
        'selectors',
    ];

    public static function params ($p) {

        $p = parent::params($p);

        if (is_null($p['selectors']['archive']))    $p['selectors']['archive'] = "#{$p['archive_id']}";
        if (is_null($p['selectors']['posts']))      $p['selectors']['posts'] = "#{$p['archive_id']} .posts";
        if (is_null($p['selectors']['controls']))   $p['selectors']['controls'] = "#{$p['archive_id']} .archive-controls";
        if (is_null($p['selectors']['form']))       $p['selectors']['form'] = "#" . $p['id'];

        return $p;
        
    }

    protected static function before_first ($p) {

        if (!$p['module_url']) return;

        $js_params = wp_parse_args($p['js_params'], [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'action'            => $p['action'],
            'archive_id'        => $p['archive_id'],
            'selectors'         => $p['selectors'],
        ]);

        $handle = static::class . '-query-module';

        wp_enqueue_script($handle, $p['module_url'], [], $p['module_version'], true);
        wp_localize_script($handle, 'query_params', $js_params);

    }

}