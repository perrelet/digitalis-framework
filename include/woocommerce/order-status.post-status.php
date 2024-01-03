<?php

namespace Digitalis;

abstract class Order_Status extends Post_Status {

    protected $slug       = 'wc-order-status';

    protected $singular   = 'Order Status';
    protected $plural     = 'Order Statuses';

    protected $position   = 0;
    protected $before     = true;

    protected $allow_edit = true;

    protected function filter_args (&$args) {

        // ...
    
    }

    public function __construct () {

        if (substr($this->slug, 0, 2) != 'wc') $this->slug = 'wc-' . $this->slug;

        $this->get_args();

        if ($this->register)   add_action('woocommerce_register_shop_order_post_statuses', [$this, 'register_order_status']);
        if ($this->add_to_ui)  $this->add_to_ui();
        if ($this->allow_edit) add_filter('wc_order_is_editable', [$this, 'wc_order_is_editable'], 9999, 2);
    
    }

    public function register_order_status ($order_statuses) {

        $order_statuses[$this->slug] = $this->args;

        return $order_statuses;

    }

    protected $wc_order_statuses = null;

    public function add_to_ui () {

        add_filter('wc_order_statuses', function ($order_statuses) {

            if (is_null($this->wc_order_statuses)) {

                if (!is_int($this->position)) $this->position = array_search($this->position, array_keys($order_statuses));
                if (!$this->before) $this->position++;

                $this->wc_order_statuses =
                    array_slice($order_statuses, 0, $this->position, true) +
                    [$this->slug => $this->args['label']] +
                    array_slice($order_statuses, $this->position, count($order_statuses) - 1, true)
                ;

            }

            return $this->wc_order_statuses;

        });
    
    }

    public function wc_order_is_editable ($allow_edit, $order) {
    
        if ($order->get_status() == str_replace('wc-', '', $this->slug)) return true;

        return $allow_edit;
    
    }

}