<?php

namespace Digitalis;

// kses filters new input; a value sent back exactly as stored isn't.
class Stored_Markup extends Feature {

    const COLUMNS = ['post_title', 'post_content', 'post_excerpt', 'post_content_filtered'];

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

    // ACF filters $_POST['acf'] before any hook; $_REQUEST keeps what was sent.
    public function keep_fields ($post_id) {

        if (acf_allow_unfiltered_html() || !is_array($_POST['acf'] ?? null) || !is_array($_REQUEST['acf'] ?? null)) return;

        foreach ($_POST['acf'] as $key => $filtered) if (isset($_REQUEST['acf'][$key]) && ($field = acf_get_field($key))) $_POST['acf'][$key] = $this->keep_value($filtered, $_REQUEST['acf'][$key], acf_get_value($post_id, $field));

    }

    // Rows match stored rows by position.
    protected function keep_value ($filtered, $sent, $stored) {

        if (is_string($filtered)) return (($filtered !== $sent) && is_string($sent) && is_string($stored) && (wp_unslash($sent) === $stored)) ? $sent : $filtered;

        if (!is_array($filtered) || !is_array($sent)) return $filtered;

        $rows = (is_array($stored) && array_is_list($stored)) ? $stored : null;
        $i    = 0;

        foreach ($filtered as $key => $value) $filtered[$key] = $this->keep_value($value, $sent[$key] ?? null, $rows ? ($rows[$i++] ?? null) : (is_array($stored) ? ($stored[$key] ?? null) : null));

        return $filtered;

    }

}
