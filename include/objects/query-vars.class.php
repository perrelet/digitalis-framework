<?php

namespace Digitalis;

use WP_Query;

class Query_Vars implements \ArrayAccess, \IteratorAggregate, \JsonSerializable, \Countable {

    protected $query;

    public function __construct ($query_vars = []) {

        if ($query_vars instanceof WP_Query) $query_vars = $query_vars->query_vars;

        $this->set_vars($query_vars);

    }

    public function get_vars () {

        return $this->query;

    }

    public function to_array () {

        return $this->get_vars();

    }

    public function set_vars ($query_vars) {
    
        $this->query = wp_parse_args($query_vars, [
            'meta_query' => [],
            'tax_query'  => [],
        ]);

        return $this;
    
    }

    public function get ($key, $default = null) {

        return $this->query[$key] ?? $default;

    }

    public function set ($key, $value) {

        $this->query[$key] = $value;
        return $this;

    }

    public function has ($key) {
    
        return array_key_exists($key, $this->query);
    
    }

    public function remove ($key) {

        unset($this->query[$key]);
        return $this;
    
    }

    public function get_var ($key, $default = null) { return $this->get($key, $default); }
    public function set_var ($key, $value)          { return $this->set($key, $value);   }
    public function has_var ($key)                  { return $this->has($key);   }
    public function unset_var ($key)                { return $this->remove($key);        }

    public function get_meta_query () {

        return $this->query['meta_query'] ?? [];

    }

    public function get_tax_query () {

        return $this->query['tax_query'] ?? [];

    }

    public function add_meta_query ($meta_query) {

        $this->query['meta_query'][] = $meta_query;

        return $this;

    }

    public function add_tax_query ($tax_query) {

        $this->query['tax_query'][] = $tax_query;

        return $this;

    }

    public function clear_meta_query () {

        $this->query['meta_query'] = [];
        return $this;

    }

    public function clear_tax_query () {

        $this->query['tax_query'] = [];
        return $this;

    }

    public function overwrite ($query) {

        if (!is_array($query) || !$query) return $this;
        foreach ($query as $key => $value) $this->set($key, $value);
        return $this;
        
    }

    protected function should_merge ($value, $allow_empty) {

        if ($value === null)                   return $allow_empty;
        if ($value === '')                     return $allow_empty;
        if (is_array($value) && $value === []) return $allow_empty;

        return true; // false, 0, '0', non-empty arrays always allowed

    }

    public function merge ($query, $allow_empty = false) {

        if (!is_array($query) || !$query) return $this;

        foreach ($query as $key => $value) {

            if (!$this->should_merge($value, $allow_empty)) continue;
            $this->merge_var($key, $value, $allow_empty);

        }

        return $this;

    }

    public function merge_var ($key, $value, $allow_empty = false) {

        if (!$this->should_merge($value, $allow_empty)) return $this;
        
        if (array_key_exists($key, $this->query)) {

            $current = $this->query[$key];

            switch ($key) {

                case 'post_type':
                case 'post_status':

                    if (($value == 'any') || ($current == 'any')) {

                        $value = 'any';
                        break;

                    }

                    $value   = (array) $value;
                    $current = (array) $current;

                    // no break;

                case 'tax_query':
                case 'meta_query':

                    // no break;

                default:

                    if (is_array($value) && is_array($current)) $value = array_values(array_unique(array_merge($current, $value), SORT_REGULAR));
                
            }

        }

        $this->query[$key] = $value;

        return $this;

    }

    public function get_stamp () {

        return (array) $this->get('digitalis');

    }

    //

    protected function compare ($v1, $v2, $operator = '=') {

        $operator = strtoupper(trim($operator));

        switch ($operator) {

            case '=':    return $v1 == $v2;
            case '!=':   return $v1 != $v2;
            case '==':   return $v1 === $v2;
            case '!==':  return $v1 !== $v2;
            case '<':    return $v1 < $v2;
            case '<=':   return $v1 <= $v2;
            case '>':    return $v1 > $v2;
            case '>=':   return $v1 >= $v2;
            case 'IN':   return is_array($v2) ? in_array($v1, $v2) : $v1 == $v2;
            case '!IN':  return !$this->compare($v1, $v2, 'IN');
            case 'IN=':  return is_array($v2) ? in_array($v1, $v2, true) : $v1 === $v2;
            case '!IN=': return !$this->compare($v1, $v2, 'IN=');
            default:     return false;

        }

    }

    public function find_path ($haystack, $match_value, $key = 'key', $compare = '=') {

        if (!is_array($haystack)) return null;
        if (array_key_exists($key, $haystack)) return $this->compare($match_value, $haystack[$key], $compare) ? [] : null;

        foreach ($haystack as $i => $block) {

            if (!is_array($block)) continue;

            if (array_key_exists($key, $block) && $this->compare($match_value, $block[$key], $compare)) return [$i];

            foreach ($block as $k => $v) {

                if (!is_array($v)) continue;

                $sub = $this->find_path($v, $match_value, $key, $compare);
                if ($sub !== null) return array_merge([$i, $k], $sub);

            }

        }

        return null;

    }

    public function find_meta_query_path ($match_value, $key = 'key', $compare = '=') {

        return $this->find_path($this->get_meta_query(), $match_value, $key, $compare);

    }

    public function find_tax_query_path ($match_value, $key = 'taxonomy', $compare = '=') {

        return $this->find_path($this->get_tax_query(), $match_value, $key, $compare);

    }

    protected function &get_by_path (&$arr, $path) {

        $ref = &$arr;
        foreach ($path as $p) $ref = &$ref[$p];
        return $ref;

    }

    public function &get_meta_block ($path) {
    
        return $this->get_by_path($this->query['meta_query'], $path);
    
    }

    public function &get_tax_block ($path) {
    
        return $this->get_by_path($this->query['tax_query'], $path);
    
    }

    public function upsert_meta_query ($match_value, $new_block, $match_key = 'key', $compare = '=') {

        $path = $this->find_meta_query_path($match_value, $match_key, $compare);

        if ($path === null) {
            $this->add_meta_query($new_block);
            return $this;
        }

        $block = &$this->get_meta_block($path);
        $block = array_merge($block, $new_block);

        return $this;

    }

    public function upsert_tax_query ($match_value, $new_block, $match_key = 'taxonomy', $compare = '=') {

        $path = $this->find_tax_query_path($match_value, $match_key, $compare);

        if ($path === null) {
            $this->add_tax_query($new_block);
            return $this;
        }

        $block = &$this->get_tax_block($path);
        $block = array_merge($block, $new_block);

        return $this;

    }

    //

    public function make_query ($overrides = []) {

        $wp_query = new WP_Query();
        $wp_query->query_vars = array_merge($this->to_array(), $overrides);
        return $wp_query;
    
    }

    // Property Overloading

    public function &__get ($key) {

        if (array_key_exists($key, $this->query)) return $this->query[$key]; // Terinaries, null coalesce, etc cause `Only variable references should be returned by reference`

        $null = null;
        return $null;

    }

    public function __set ($key, $value) {

        return $this->set($key, $value);

    }

    public function __unset ($key) {

        return $this->remove($key);

    }

    public function __isset ($key) {

        return $this->has($key);

    }

    // ArrayAccess

    public function &offsetGet (mixed $key): mixed { // Return by reference, see: https://www.php.net/manual/en/arrayaccess.offsetget.php

        return $this->__get($key);

    }

    public function offsetSet (mixed $key, mixed $value): void {

        $this->__set($key, $value);

    }

    public function offsetUnset (mixed $key): void {

        $this->__unset($key);

    }

    public function offsetExists (mixed $key): bool {

        return $this->__isset($key);

    }

    //

    public function count () : int {

        return count($this->query);

    }

    public function getIterator () : \Traversable {

        return new \ArrayIterator($this->query);

    }

    public function jsonSerialize () : mixed {

        return $this->to_array();

    }

    //

    public static function compare_post_type (WP_Query $wp_query, $post_type) {

        if (
            ($wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category()) &&
            ($queried_object = $wp_query->get_queried_object()) &&
            ($taxonomy = $queried_object->taxonomy ?? false) &&
            ($taxonomy = get_taxonomy($taxonomy))
        ) {

            return in_array($post_type, $taxonomy->object_type);

        } else {

            if ($queried_post_type = $wp_query->get('post_type')) {

                if (is_array($queried_post_type)) {

                    if (in_array('any', $queried_post_type) || in_array($post_type, $queried_post_type)) return true;

                } else {

                    if (($queried_post_type == 'any') || ($queried_post_type == $post_type)) return true;

                }

            } elseif (($post_type == 'post') && $wp_query) {

                return $wp_query->is_posts_page || $wp_query->is_author;

            }

            return false;

        }

    }

    public static function is_multiple ($wp_query = null) {

        if (is_null($wp_query)) global $wp_query;

        if ((bool) $wp_query->get(Post_Type::AJAX_Flag)) return true;

        return $wp_query && ($wp_query->is_archive() || $wp_query->is_search() || $wp_query->is_posts_page);
    
    }

}