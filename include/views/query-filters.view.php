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
        'defaults'          => [],
        'tag'               => 'form',
        'attributes'        => [],
        'js_params'         => [],
    ];

    protected static $merge = [
        'selectors',
    ];

    public static function params ($p) {

        $p = parent::params($p);

        if (is_null($p['selectors']['archive']))    $p['selectors']['archive']  = "#{$p['archive_id']}";
        if (is_null($p['selectors']['items']))      $p['selectors']['items']    = "#{$p['archive_id']} .items";
        if (is_null($p['selectors']['controls']))   $p['selectors']['controls'] = "#{$p['archive_id']} .archive-controls";
        if (is_null($p['selectors']['form']))       $p['selectors']['form']     = "#" . $p['id'];

        return $p;
        
    }

    public static function get_fields ($fields) {

        if (static::$params['defaults']) foreach (static::$params['defaults'] as $key => $default) {
        
            if ($fields) foreach ($fields as &$field) {
            
                if (($field['key'] ?? '') != $key) continue;

                $field['default'] = $default;
            
            }
        
        }
        
        return $fields;
        
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
        wp_localize_script($handle, $p['js_params_object'], $js_params);

    }

}