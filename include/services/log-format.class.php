<?php

declare(strict_types=1);

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

        return iterator_to_array($this->read_entries(explode("\n", $chunk), $offset, $grouped), false);

    }

    // Entries from a file read line by line, for a scan that may stop early. Lines arrive without their newline.
    public function scan ($handle) : \Generator {

        $grouped = $this->holds_entry_start((string) fread($handle, static::HEAD_BYTES));
        rewind($handle);

        $lines = (function () use ($handle) { while (($line = fgets($handle)) !== false) yield rtrim($line, "\n"); })();

        yield from $this->read_entries($lines, 0, $grouped);

    }

    // One warning under a thousand stamps is one entry: consecutive entries fold when the text after the stamp, the body included, is the same.
    protected function read_entries (iterable $lines, int $offset, bool $grouped) : \Generator {

        $open = $held = $held_key = null;

        $close = function (array $open) use (&$held, &$held_key) : ?array {

            while ($open['body'] && (end($open['body']) === '') && !is_null($open['head'])) array_pop($open['body']);

            $key  = ($open['stamp'] === '') ? null : substr($open['head'], strlen($open['stamp'])) . "\n" . implode("\n", $open['body']);
            $done = null;

            if (!is_null($key) && ($key === $held_key)) {

                $held['count']++;
                $held['end']  = $open['end'];
                $held['last'] = $open['stamp'];

            } else {

                [$done, $held, $held_key] = [$held, $open, $key];

            }

            return $done;

        };

        foreach ($lines as $line) {

            $at      = $offset;
            $offset += strlen($line) + 1;
            $line    = rtrim($line, "\r");
            $stamp   = $grouped ? $this->read_stamp($line) : '';

            if (is_null($stamp) && $open) {

                $open['body'][] = $line;
                $open['end']    = $offset;
                continue;

            }

            if ($open && ($done = $close($open))) yield $done;

            $open = [
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

        if ($open && ($done = $close($open))) yield $done;
        if ($held) yield $held;

    }

    // $paint escapes a run of text (a token's or the text between two); a search marks its term there.
    public function tokenize (string $line, ?callable $paint = null) : string {

        $paint ??= fn ($text) => htmlspecialchars($text, static::ESCAPE);

        if (!$this->token_pattern || (strlen($line) > static::MAX_LINE)) return $paint($line);

        if (!@preg_match_all($this->token_pattern, $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return $paint($line);

        $html = '';
        $pos  = 0;

        foreach ($matches as $match) {

            [$text, $at] = $match[0];

            if ($text === '') continue;

            $name  = $this->token_names[array_key_last($match) - 1]; // trailing unmatched groups are dropped, so the last key is the group that matched
            $html .= $paint(substr($line, $pos, $at - $pos));
            $html .= "<span class='x-{$name}'>" . $paint($text) . "</span>";
            $pos   = $at + strlen($text);

        }

        return $html . $paint(substr($line, $pos));

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

}
