<?php

namespace Digitalis\ACF;

use Digitalis\Integration;

abstract class Bidirectional_Relationship extends Integration {

    protected $key_1 = 'field_1';
    protected $key_2 = 'field_2';

    protected $post_type_1 = [];
    protected $post_type_2 = [];

    protected $log = false;

    public function __construct () {

        add_filter("acf/update_value/name={$this->key_1}", [$this, 'sync'], 10, 3);
        add_filter("acf/update_value/name={$this->key_2}", [$this, 'sync'], 10, 3);

    }

    public function sync ($values, $updated_post_id, $field) {

        $field_name = $field['name'];
        $sync_field = ($field_name == $this->key_1) ? $this->key_2 : $this->key_1;

        if ($post_type = ($field_name == $this->key_1) ? $this->post_type_1 : $this->post_type_2) {

            if (is_array($post_type)) {

                if (!in_array(get_post_type($updated_post_id), $post_type)) return;

            } else {

                if (get_post_type($updated_post_id) != $post_type) return;

            }

        }

        $global_flag = "updating_{$field_name}";

        if (isset($GLOBALS[$global_flag]) && $GLOBALS[$global_flag]) return $values;
        //if (get_post_type($updated_post_id) != 'product') return $values;

        $old_values = get_field($field['name'], $updated_post_id, false);
        if (empty($values)) $values = [];
		if (empty($old_values)) $old_values = [];

        $added = array_diff($values, $old_values);
		$removed = array_diff($old_values, $values);

        if ($this->log) {

            error_log("--- Bidirectional_Relationship ---");
            error_log("Added Values:");
            error_log(print_r($added, true));
            error_log("Removed Values:");
            error_log(print_r($removed, true));
            error_log("--- End ---");
            
        }

        $GLOBALS[$global_flag] = true;

        if ($added) foreach ($added as $post_id) {

			$sync_ids = get_field($sync_field, $post_id, false);
			if (empty($sync_ids)) $sync_ids = [];

			if (!in_array($updated_post_id, $sync_ids)) $sync_ids[] = $updated_post_id;

			update_field($sync_field, $sync_ids, $post_id);

		}

        if ($removed) foreach ($removed as $post_id) {

			$sync_ids = get_field($sync_field, $post_id, false);
			if (empty($sync_ids)) $sync_ids = [];

			if (in_array($updated_post_id, $sync_ids)) unset($sync_ids[array_search($updated_post_id, $sync_ids)]);

			update_field($sync_field, $sync_ids, $post_id);

		}

        $GLOBALS[$global_flag] = false;

		return $values;

    }

}