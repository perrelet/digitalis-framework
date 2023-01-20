<?php

namespace Digitalis;

abstract class Product_Type extends Integration {

    protected $root_file;
    protected $slug;
    protected $name;
    protected $class_path;
    protected $class_name;
    
    protected $text_domain = 'default';

    public function __construct() {

        register_activation_hook($this->root_file,  [$this, 'install_product']);
        add_action('woocommerce_loaded',            [$this, 'load_product']);
        add_filter('woocommerce_product_class',     [$this, 'product_class'], 10, 2);
        add_filter('product_type_selector',         [$this, 'product_selector']);

        add_action( "woocommerce_{$this->slug}_add_to_cart", function() { do_action('woocommerce_simple_add_to_cart'); });

        $this->init();

    }

    public function init () {}

    public function install_product () {

        if (!get_term_by('slug', $this->slug, 'product_type')) wp_insert_term($this->slug, 'product_type');

    }
    
    public function load_product () {

        include $this->class_path;

    }

    public function product_class ($class_name, $product_type) {

        if ($product_type == $this->slug) return $this->class_name;
        
        return $class_name;

    }

    public function product_selector ($types) {

        $types[$this->slug] = __($this->name, $this->text_domain);

        return $types;

    }

}