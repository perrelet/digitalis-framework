<?php

if (!function_exists('get_tax_field')) {

    function get_tax_field ($selector, $term_id = false) {

        return get_field($selector, $term_id ? "term_{$term_id}" : get_queried_object());

    }

}

if (!function_exists('get_field_element')) {

    function get_field_element ($selector, $key, $post_id = false, $format_value = true) {

        return ($field = get_field($selector, $post_id, $format_value)) ? (isset($field[$key]) ? $field[$key] : null) : null;
    
    }

}