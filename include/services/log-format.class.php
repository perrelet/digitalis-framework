<?php

namespace Lattice;

class Log_Format {

    const MAX_LINE   = 8192;
    const HEAD_BYTES = 4096;
    const ESCAPE     = ENT_QUOTES | ENT_SUBSTITUTE;

    protected $levels      = [];
    protected $token_names = [];
    protected $entry_pattern;
    protected $token_pattern;

    public static function merge (array $themes, array $names, string $owner = self::class) : array {

        $merged = ['entry' => [], 'levels' => [], 'tokens' => []];

        foreach ($names as $name) {

            $theme = $themes[$name] ?? [];

            foreach ((array) ($theme['entry'] ?? []) as $pattern) {

                if (static::compiles($pattern)) $merged['entry'][] = $pattern;
                else static::refuse($owner, $name, 'entry', 'does not compile');

            }

            foreach ((array) ($theme['levels'] ?? []) as $level => $patterns) {

                $kept = array_values(array_filter((array) $patterns, [static::class, 'compiles']));

                if (count($kept) < count((array) $patterns)) static::refuse($owner, $name, "levels.{$level}", 'does not compile');

                $merged['levels'][$level] = array_merge($merged['levels'][$level] ?? [], $kept);

            }

            foreach ((array) ($theme['tokens'] ?? []) as $rule => $pattern) {

                unset($merged['tokens'][$rule]);

                if ($problem = static::find_token_problem($pattern)) static::refuse($owner, $name, "tokens.{$rule}", $problem);
                else $merged['tokens'][$rule] = $pattern;

            }

        }

        return $merged;

    }

    // Inside the alternation a rule's own group would renumber the others, and an empty match would loop.
    public static function find_token_problem ($pattern) : ?string {

        if (!is_string($pattern) || !static::compiles($pattern)) return 'does not compile';

        if (preg_match('/\\\\(?:[1-9]|[gk][{<\'\d-])/', $pattern)) return 'holds a backreference';

        $bare = preg_replace('/\[\^?\]?[^\]]*\]/', '', preg_replace('/\\\\./s', '', $pattern));

        if (preg_match('/\((?![?*])|\(\?P?<(?![=!])|\(\?\'|\(\?P[=>]/', $bare)) return 'holds a capturing group';

        if (preg_match("/{$pattern}/", '') || (preg_match_all("/{$pattern}/", " a1 'b' [c] /d ", $found) && in_array('', $found[0], true))) return 'matches the empty string';

        return null;

    }

    protected static function compiles ($pattern) : bool {

        return is_string($pattern) && ($pattern !== '') && (@preg_match("/{$pattern}/", '') !== false);

    }

    protected static function refuse (string $owner, string $theme, string $rule, string $problem) : void {

        $error = "declares the `{$theme}` theme rule `{$rule}`, which {$problem}, so the rule is skipped.";

        Strict::violation($owner, $error, 'Write the rule as a pattern for the raw line, delimited by `/`, with non-capturing groups only and at least one character matched.');
        Strict::log_once($owner, $error);

    }

    public function __construct (array $theme) {

        $this->levels = $theme['levels'] ?? [];

        if ($entry = ($theme['entry'] ?? [])) $this->entry_pattern = '/(?:' . implode(')|(?:', $entry) . ')/m';

        if ($tokens = ($theme['tokens'] ?? [])) {

            $this->token_names = array_keys($tokens);
            $groups            = [];

            foreach (array_values($tokens) as $i => $pattern) $groups[] = "(?<t{$i}>{$pattern})"; // a group name takes no hyphen, and rule names have one

            $this->token_pattern = '/' . implode('|', $groups) . '/';

        }

    }

    public function holds_entry_start (string $text) : bool {

        return $this->entry_pattern && (preg_match($this->entry_pattern, $text) === 1);

    }

    // $head is the file's first bytes: a page deep in the file groups the way its first page does.
    public function parse (string $chunk, int $offset = 0, string $head = '') : array {

        if (str_ends_with($chunk, "\n")) $chunk = substr($chunk, 0, -1);
        if ($chunk === '') return [];

        $grouped = $this->holds_entry_start($head) || $this->holds_entry_start($chunk);
        $entries = [];

        foreach (explode("\n", $chunk) as $line) {

            $at      = $offset;
            $offset += strlen($line) + 1;
            $line    = rtrim($line, "\r");
            $stamp   = $grouped ? $this->read_stamp($line) : '';
            $last    = array_key_last($entries);

            if (is_null($stamp) && !is_null($last)) {

                $entries[$last]['body'][] = $line;
                $entries[$last]['end']    = $offset;
                continue;

            }

            $entries[] = [
                'offset' => $at,
                'end'    => $offset,
                'stamp'  => (string) $stamp,
                'last'   => (string) $stamp,
                'level'  => is_null($stamp) ? '' : $this->read_level($line),
                'head'   => is_null($stamp) ? null : $line,
                'body'   => is_null($stamp) ? [$line] : [],
                'count'  => 1,
            ];

        }

        return $this->fold($entries);

    }

    public function tokenize (string $line) : string {

        if (!$this->token_pattern || (strlen($line) > static::MAX_LINE)) return htmlspecialchars($line, static::ESCAPE);

        if (!@preg_match_all($this->token_pattern, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return htmlspecialchars($line, static::ESCAPE);

        $html = '';
        $pos  = 0;

        foreach ($matches as $match) {

            [$text, $at] = $match[0];

            if ($text === '') continue;

            $name  = $this->token_names[array_key_last($match) - 1]; // trailing unmatched groups are dropped, so the last key is the group that matched
            $html .= htmlspecialchars(substr($line, $pos, $at - $pos), static::ESCAPE);
            $html .= "<span class='x-{$name}'>" . htmlspecialchars($text, static::ESCAPE) . "</span>";
            $pos   = $at + strlen($text);

        }

        return $html . htmlspecialchars(substr($line, $pos), static::ESCAPE);

    }

    protected function read_stamp (string $line) : ?string {

        if (!$this->entry_pattern) return null;

        return preg_match($this->entry_pattern, substr($line, 0, static::MAX_LINE), $match) ? $match[0] : null;

    }

    protected function read_level (string $head) : string {

        $head = substr($head, 0, static::MAX_LINE);

        foreach ($this->levels as $level => $patterns) {

            foreach ($patterns as $pattern) if (preg_match("/{$pattern}/", $head)) return (string) $level;

        }

        return '';

    }

    // One warning under a thousand stamps is one entry: the text after the stamp decides, the body included.
    protected function fold (array $entries) : array {

        $folded = [];
        $key    = null;

        foreach ($entries as $entry) {

            while ($entry['body'] && (end($entry['body']) === '') && !is_null($entry['head'])) array_pop($entry['body']);

            $last     = array_key_last($folded);
            $this_key = ($entry['stamp'] === '') ? null : substr($entry['head'], strlen($entry['stamp'])) . "\n" . implode("\n", $entry['body']);

            if (!is_null($this_key) && ($this_key === $key)) {

                $folded[$last]['count']++;
                $folded[$last]['end']  = $entry['end'];
                $folded[$last]['last'] = $entry['stamp'];

            } else {

                $folded[] = $entry;

            }

            $key = $this_key;

        }

        return $folded;

    }

}
