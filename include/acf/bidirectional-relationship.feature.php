<?php

namespace Digitalis\ACF;

use Digitalis\Feature;

abstract class Bidirectional_Relationship extends Feature {

    protected $key_1 = 'field_1';
    protected $key_2 = 'field_2';

    protected $post_type_1 = [];
    protected $post_type_2 = [];

    protected $limit_1 = false;
    protected $limit_2 = false;

    protected $allow_self = false;

    protected $log = false;

    public function run () {

        add_filter("acf/validate_value/name={$this->key_1}", [$this, 'validate'], 10, 4);
        add_filter("acf/validate_value/name={$this->key_2}", [$this, 'validate'], 10, 4);

        add_filter("acf/update_value/name={$this->key_1}", [$this, 'sync'], 10, 3);
        add_filter("acf/update_value/name={$this->key_2}", [$this, 'sync'], 10, 3);

    }

    public function sync_condition ($values, $updated_selector, $field) {

        return true;

    }

    public function validate ($valid, $values, $field, $input_name) {

        if (($_REQUEST['action'] ?? false) != 'acf/validate_save_post') return $valid;
        if ($valid !== true) return $valid;

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2       : $this->key_1;
        $sync_post_type = ($field_name == $this->key_1) ? $this->post_type_2 : $this->post_type_1;
        $limit          = ($field_name == $this->key_1) ? $this->limit_2     : $this->limit_1;

        $this_post_type = ($field_name == $this->key_1) ? $this->post_type_1 : $this->post_type_2;

        if (!$limit)                                                                     return true;
        if (!$this->check_global_flag('validating'))                                     return true;
        if (!$updated_id = $this->get_request_id())                                      return true;
        if (!$updated_selector = $this->get_selector($updated_id, $this_post_type))      return true;
        if (!$this->sync_condition($values, $updated_selector, $field))                  return true;
        if (!$this->validate_post_type($field_name, $updated_selector))                  return true;

        if ($values) foreach ($values as $id) {

            $selector = $this->get_selector($id, $sync_post_type);

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (!in_array($updated_id, $sync_ids) && $limit && (count($sync_ids) >= $limit)) {

                $title = $this->get_object_title($selector);
                return "Unable to update this field because '{$title}' cannot be related to more than {$limit} items.";

            }

		}

        $this->release_global_flag('validating');

        return true;

    }

    public function sync ($values, $updated_selector, $field) {

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2 : $this->key_1;
        $sync_post_type = ($field_name == $this->key_1) ? $this->post_type_2 : $this->post_type_1;
        $updated_id     = $this->extract_id($updated_selector);

        if (!$this->check_global_flag('updating'))                      return $values;
        if (!$this->sync_condition($values, $updated_selector, $field)) return $values;
        if (!$this->validate_post_type($field_name, $updated_selector)) return $values;

        $old_values = get_field($field['name'], $updated_selector, false);
        
        if (empty($values))         $values     = [];
		if (empty($old_values))     $old_values = [];
        if (!is_array($values))     $values     = [$values];
        if (!is_array($old_values)) $old_values = [$old_values];

        $added   = array_diff($values, $old_values);
		$removed = array_diff($old_values, $values);

        if ($this->log) { 
            
            error_log("--- Bidirectional_Relationship : " . static::class . " ---");
            error_log("> Updated: {$updated_selector}.{$field_name} (#{$updated_id} '" . $this->get_object_title($updated_selector) . "')");
            error_log("> Fields:  ({$field_name} -> {$sync_field_key})");
            error_log("> Added:   " . ($added   ? implode(', ', $added)   : '-'));
            error_log("> Removed: " . ($removed ? implode(', ', $removed) : '-'));

        }

        if ($added) foreach ($added as $id) {

            $selector = $this->get_selector($id, $sync_post_type);

            if (!$this->allow_self && ($selector == $updated_selector)) {

                if (($self_key = array_search($id, $values)) !== false) unset($values[$self_key]);
                if ($this->log) error_log(">>> Skipping add {$updated_selector} to {$selector}.{$sync_field_key} (#{$id}): Cannot add to self.");
                continue;

            }

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (!in_array($updated_id, $sync_ids)) $sync_ids[] = $updated_id; // Add the current post to the post being added

            if ($this->log) error_log(">>> Adding '{$updated_id}' to {$selector}.{$sync_field_key} (#{$id} '" . $this->get_object_title($selector) . "').");

            update_field($sync_field_key, $sync_ids, $selector);

        }

        if ($removed) foreach ($removed as $id) {

            $selector = $this->get_selector($id, $sync_post_type);

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (in_array($updated_id, $sync_ids)) unset($sync_ids[array_search($updated_id, $sync_ids)]); // Remove the current post from the post being added

            if ($this->log) error_log(">>> Removing '{$updated_id}' from {$selector}.{$sync_field_key} (#{$id} '" . $this->get_object_title($selector) . "').");

            update_field($sync_field_key, $sync_ids, $selector);

        }

        if ($this->log) error_log("--- End ---");

        $this->release_global_flag('updating');

		return $values;

    }

    public function check_global_flag ($action) {

        $global_flag = "{$action}_" . static::class;
        if (isset($GLOBALS[$global_flag]) && $GLOBALS[$global_flag]) return false;
        $GLOBALS[$global_flag] = true;

        return true;

    }

    public function release_global_flag ($action) {

        $global_flag = "{$action}_" . static::class;
        $GLOBALS[$global_flag] = false;

    }

    public function validate_post_type ($field_name, $updated_selector) {

        if ($post_type = ($field_name == $this->key_1) ? $this->post_type_1 : $this->post_type_2) {

            if (!is_array($post_type)) $post_type = [$post_type];

            if (in_array('user', $post_type))                            return ($this->detect_type($updated_selector) == 'user');
            if (!in_array(get_post_type($updated_selector), $post_type)) return false;

        }

        return true;

    }

    public function get_object_title ($selector) {

        $id = $this->extract_id($selector);

        switch ($this->detect_type($selector)) {

            case 'post':
                return get_the_title($id);

            case 'user':
                return ($user = get_user_by('id', $id)) ? $user->user_nicename : 'Unknown User';

        }

        return 'Unknown Object';

    
    }

    public function get_request_id () {

        $id = false;

        if (isset($_REQUEST['post_id'])) $id = $_REQUEST['post_id'];
        if (isset($_REQUEST['user_id'])) $id = $_REQUEST['user_id'];

        return $id;
    
    }

    public function detect_type ($selector) {
    
        if (strpos($selector, 'user_') !== false) return 'user';
        return 'post';
    
    }

    public function extract_id ($selector) {
    
        return str_replace('user_', '', $selector);
    
    }

    public function get_selector ($id, $post_type) {

        if ($post_type == 'user') return "user_{$id}";
        
        return $id;

    }

}