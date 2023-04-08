<?php

// ACF: Gets a field from a WP_Term

if (!function_exists('get_tax_field')) {

    function get_tax_field ($selector, $term_id = false) {

        return get_field($selector, $term_id ? "term_{$term_id}" : get_queried_object());

    }

}

// ACF: Plucks a signle element from a field that returns an Array

if (!function_exists('get_field_element')) {

    function get_field_element ($selector, $key, $post_id = false, $format_value = true) {

        return ($field = get_field($selector, $post_id, $format_value)) ? (isset($field[$key]) ? $field[$key] : null) : null;
    
    }

}

// ACF: Checks if a field has a value.
// This is helpful because wp-includes/shortcodes.php throws an Array to string conversion warning for fields that return arrays when using conditions.

if (!function_exists('field_not_empty')) {

    function field_not_empty ($selector, $post_id = false, $format_value = true) {

        return !empty(get_field($selector, $post_id, $format_value));

    }

}

// UTIL: Pluck key from array or object into new array

if (!function_exists('array_pluck')) {

    function array_pluck ($array, $key) {
        return array_map(function($item) use ($key) {
            return is_array($item) ? $item[$key] : $item->$key;
        }, $array);
    }

}
