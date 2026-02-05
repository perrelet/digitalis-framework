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

    public function get_js_params () {
    
        return wp_parse_args($this->js_params, [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'action'     => $this->action,
            'archive_id' => $this->archive_id,
            'selectors'  => $this->selectors,
        ]);
    
    }

    public function get_script_attributes () {
    
        return new Attributes([
            'type'         => 'module',
            'data-version' => $this->module_version,
        ]);
    
    }

    public function get_module_url () {
    
        if (!$url = $this->module_url) return;

        if ($version = $this->module_version) {
            $url = remove_query_arg('ver', $url);
            $url = add_query_arg('ver', rawurlencode($version), $url);
        }

        return $url;
    
    }

    public function get_script_js () {

        if (!$url = $this->get_module_url()) return;

        $js  = "import boot from " . json_encode($url) . ";";
        $js .= "try { boot(" . json_encode($this->get_js_params()) . "); } catch (e) { console.error(e); }";

        return $js;

    }

    public function get_script () {

        if (!$js = $this->get_script_js()) return;

        return new Element('script', $this->get_script_attributes(), $js);

    }

    public function before () {

        parent::before();

        if ($script = $this->get_script()) echo $script;

    }

}