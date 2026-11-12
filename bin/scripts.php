<?php
// The script rule (docs/SCRIPTS.md, Gates): a script outside the legacy list holds none of the banned forms.
// `php bin/scripts.php [framework-root]` walks the tree; `--list [root]` prints the scripts it would check;
// `--check file...` checks the named files alone. Exit 1 on a hit.
PHP_SAPI === 'cli' || exit;
if (PHP_VERSION_ID < 80000) { fwrite(STDERR, "PHP 8.0+ required\n"); exit(2); }

// Mirrors the Existing Scripts table of docs/SCRIPTS.md: a script leaves both when it is rewritten.
$legacy = [
    'assets/js/acf-ajax.class.js',
    'assets/js/counter.js',
    'assets/js/debugger.js',
    'assets/js/fields.js',
    'assets/js/human-check-turnstile.js',
    'assets/js/human-check.js',
    'assets/js/iterator.js',
    'assets/js/menu-drawer.js',
    'assets/js/menu.js',
    'assets/js/woo-clean.js',
    'include/_features/lattice/page-loader/page-loader.js',
    'include/_features/lattice/proposals/proposal-status.js',
    'include/_features/lattice/proposals/proposals.js',
    'include/_features/lattice/scroll-classes/scroll-classes.js',
    'modules/mapbox.js',
    'modules/query.js',
];

$banned = [
    '/\bvar\s/'                                                                  => 'declares with var',
    '/\bsetInterval\b/'                                                          => 'calls setInterval',
    '/\bXMLHttpRequest\b/'                                                       => 'names XMLHttpRequest',
    '/\bconsole\./'                                                              => 'writes to the console',
    '/\bdocument\.write/'                                                        => 'calls document.write',
    '/\.(inner|outer)HTML\s*\+?=(?![=>])/'                                       => 'assigns markup',
    '/\b(window|globalThis|self)\s*(\.\s*(?!location\b)\w+|\[[^\]]*\])\s*\+?=(?![=>])/' => 'assigns a global',
    '/(?<![=!<>])==(?!=)|!=(?!=)/'                                               => 'compares loosely',
];

$mode  = in_array($argv[1] ?? '', ['--list', '--check'], true) ? $argv[1] : '';
$args  = array_slice($argv, $mode ? 2 : 1);
$hits  = [];
$files = [];

if ($mode === '--check') {

    foreach ($args as $file) $files[$file] = $file;

} else {

    $root = realpath($args[0] ?? dirname(__DIR__));

    if (!$root || !is_dir("$root/assets/js")) { fwrite(STDERR, "No assets/js under " . ($root ?: 'the given root') . "\n"); exit(2); }

    foreach ($legacy as $rel) if (!is_file("$root/$rel")) $hits[] = "$rel is listed as legacy and is gone";

    foreach (['assets/js', 'include', 'modules'] as $dir) {

        if (!is_dir("$root/$dir")) continue;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)) as $file) {

            $rel = substr((string) $file, strlen($root) + 1);
            if ($file->getExtension() === 'js' && !in_array($rel, $legacy, true)) $files[$rel] = (string) $file;

        }

    }

    ksort($files);

}

if ($mode === '--list') { foreach ($files as $path) echo "$path\n"; exit(0); }

foreach ($files as $rel => $path) {

    $source = file_get_contents($path);

    if ($source === false) { $hits[] = "$rel cannot be read"; continue; }
    if (str_contains($source, "\r")) $hits[] = "$rel has CR line endings";

    [$code, $comments, $blocks] = split_script($source);

    foreach ($blocks as $line) $hits[] = "$rel:$line opens a block comment";

    foreach ($comments as $line => $text) if (preg_match('/[;{]\s*$/', $text)) $hits[] = "$rel:$line comments out a statement";

    foreach (explode("\n", $source) as $i => $raw) {

        if (preg_match('/\[\s*[\'"`](inner|outer)HTML[\'"`]\s*\]\s*\+?=(?![=>])/', $raw)) $hits[] = "$rel:" . ($i + 1) . " assigns markup";

    }

    foreach (explode("\n", $code) as $i => $line) {

        foreach ($banned as $pattern => $reason) if (preg_match($pattern, $line)) $hits[] = "$rel:" . ($i + 1) . " $reason";

    }

}

foreach ($hits as $hit) echo "  $hit\n";
printf("RESULT: %s\n", $hits ? count($hits) . ' script violation(s)' : 'clean');
exit($hits ? 1 : 0);

// The source with string, template and regex contents blanked and comments removed, line for line; the line
// comments by line; the lines that open a block comment.
function split_script (string $source) : array {

    $code     = '';
    $comments = [];
    $blocks   = [];
    $line     = 1;
    $n        = strlen($source);
    $last     = '';

    for ($i = 0; $i < $n; $i++) {

        $c    = $source[$i];
        $next = $source[$i + 1] ?? '';

        if ($c === '/' && $next === '/') {

            $end             = strpos($source, "\n", $i);
            $end             = ($end === false) ? $n : $end;
            $comments[$line] = substr($source, $i + 2, $end - $i - 2);
            $i               = $end - 1;
            continue;

        }

        if ($c === '/' && $next === '*') {

            $blocks[] = $line;
            $end      = strpos($source, '*/', $i + 2);
            $end      = ($end === false) ? $n : $end + 2;
            $skipped  = substr($source, $i, $end - $i);
            $code    .= str_repeat("\n", substr_count($skipped, "\n"));
            $line    += substr_count($skipped, "\n");
            $i        = $end - 1;
            continue;

        }

        // A slash opens a regex where a value may start, and divides anywhere else.
        $opens_regex = ($c === '/') && ($last === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $last) || preg_match('/\b(return|typeof|case|of|in)\s*$/', $code));

        if ($c === "'" || $c === '"' || $c === '`' || $opens_regex) {

            $code .= $c;

            for ($i++; $i < $n && $source[$i] !== $c; $i++) {

                if ($source[$i] === '\\') $i++;
                if (($source[$i] ?? '') === "\n") { $code .= "\n"; $line++; }

            }

            $code .= $c;
            $last  = $c;
            continue;

        }

        if ($c === "\n") $line++;
        if (!ctype_space($c)) $last = $c;

        $code .= $c;

    }

    return [$code, $comments, $blocks];

}
