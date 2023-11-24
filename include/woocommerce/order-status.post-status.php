<?php

namespace Digitalis;

abstract class Order_Status extends Post_Status {

    protected $slug       = 'wc-order-status';
    protected $post_types = 'shop_order';

    protected $singular   = 'Order Status';
    protected $plural     = 'Order Statuses';

    protected $position   = 'wc-refunded';
    protected $before     = false;

    protected function filter_args (&$args) {

        // ...
    
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

                // jprint($this->wc_order_statuses);

            }

            return $this->wc_order_statuses;

        });
    
    }

}