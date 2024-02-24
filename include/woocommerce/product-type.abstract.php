<?php

namespace Digitalis;

abstract class Product_Type extends Integration {

    protected $slug;
    protected $name;
    protected $class_path;
    protected $class_name;
    protected $data_store = 'WC_Product_Data_Store_CPT';
    
    protected $text_domain = 'default';

    public function run () {

        Task_Handler::get_instance()->add_task("install_product_{$this->slug}", [$this, 'install_product']);

        if (did_action('plugins_loaded') && !doing_action('plugins_loaded')) {
            $this->load_product();
        } else {
            add_action('plugins_loaded',            [$this, 'load_product'], PHP_INT_MAX);
        }
        
        add_filter('woocommerce_product_class',     [$this, 'product_class'], 10, 2);
        add_filter('product_type_selector',         [$this, 'product_selector']);

        if ($this->data_store) add_filter("woocommerce_data_stores", [$this, 'data_stores']);

        add_action( "woocommerce_{$this->slug}_add_to_cart", function() { do_action('woocommerce_simple_add_to_cart'); });

    }

    public function install_product () {

        if (!get_term_by('slug', $this->slug, 'product_type')) wp_insert_term($this->slug, 'product_type');

    }
    
    public function load_product () {

        if ($this->class_path) include $this->class_path;

    }

    public function product_class ($class_name, $product_type) {

        if ($product_type == $this->slug) return $this->class_name;
        
        return $class_name;

    }

    public function product_selector ($types) {

        $types[$this->slug] = __($this->name, $this->text_domain);

        return $types;

    }

    public function data_stores ($stores) {

        $stores['product-' . $this->slug] = $this->data_store;
    
        return $stores;
    
    }

}