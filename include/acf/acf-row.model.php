<?php

namespace Digitalis;

class ACF_Row extends Model {

    protected static $repeater = null; // string|null - ACF repeater field name; subclass discriminator for auto-resolution; null on the base class

    protected $parent;     // Post|User|Term instance
    protected $selector;   // string    - this instance's repeater selector
    protected $index;      // int|null  - 0-based row position; null until saved
    protected $data = [];  // array     - row subfield values

    protected $dirty = false;

    public static function get_specificity () {

        return (int) (bool) static::$repeater;

    }

    public static function extract_id ($data = null) {

        // ID format: acf:{type}:{selector}:{parent_id}:{index} — e.g. acf:post:milestones:42:3
        if (is_string($data) && (strpos($data, 'acf:') === 0)) return $data;

        if (is_array($data) && isset($data['parent'], $data['index'])) {

            if (!$selector = static::$repeater ?? $data['selector'] ?? null) return null;
            if (!$info     = static::extract_parent_info($data['parent']))   return null;

            return static::format_id($info['type'], $selector, $info['id'], (int) $data['index']);

        }

        return parent::extract_id($data);

    }

    public static function validate_id ($id) {

        // Cheap structural check only — no parent lookup. Bogus ids hydrate to empty $data and downstream operations no-op.
        if (!$parsed = static::parse_id($id)) return false;

        return !static::$repeater || ($parsed['selector'] === static::$repeater);

    }

    protected static function format_id ($type, $selector, $parent_id, $index) {

        return 'acf:' . $type . ':' . $selector . ':' . (int) $parent_id . ':' . (int) $index;

    }

    protected static function parse_id ($id) {

        if (!is_string($id))           return null;
        if (strpos($id, 'acf:') !== 0) return null;

        $parts = explode(':', $id, 5);
        if (count($parts) !== 5)       return null;

        return [
            'type'     => $parts[1],
            'selector' => $parts[2],
            'parent'   => (int) $parts[3],
            'index'    => (int) $parts[4],
        ];

    }

    protected static function extract_parent_info ($parent) {

        if (!($parent instanceof Model))                 return null;
        if (!method_exists($parent, 'get_wp_meta_type')) return null;

        $type = $parent->get_wp_meta_type();
        if (!static::get_parent_class($type))            return null;

        return ['id' => $parent->get_id(), 'type' => $type];

    }

    protected static function get_parent_class ($type) {

        return [
            'post' => Post::class,
            'user' => User::class,
            'term' => Term::class,
        ][$type] ?? null;

    }

    // Querying

    public static function query_for ($parent, $criteria = []) {

        if (!$parent instanceof Model) return [];

        $rows = $parent->get_field_rows(static::$repeater, static::class);

        if (!$criteria) return $rows;

        return array_values(array_filter($rows, fn ($row) => static::matches_criteria($row, $criteria)));

    }

    protected static function matches_criteria ($instance, $criteria) {

        if (!$criteria) return true;

        foreach ($criteria as $key => $value) {

            if ($instance->get_field($key) !== $value) return false;

        }

        return true;

    }

    // Hydration

    protected function hydrate_instance () {

        if (!$parsed = static::parse_id($this->id)) return;

        $this->selector = $parsed['selector'];
        $this->index    = $parsed['index'];

        if ($parent_class = static::get_parent_class($parsed['type'])) {

            $this->parent = $parent_class::get_instance($parsed['parent']);

        }

        $rows       = $this->parent ? (array) $this->parent->get_field($this->selector) : [];
        $this->data = $rows[$this->index] ?? [];

    }

    protected function build_instance ($data) {

        $this->selector = static::$repeater ?? $data['selector'] ?? null;

        $parent = $data['parent'] ?? null;
        if ($parent instanceof Model) $this->parent = $parent;

        $this->index = null;
        $this->data  = $data['data'] ?? [];

    }

    // Accessors

    public function get_parent () {

        return $this->parent;

    }

    public function get_index () {

        return $this->index;

    }

    public function get_selector () {

        return $this->selector;

    }

    public function get_data () {

        return $this->data;

    }

    public function get_field ($key) {

        return $this->data[$key] ?? null;

    }

    public function set_field ($key, $value) {

        $this->data[$key] = $value;
        $this->dirty      = true;

        return $this;

    }

    public function set_fields ($data) {

        if ($data) foreach ($data as $key => $value) $this->set_field($key, $value);

        return $this;

    }

    public function update_field ($key, $value) {

        return $this->set_field($key, $value)->save();

    }

    public function update_fields ($data) {

        return $this->set_fields($data)->save();

    }

    public function is_dirty () {

        return $this->dirty;

    }

    public function is_saved () {

        return !$this->is_new() && !$this->dirty;

    }

    // CRUD

    public function save () {

        if (!$this->parent)                         return false;
        if (!$this->selector)                       return false;
        if (!$acf_id = $this->parent->get_acf_id()) return false;

        if ($this->is_new()) {

            // Resolve parent type before writing — if we can't compose the post-insert id, don't leave the row half-written.
            if (!$info = static::extract_parent_info($this->parent)) return false;

            // add_row returns the new 1-based row number, or false on failure.
            if (!$row_num = add_row($this->selector, $this->data, $acf_id)) return false;

            $this->index  = $row_num - 1;
            $this->is_new = false;
            $this->id     = static::format_id($info['type'], $this->selector, $info['id'], $this->index);

            $this->cache_instance();

        } else {

            update_row($this->selector, $this->index + 1, $this->data, $acf_id);

        }

        $this->dirty = false;

        return $this;

    }

    public function delete () {

        if (!$this->parent)                         return false;
        if (is_null($this->index))                  return false;
        if (!$this->selector)                       return false;
        if (!$acf_id = $this->parent->get_acf_id()) return false;

        $result = delete_row($this->selector, $this->index + 1, $acf_id);

        // Sibling rows shift indexes after deletion — drop this instance's cache slot; cached siblings are now stale and should be re-queried.
        unset(self::$instances[static::class][$this->id]);
        $this->index = null;

        return $result;

    }

    public function reload () {

        $this->hydrate_instance();
        $this->dirty = false;

        return $this;

    }

}
