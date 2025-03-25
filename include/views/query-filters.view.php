<?php

namespace Digitalis;

abstract class Query_Filters extends Field_Group {

    protected static $defaults = [
        'archive_id'        => 'digitalis-archive',
        'selectors'         => [
            'archive'  => null,
            'items'    => null,
            'controls' => null,
            'form'     => null,
        ],
        'module_version'    => DIGITALIS_FRAMEWORK_VERSION,
        'module_url'        => DIGITALIS_FRAMEWORK_URI . 'modules/query.js',
        'action'            => 'query_[post_type]',
        'js_params_object'  => 'query_params',
        'classes'           => ['digitalis-filters'],
        'fields'            => [],
        'tag'               => 'form',
        'attributes'        => [],
        'js_params'         => [],
    ];

    protected static $merge = [
        'selectors',
    ];

    public function params (&$p) {

        parent::params($p);

        if (is_null($p['selectors']['archive']))    $p['selectors']['archive']  = "#{$p['archive_id']}";
        if (is_null($p['selectors']['items']))      $p['selectors']['items']    = "#{$p['archive_id']} .items";
        if (is_null($p['selectors']['controls']))   $p['selectors']['controls'] = "#{$p['archive_id']} .archive-controls";
        if (is_null($p['selectors']['form']))       $p['selectors']['form']     = "#" . $p['id'];
        
    }

    public function before_first () {

        parent::before_first();

        if (!$this['module_url']) return;

        $js_params = wp_parse_args($this['js_params'], [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'action'            => $this['action'],
            'archive_id'        => $this['archive_id'],
            'selectors'         => $this['selectors'],
        ]);

        $handle = static::class . '-query-module';

        wp_enqueue_script($handle, $this['module_url'], [], $this['module_version'], true);
        wp_localize_script($handle, $this['js_params_object'], $js_params);

    }

}