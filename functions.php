<?php

if (!function_exists('get_tax_field')) {

    function get_tax_field ($selector, $term_id = false) {

        return get_field($selector, $term_id ? "term_{$term_id}" : get_queried_object());

    }

}