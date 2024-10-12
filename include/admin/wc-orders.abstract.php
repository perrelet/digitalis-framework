<?php

namespace Digitalis;

abstract class WC_Orders_Table extends Screen_Table {

    protected $slug = 'woocommerce_page_wc-orders';
    protected $priority = 20;

    public function run () {

        add_action("manage_{$this->slug}_custom_column", [$this, 'order_column'], $this->priority, 2);
    
        return parent::run();
    
    }

    protected function get_column_hook ($slug) {
    
        return false;
    
    }

    public function order_column ($column, $order) {

        echo $this->column('', $column, $order);
    
    }

}