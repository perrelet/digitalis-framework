<?php

namespace Lattice;

// kses filters new input; a value sent back exactly as stored isn't.
class Stored_Markup extends Feature {

    const COLUMNS = ['post_title', 'post_content', 'post_excerpt', 'post_content_filtered'];

    protected array $kept = [];

    public function get_hooks () {

        return [
            'wp_insert_post_data' => ['keep_columns', PHP_INT_MIN],
            'acf/save_post'       => ['keep_fields', 0],
        ];

    }

    // $data arrives slashed.
    public function keep_columns ($data, $postarr, $unsanitized, $update) {

        if (!$update || !($stored = get_post((int) ($postarr['ID'] ?? 0), ARRAY_A))) return $data;

        foreach (static::COLUMNS as $column) if (isset($unsanitized[$column]) && (wp_unslash($unsanitized[$column]) === $stored[$column])) $data[$column] = wp_slash($stored[$column]);

        return $data;

    }

    // ACF filters $_POST['acf'] before any hook; $_REQUEST keeps what the form sent. The form's save is its record's first: later ones carry code's values.
    public function keep_fields ($post_id) {

        if (acf_allow_unfiltered_html() || isset($this->kept[$post_id]) || !is_array($_POST['acf'] ?? null) || !is_array($_REQUEST['acf'] ?? null) || ((string) ($_REQUEST['_acf_post_id'] ?? '') !== (string) $post_id)) return;

        $this->kept[$post_id] = true;

        foreach ($_POST['acf'] as $key => $filtered) if (isset($_REQUEST['acf'][$key]) && ($field = acf_get_field($key))) $_POST['acf'][$key] = wp_slash(Markup::keep_value(wp_unslash($filtered), wp_unslash($_REQUEST['acf'][$key]), [acf_get_value($post_id, $field)]));

    }

}
