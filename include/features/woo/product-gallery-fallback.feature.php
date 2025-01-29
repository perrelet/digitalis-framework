<?php

namespace Digitalis\Woo;

class Product_Gallery_Fallback extends \Digitalis\Feature {

    public function run () {

        add_filter('woocommerce_product_get_image_id',                [$this, 'product_get_image_id'], 10, 2);    
        add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'single_product_image_thumbnail_html'], 10, 2);

    }

    public function product_get_image_id ($image_id, $product) {

        if (!$image_id) return $product->get_gallery_image_ids()[0] ?? null;

        return $image_id;

    }

    public function single_product_image_thumbnail_html ($html, $attachment_id) {
    
        static $ids;
        if (!$ids) $ids = [];
        if (in_array($attachment_id, $ids)) return '';

        $ids[] = $attachment_id;

        return $html;
    
    }

}