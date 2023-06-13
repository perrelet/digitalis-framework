<?php

/* if (!function_exists('merge_query_vars')) {

    function merge_query_vars ($query_vars, $existing_query_vars = []) {

        if ($query_vars) foreach ($query_vars as $key => $value) {

            if (isset($existing_query_vars[$key]) && ($existing_value = $existing_query_vars[$key])) {

                switch ($key) {

                    case 'post_type':
                    case 'post_status':
    
                        if (($value == 'any') || ($existing_value == 'any')) {

                            $value = 'any';
                            break;

                        }

                        if (!is_array($value)) $value = [$value];
                        if (!is_array($existing_value)) $existing_value = [$existing_value];

                        //$value = array_unique(array_merge($existing_value, $value));
                        //break;
    
                    case 'tax_query':
                    case 'meta_query':

                        //$value = array_unique(array_merge($existing_value, $value));
                        //break;

                    default:

                        if (is_array($value) && is_array($existing_value)) $value = array_unique(array_merge($existing_value, $value), SORT_REGULAR);
                    
                }

            }

            $existing_query_vars[$key] = $value;

        }

        return $existing_query_vars;

    }

}

if (!function_exists('merge_query')) {

    function merge_query ($query_vars, $query) {

        $query->query_vars = merge_query_vars($query_vars, $query->query_vars);

        return $query;

    }

} */

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