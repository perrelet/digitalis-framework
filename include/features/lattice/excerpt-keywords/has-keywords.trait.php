<?php

namespace Digitalis;

trait Has_Keywords {

    public function get_keyword_substitutions () {

        return [];

    }

    public function get_keyword_preserve_chars () {

        return '';

    }

    public function get_keyword_min_length () {

        return 2;

    }

    public function get_keyword_stop_words () {

        return ['the','a','an','and','or','but','is','are','was','were','be','to','of','in','for','on','with','at','by','it','this','that','from','as','not','has','have','had','its','can','will','do','does','did'];

    }

    public function collect_keywords () {

        return [];

    }

    public function generate_keywords () {

        $keywords = $this->collect_keywords();
        $keywords = is_array($keywords) ? implode(' ', array_filter(array_map([$this, 'stringify_keyword'], $keywords))) : (string) $keywords;
        $keywords = apply_filters('lattice.keywords.collected', $keywords, $this);

        return $this->filter_keywords($keywords);

    }

    protected function stringify_keyword ($value) {

        if (is_array($value)) return implode(' ', array_filter(array_map([$this, 'stringify_keyword'], $value)));
        return (string) $value;

    }

    public function update_keywords () {

        if (!$keywords = $this->generate_keywords()) return;

        $this->wp_post->post_excerpt = $keywords;

        // Targeted update + suppress after-hooks: (1) avoids re-entering
        // wp_after_insert_post (recursion), (2) avoids propagating a stale
        // cached post_status if firing inside another save's hook chain.
        wp_update_post(['ID' => $this->get_id(), 'post_excerpt' => $keywords], false, false);

        do_action('lattice.keywords.updated', $this, $keywords);

    }

    public function filter_keywords ($text) {

        if ($substitutions = $this->get_keyword_substitutions()) $text = strtr($text, $substitutions);

        $text = strtolower(strip_tags($text));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Preserve intra-word `.` and `-` (model numbers, versions, ranges,
        // URLs) by swapping to placeholders before the punctuation strip,
        // then restoring. Sentence-level punctuation drops through.
        $text = preg_replace('/(?<=[\p{L}\p{N}])\.(?=[\p{L}\p{N}])/u', "\x01", $text);
        $text = preg_replace('/(?<=[\p{L}\p{N}])-(?=[\p{L}\p{N}])/u', "\x02", $text);

        $preserved = preg_quote($this->get_keyword_preserve_chars(), '/');
        $text = preg_replace("/[^\\p{L}\\p{N}\\s{$preserved}\x01\x02]/u", ' ', $text);
        $text = strtr($text, ["\x01" => '.', "\x02" => '-']);
        $text = preg_replace('/\s+/', ' ', trim($text));

        $words = array_diff(explode(' ', $text), $this->get_keyword_stop_words());

        if (($min = $this->get_keyword_min_length()) > 0) {
            $words = array_filter($words, fn ($w) => mb_strlen($w) >= $min);
        }

        return implode(' ', array_unique($words));

    }

    public function get_keywords () {

        return (string) $this->wp_post->post_excerpt;

    }

}
