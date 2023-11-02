<?php

namespace Digitalis;

use WP_REST_Request;
use WP_Error;

abstract class View_Route extends Route {

    protected $view = View::class;

    /* protected function get_params () {
        
        return [
            'param' => [
                'default'           => 'Default',
                'required'          => true,
                'validate_callback' => [$this, 'validate_param'],
                'sanitize_callback' => [$this, 'sanitize_param'],
            ],
        ];
        
    } */

    public function render_view ($params) {
    
        return call_user_func("{$this->view}::render", $params, false);
    
    }

    public function callback (WP_REST_Request $request) {

        if (!is_subclass_of($this->view, View::class)) return $this->respond(new WP_Error('view-error', "\$view must be a subclass of \Digitalis\View, '{$this->view}' provided."));

        $params = [];
        if ($this->rest_args['args']) foreach ($this->rest_args['args'] as $key => $arg) $params[$key] = $request->get_param($key);

        return $this->respond($this->render_view($params));

    }

}