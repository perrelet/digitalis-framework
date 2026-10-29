<?php

namespace Digitalis;

// One ACF options page as one record, found by the page's slug. Writes run ACF's per-field filters, not acf/save_post.
abstract class Options_Page_Resource extends Model_Resource {

    protected static $model_class = Options_Page_Record::class;
    protected static $page        = '';

    public function get_post_type () : string {

        return '';

    }

    public function get_page_slug () : string {

        return static::$page;

    }

    public function get_model_fields () : array {

        return [];

    }

    protected function get_type_groups () : array {

        return function_exists('acf_get_field_groups') ? acf_get_field_groups(['options_page' => static::$page]) : [];

    }

    public function get_fields_for ($model, array $values = []) : array {

        return $this->get_fields();

    }

    // Records

    public function find ($id) : ?object {

        return ($id === static::$page) ? (static::$model_class)::get_instance($id) : null;

    }

    public function query (array $args) : array {

        return array_filter([$this->find(static::$page)]);

    }

    public function get_status ($model) : string {

        return 'publish';

    }

    public function summarise ($model) : array {

        return ['title' => $model->get_title(), 'status' => 'publish', 'modified' => null];

    }

    public function get_edit_url ($model) : string {

        return $model->get_edit_url();

    }

    public function get_states ($model) : array {

        return [];

    }

    public function get_capability (string $operation, $model = null) : string {

        return ($operation === 'create') ? 'do_not_allow' : ($this->find(static::$page)?->get_capability() ?? 'manage_options');

    }

    public function can (string $operation, $model = null) : bool {

        return current_user_can($this->get_capability($operation, $model));

    }

}
