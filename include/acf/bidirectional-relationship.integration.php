<?php

namespace Digitalis\ACF;

use Digitalis\Integration;

abstract class Bidirectional_Relationship extends Integration {

    protected $key_1 = 'field_1';
    protected $key_2 = 'field_2';

    protected $post_type_1 = [];
    protected $post_type_2 = [];

    protected $limit_1 = false;
    protected $limit_2 = false;

    protected $log = false;

    public function __construct () {

        add_filter("acf/validate_value/name={$this->key_1}", [$this, 'validate'], 10, 4);
        add_filter("acf/validate_value/name={$this->key_2}", [$this, 'validate'], 10, 4);

        add_filter("acf/update_value/name={$this->key_1}", [$this, 'sync'], 10, 3);
        add_filter("acf/update_value/name={$this->key_2}", [$this, 'sync'], 10, 3);

    }

    public function sync_condition ($values, $updated_post_id, $field) {

        return true;

    }

    public function validate ($valid, $values, $field, $input_name) {

        if ($valid !== true) return $valid;

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2 : $this->key_1;
        $limit          = ($field_name == $this->key_1) ? $this->limit_2 : $this->limit_1;

        if (!$limit)                                                                    return true;
        if (!$this->check_global_flag('validating', $field_name))                       return true;
        if (!isset($_REQUEST['post_id']) || (!$updated_post_id = $_REQUEST['post_id'])) return true;
        if (!$this->sync_condition($values, $updated_post_id, $field))                  return true;
        if (!$this->validate_post_type($field_name, $updated_post_id))                  return true;

        $old_values = get_field($field['name'], $updated_post_id, false);
        if (empty($values))         $values = [];
		if (empty($old_values))     $old_values = [];
        if (!is_array($values))     $values = [$values];
        if (!is_array($old_values)) $values = [$old_values];

        if ($adding = array_diff($values, $old_values)) foreach ($adding as $post_id) {

			$sync_ids = get_field($sync_field_key, $post_id, false);
			if (empty($sync_ids)) $sync_ids = [];

			if (!in_array($updated_post_id, $sync_ids) && $limit && (count($sync_ids) >= $limit)) {

                return "Unable to update this field because '" . get_the_title($post_id) . "' cannot be related to more than {$limit} items.";

            }

		}

        $this->release_global_flag('validating', $field_name);

        return true;

    }

    public function sync ($values, $updated_post_id, $field) {

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2 : $this->key_1;

        if (!$this->check_global_flag('updating', $field_name))         return $values;
        if (!$this->sync_condition($values, $updated_post_id, $field))  return $values;
        if (!$this->validate_post_type($field_name, $updated_post_id))  return $values;

        $old_values = get_field($field['name'], $updated_post_id, false);
        if (empty($values))         $values = [];
		if (empty($old_values))     $old_values = [];
        if (!is_array($values))     $values = [$values];
        if (!is_array($old_values)) $values = [$old_values];

        $added = array_diff($values, $old_values);
		$removed = array_diff($old_values, $values);

        if ($added) foreach ($added as $post_id) {

			$sync_ids = get_field($sync_field_key, $post_id, false);
			if (empty($sync_ids)) $sync_ids = [];

            if (!in_array($updated_post_id, $sync_ids)) $sync_ids[] = $updated_post_id; // Add the current post to the post being added

			update_field($sync_field_key, $sync_ids, $post_id);

		}

        if ($removed) foreach ($removed as $post_id) {

			$sync_ids = get_field($sync_field_key, $post_id, false);
			if (empty($sync_ids)) $sync_ids = [];

			if (in_array($updated_post_id, $sync_ids)) unset($sync_ids[array_search($updated_post_id, $sync_ids)]); // Remove the current post from the post being added

			update_field($sync_field_key, $sync_ids, $post_id);

		}

        if ($this->log) {

            error_log("--- Bidirectional_Relationship : " . static::class . " ---");
            error_log("Syncing: " . get_post_type($updated_post_id) . " (" . get_the_title($updated_post_id) . " #{$updated_post_id}): {$field['name']} -> {$sync_field_key}");
            if ($added) error_log("Values added to " . get_the_title($added[array_key_first($added)]) . ".{$sync_field_key} = " . implode(", ", $added));
            if ($removed) error_log("Values removed from " . get_post_type($removed[array_key_first($removed)]) . ".{$sync_field_key} = " . implode(", ", $removed));
            error_log("--- End ---");
            
        }

        $this->release_global_flag('updating', $field_name);

		return $values;

    }

    public function check_global_flag ($action, $field_name) {

        $global_flag = "{$action}_{$field_name}_" . static::class;
        if (isset($GLOBALS[$global_flag]) && $GLOBALS[$global_flag]) return false;
        $GLOBALS[$global_flag] = true;

        return true;

    }

    public function release_global_flag ($action, $field_name) {

        $global_flag = "{$action}_{$field_name}_" . static::class;
        $GLOBALS[$global_flag] = false;

    }

    public function validate_post_type ($field_name, $updated_post_id) {

        if ($post_type = ($field_name == $this->key_1) ? $this->post_type_1 : $this->post_type_2) {

            if (!is_array($post_type)) $post_type = [$post_type];
            if (!in_array(get_post_type($updated_post_id), $post_type)) return false;

        }

        return true;

    }

}