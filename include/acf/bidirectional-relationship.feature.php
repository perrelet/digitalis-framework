<?php

namespace Digitalis\ACF;

use Digitalis\Log;
use WP_Term;

abstract class Bidirectional_Relationship extends \Digitalis\Feature {

    protected $key_1 = 'field_1';
    protected $key_2 = 'field_2';

    protected $type_1 = 'post';
    protected $type_2 = 'post';

    protected $post_type_1 = [];
    protected $post_type_2 = [];

    protected $taxonomy_1 = [];
    protected $taxonomy_2 = [];

    protected $limit_1 = false;
    protected $limit_2 = false;

    protected $allow_self = false;
    protected $force_add  = false;

    protected $log = false;

    public function get_hooks () {

        $hooks = [
            "acf/validate_value/name={$this->key_1}" => 'validate',
            "acf/validate_value/name={$this->key_2}" => 'validate',
            "acf/update_value/name={$this->key_1}"   => 'sync',
            "acf/update_value/name={$this->key_2}"   => 'sync',
        ];

        $types = [$this->type_1, $this->type_2];

        if (in_array('post', $types, true)) $hooks['deleted_post'] = ['on_post_deleted', 10, 'action'];
        if (in_array('user', $types, true)) $hooks['deleted_user'] = ['on_user_deleted', 10, 'action'];
        if (in_array('term', $types, true)) $hooks['delete_term']  = ['on_term_deleted', 10, 'action'];

        return $hooks;

    }

    public function sync_condition ($values, $updated_selector, $field) {

        return true;

    }

    public function validate ($valid, $values, $field, $input_name) {

        if (($_REQUEST['action'] ?? false) != 'acf/validate_save_post') return $valid;
        if ($valid !== true)                                            return $valid;

        if (empty($values))     $values = [];
        if (!is_array($values)) $values = [$values];

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2   : $this->key_1;
        $limit          = ($field_name == $this->key_1) ? $this->limit_2 : $this->limit_1;
        $self_type      = $this->get_type_for($field_name, false);
        $partner_type   = $this->get_type_for($field_name, true);

        if (!$limit)                                                    return true;
        if (!$updated_selector = $this->get_request_selector())         return true;
        if (!$self_type->matches($updated_selector))                    return true;
        if (!$self_type->passes_filter($updated_selector))              return true;
        if (!$this->sync_condition($values, $updated_selector, $field)) return true;

        $updated_id = $self_type->id_from_selector($updated_selector);

        if ($values) foreach ($values as $id) {

            $selector = $partner_type->selector($id);

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (!in_array($updated_id, $sync_ids) && $limit && (count($sync_ids) >= $limit)) {

                $title = $partner_type->title($selector);
                return "Unable to update this field because '{$title}' cannot be related to more than {$limit} items.";

            }

        }

        return true;

    }

    public function sync ($values, $updated_selector, $field) {

        $field_name     = $field['name'];
        $sync_field_key = ($field_name == $this->key_1) ? $this->key_2 : $this->key_1;
        $self_type      = $this->get_type_for($field_name, false);
        $partner_type   = $this->get_type_for($field_name, true);
        $sync_field     = acf_get_field($sync_field_key);
        $updated_id     = $self_type->id_from_selector($updated_selector);

        if (!$this->sync_condition($values, $updated_selector, $field)) return $values;
        if (!$self_type->matches($updated_selector))                    return $values;
        if (!$self_type->passes_filter($updated_selector))              return $values;
        if (!$this->check_global_flag('updating'))                      return $values;

        // Unresolved partner field means we're about to write its value blind
        // (denormalize() now preserves the array, but multiplicity is a guess).
        // Surface it regardless of $this->log — it signals a real misconfig.
        if (!$sync_field) $this->log(
            "!!! Bidirectional_Relationship : " . static::class
            . " : could not resolve partner field '{$sync_field_key}' via acf_get_field() — syncing blind."
        );

        $old_values = get_field($field['name'], $updated_selector, false);

        if (empty($values))         $values     = [];
        if (empty($old_values))     $old_values = [];
        if (!is_array($values))     $values     = [$values];
        if (!is_array($old_values)) $old_values = [$old_values];

        $added   = $this->force_add ? $values : array_diff($values, $old_values);
        $removed = array_diff($old_values, $values);

        if ($this->log) {

            $this->log("--- Bidirectional_Relationship : " . static::class . " ---");
            $this->log("> Updated: {$updated_selector}.{$field_name} (#{$updated_id} '" . $self_type->title($updated_selector) . "')");
            $this->log("> Fields:  ({$field_name} -> {$sync_field_key})");
            $this->log("> Added:   " . ($added   ? implode(', ', $added)   : '-'));
            $this->log("> Removed: " . ($removed ? implode(', ', $removed) : '-'));

        }

        if ($added) foreach ($added as $id) {

            $selector = $partner_type->selector($id);

            if (!$this->allow_self && ($selector == $updated_selector)) {

                if (($self_key = array_search($id, $values)) !== false) unset($values[$self_key]);
                if ($this->log) $this->log(">>> Skipping add {$updated_selector} to {$selector}.{$sync_field_key} (#{$id}): Cannot add to self.");
                continue;

            }

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (!in_array($updated_id, $sync_ids)) $sync_ids[] = $updated_id;

            if ($this->log) $this->log(">>> Adding '{$updated_id}' to {$selector}.{$sync_field_key} (#{$id} '" . $partner_type->title($selector) . "').");

            update_field($sync_field_key, $this->denormalize($sync_ids, $sync_field), $selector);

        }

        if ($removed) foreach ($removed as $id) {

            $selector = $partner_type->selector($id);

            $sync_ids = get_field($sync_field_key, $selector, false);
            if (empty($sync_ids))     $sync_ids = [];
            if (!is_array($sync_ids)) $sync_ids = [$sync_ids];

            if (in_array($updated_id, $sync_ids)) unset($sync_ids[array_search($updated_id, $sync_ids)]);

            if ($this->log) $this->log(">>> Removing '{$updated_id}' from {$selector}.{$sync_field_key} (#{$id} '" . $partner_type->title($selector) . "').");

            update_field($sync_field_key, $this->denormalize($sync_ids, $sync_field), $selector);

        }

        if ($this->log) $this->log("--- End ---");

        $this->release_global_flag('updating');

        return $this->denormalize($values, $field);

    }

    public function on_post_deleted ($post_id, $post = null) {

        $this->cleanup_for('post', $post_id, ['post_type' => $post ? $post->post_type : null]);

    }

    public function on_user_deleted ($user_id, $reassign = null, $user = null) {

        $this->cleanup_for('user', $user_id);

    }

    public function on_term_deleted ($term_id, $tt_id, $taxonomy) {

        $this->cleanup_for('term', $term_id, ['taxonomy' => $taxonomy]);

    }

    protected function cleanup_for ($deleted_type, $deleted_id, $context = []) {

        foreach ([1, 2] as $side) {

            $type_this = ($side === 1) ? $this->type_1 : $this->type_2;
            if ($type_this !== $deleted_type) continue;

            if ($type_this === 'post') {

                $filter    = ($side === 1) ? (array) $this->post_type_1 : (array) $this->post_type_2;
                $post_type = $context['post_type'] ?? null;
                if ($filter && $post_type && !in_array($post_type, $filter, true)) continue;

            }

            if ($type_this === 'term') {

                $filter   = ($side === 1) ? (array) $this->taxonomy_1 : (array) $this->taxonomy_2;
                $taxonomy = $context['taxonomy'] ?? null;
                if ($filter && $taxonomy && !in_array($taxonomy, $filter, true)) continue;

            }

            $dangling_key  = ($side === 1) ? $this->key_2  : $this->key_1;
            $dangling_type = ($side === 1) ? $this->type_2 : $this->type_1;

            $this->strip_reference($dangling_type, $dangling_key, (int) $deleted_id);

        }

    }

    protected function strip_reference ($partner_type, $key, int $deleted_id) {

        global $wpdb;

        if ($partner_type === 'user') {
            $table  = $wpdb->usermeta;
            $col_id = 'user_id';
            $get    = 'get_user_meta';
            $update = 'update_user_meta';
        } else if ($partner_type === 'term') {
            $table  = $wpdb->termmeta;
            $col_id = 'term_id';
            $get    = 'get_term_meta';
            $update = 'update_term_meta';
        } else {
            $table  = $wpdb->postmeta;
            $col_id = 'post_id';
            $get    = 'get_post_meta';
            $update = 'update_post_meta';
        }

        $like = '%' . $wpdb->esc_like('"' . $deleted_id . '"') . '%';
        $eq   = (string) $deleted_id;

        $partner_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT {$col_id} FROM {$table} WHERE meta_key = %s AND (meta_value = %s OR meta_value LIKE %s)",
            $key, $eq, $like
        ));

        if (!$partner_ids) return;

        if ($this->log) $this->log(">>> Cleanup #{$deleted_id} from " . count($partner_ids) . " {$partner_type}(s) via {$key}");

        foreach ($partner_ids as $partner_id) {

            $value = $get((int) $partner_id, $key, true);

            if (is_array($value)) {

                $cleaned = array_values(array_filter($value, fn($v) => (int) $v !== $deleted_id));
                if (count($cleaned) !== count($value)) $update((int) $partner_id, $key, $cleaned);

            } else if ((string) $value === $eq) {

                $update((int) $partner_id, $key, '');

            }

        }

    }

    protected function get_type_for ($field_name, $partner = false): Object_Type {

        $is_one = (($field_name == $this->key_1) xor $partner);

        $type   = $is_one ? $this->type_1 : $this->type_2;
        $filter = ($type === 'term')
            ? ($is_one ? $this->taxonomy_1  : $this->taxonomy_2)
            : ($is_one ? $this->post_type_1 : $this->post_type_2);

        return new Object_Type($type, (array) $filter);

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

    public function get_request_selector () {

        if (!empty($_REQUEST['_acf_post_id'])) return sanitize_text_field(wp_unslash($_REQUEST['_acf_post_id']));
        if (isset($_REQUEST['post_id']))       return (int) $_REQUEST['post_id'];
        if (isset($_REQUEST['user_id']))       return "user_" . (int) $_REQUEST['user_id'];

        return false;

    }

    protected function denormalize ($values, $field) {

        // If the field definition couldn't be resolved (e.g. acf_get_field()
        // returned false for a name that didn't map, including name collisions)
        // we can't know its multiplicity — preserve the array rather than risk
        // truncating a multi-value partner field down to its first element.
        if (!is_array($field)) return $values;

        // Collapse to a scalar only for genuinely single-value fields. Several
        // ACF field types return an array but don't carry a `multiple` flag, so
        // they get silently truncated to their first element unless recognised:
        //   - relationship / checkbox        : always multi, no `multiple` setting
        //   - taxonomy (checkbox|multi_select): multiplicity set via `field_type`
        //   - post_object/select/user/page_link: carry an explicit `multiple` flag
        $type = $field['type'] ?? '';

        $multiple = (
            in_array($type, ['relationship', 'checkbox'], true)
            || !empty($field['multiple'])
            || ($type === 'taxonomy' && in_array($field['field_type'] ?? '', ['checkbox', 'multi_select'], true))
        );

        if ($multiple) return $values;
        return $values ? reset($values) : '';

    }

    protected function log ($msg) {

        if (is_string($this->log)) {

            $log = class_exists($this->log) ? ($this->log)::get_instance() : Log::get_instance($this->log);
            if ($log) $log->log($msg);

        } else {

            error_log($msg);

        }

    }

}

class Object_Type {

    public function __construct (
        protected string $type   = 'post',
        protected array  $filter = []
    ) {}

    public function get_type (): string {

        return $this->type;

    }

    public function selector ($id) {

        if ($this->type === 'user') return "user_{$id}";
        if ($this->type === 'term') return "term_{$id}";

        return (int) $id;

    }

    public function id_from_selector ($selector): int {

        return (int) str_replace(['user_', 'term_'], '', (string) $selector);

    }

    public function matches ($selector): bool {

        if (is_int($selector)) return $this->type === 'post';

        $s = (string) $selector;

        if ($this->type === 'user') return strpos($s, 'user_') === 0;
        if ($this->type === 'term') return strpos($s, 'term_') === 0;

        return ctype_digit($s);

    }

    public function passes_filter ($selector): bool {

        if (!$this->filter)         return true;
        if ($this->type === 'user') return true;

        $id = $this->id_from_selector($selector);

        if ($this->type === 'term') {

            $term = get_term($id);
            return ($term instanceof WP_Term) && in_array($term->taxonomy, $this->filter, true);

        }

        return in_array(get_post_type($id), $this->filter, true);

    }

    public function title ($selector): string {

        $id = $this->id_from_selector($selector);

        if ($this->type === 'user') return ($u = get_user_by('id', $id)) ? $u->user_nicename : 'Unknown User';
        if ($this->type === 'term') return ($t = get_term($id)) instanceof WP_Term ? $t->name : 'Unknown Term';

        return get_the_title($id) ?: 'Unknown Post';

    }

}
