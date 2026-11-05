<?php

namespace Digitalis;

// What kses does to markup, on unslashed values of any shape: a form's field keys or the layer's names.
class Markup extends Utility {

    // $stored holds what a value may match. A row may match any stored row, so moved and deleted rows still match.
    public static function keep_value ($filtered, $sent, array $stored) {

        if (is_string($filtered)) return (($filtered !== $sent) && is_string($sent) && in_array($sent, array_filter($stored, 'is_string'), true)) ? $sent : $filtered;

        if (!is_array($filtered) || !is_array($sent)) return $filtered;

        $rows   = array_merge(...array_values(array_filter($stored, fn ($each) => is_array($each) && array_is_list($each))));
        $fields = array_filter($stored, fn ($each) => is_array($each) && !array_is_list($each));

        foreach ($filtered as $key => $value) $filtered[$key] = static::keep_value($value, $sent[$key] ?? null, [...$rows, ...array_column($fields, $key)]);

        return $filtered;

    }

    // Block attributes' strings too.
    public static function count ($value) : array {

        $counts = [];

        $scan = function (string $html) use (&$counts) {

            $tags = new \WP_HTML_Tag_Processor($html);
            $seen = [];

            while ($tags->next_tag()) {

                $tag = strtolower($tags->get_tag());

                $seen[$tag]   = ($seen[$tag] ?? 0) + 1;
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;

                foreach ((array) $tags->get_attribute_names_with_prefix('') as $attribute) $counts["{$attribute} on {$tag}"] = ($counts["{$attribute} on {$tag}"] ?? 0) + 1;

            }

            // An unclosed <script> stops the processor, and kses still takes it.
            if ($tags->paused_at_incomplete_token() && preg_match_all('/<([a-z][a-z0-9-]*)/i', $html, $names)) foreach (array_count_values(array_map('strtolower', $names[1])) as $tag => $count) if ($count > ($seen[$tag] ?? 0)) $counts[$tag] = ($counts[$tag] ?? 0) + $count - ($seen[$tag] ?? 0);

        };

        $walk = function (array $blocks) use (&$walk, $scan) {

            foreach ($blocks as $block) {

                if (is_array($block['attrs'])) array_walk_recursive($block['attrs'], function ($value) use ($scan) { if (is_string($value) && str_contains($value, '<')) $scan($value); });

                $walk($block['innerBlocks']);

            }

        };

        $values = (array) $value;

        array_walk_recursive($values, function ($html) use ($scan, $walk) {

            if (!is_string($html) || !str_contains($html, '<')) return;

            $scan($html);

            if (has_blocks($html)) $walk(parse_blocks($html));

        });

        return $counts;

    }

    // An element that goes takes its attributes with it.
    public static function subtract (array $before, array $after) : array {

        $lost = [];

        foreach ($before as $markup => $count) if (($left = $count - ($after[$markup] ?? 0)) > 0) $lost[$markup] = $left;

        foreach ($lost as $markup => $count) {

            if (!str_contains($markup, ' on ')) continue;

            $left = $count - ($lost[substr($markup, strrpos($markup, ' on ') + 4)] ?? 0);

            if ($left > 0) $lost[$markup] = $left;
            else unset($lost[$markup]);

        }

        return $lost;

    }

}
