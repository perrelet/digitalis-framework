<?php

namespace Digitalis;

use WP_Error;
use WP_REST_Request;

// What a form route's rules read: the values as submitted, `when`, emptiness and the messages (docs/FORMS.md).
class Form_Rules extends Utility {

    const MESSAGES = [
        'invalid'                     => 'Please check this answer.',
        'rest_invalid_type'           => 'Please check this answer.',
        'rest_invalid_email'          => 'Please enter a valid email address.',
        'rest_invalid_date'           => 'Please enter a valid date.',
        'rest_invalid_ip'             => 'Please enter a valid IP address.',
        'rest_invalid_uuid'           => 'Please check this answer.',
        'rest_invalid_hex_color'      => 'Please enter a valid colour.',
        'rest_not_in_enum'            => 'Please choose one of the options.',
        'rest_too_short'              => 'This is too short.',
        'rest_too_long'               => 'This is too long.',
        'rest_out_of_bounds'          => 'Please enter a number in the allowed range.',
        'rest_too_few_items'          => 'Please choose more.',
        'rest_too_many_items'         => 'Please choose fewer.',
        'rest_duplicate_items'        => 'Please choose each option only once.',
        'rest_invalid_multiple'       => 'Please enter a number in the allowed steps.',
        'rest_invalid_pattern'        => 'Please check the format of this answer.',
        'rest_property_required'      => 'Please complete this answer.',
        'rest_no_matching_schema'     => 'Please check this answer.',
        'rest_one_of_multiple_matches'=> 'Please check this answer.',
    ];

    // WordPress's precedence, lowest first so the highest wins; defaults are never a submitted value.
    public static function submitted (WP_REST_Request $request) : array {

        return array_merge((array) $request->get_url_params(), (array) $request->get_query_params(), (array) $request->get_body_params(), (array) $request->get_json_params());

    }

    public static function is_empty ($value) : bool {

        if (is_null($value) || ($value === '')) return true;
        if (is_array($value)) return !array_filter($value, fn ($item) => !static::is_empty($item));

        return false;

    }

    public static function applies (array $rule, array $values) : bool {

        if (empty($rule['when']) || !is_array($rule['when'])) return true;

        [$name, $operator, $expected] = array_pad(array_values($rule['when']), 3, null);

        return static::compare($values[$name] ?? '', (string) $operator, $expected);

    }

    // The operators fields.js judges a field's `condition` with, so the two agree.
    public static function compare ($current, string $operator, $expected) : bool {

        $values = array_map('strval', is_array($current) ? array_values($current) : [$current]);

        switch ($operator) {

            case '=':
            case '==':  return in_array((string) $expected, $values, true);
            case '!=':
            case '!==': return !in_array((string) $expected, $values, true);
            case '<':   return static::numeric($values, fn ($v) => $v <  (float) $expected);
            case '<=':  return static::numeric($values, fn ($v) => $v <= (float) $expected);
            case '>':   return static::numeric($values, fn ($v) => $v >  (float) $expected);
            case '>=':  return static::numeric($values, fn ($v) => $v >= (float) $expected);

        }

        return false;

    }

    protected static function numeric (array $values, callable $test) : bool {

        foreach ($values as $value) if (($value !== '') && is_numeric($value) && $test((float) $value)) return true;

        return false;

    }

    public static function message (string $code, string $name, array $rule, string $fallback = '') : string {

        if ($code === 'missing') $default = static::missing_message($rule);
        else                     $default = static::MESSAGES[$code] ?? ($fallback ?: static::MESSAGES['invalid']);

        $message = $rule['messages'][$code] ?? $default;

        return (string) apply_filters('lattice.form.message', $message, $code, $name, $rule);

    }

    protected static function missing_message (array $rule) : string {

        switch ($rule['type'] ?? '') {

            case 'array':   return 'Please choose at least one.';
            case 'boolean': return 'Please tick this box.';

        }

        return 'Please fill this in.';

    }

    public static function refusal (array $problems) : WP_Error {

        return new WP_Error('rest_invalid_param', sprintf('Invalid parameter(s): %s', implode(', ', array_keys($problems))), ['status' => 400, 'params' => $problems]);

    }

}
