<?php
// The variance audit: every consumer override of a framework member, and whether the framework tree as it stands would
// fatal a consumer at class-declaration time. `php bin/variance.php [--framework=PATH] [--matrix] CONSUMER_ROOT...`.
// Pass dependent consumers together (an unresolved namespaced parent is a FATAL, never a silent gap). Exit 1 on a FATAL,
// 2 on usage. CHECK lines are relations to classes outside every scanned tree (WordPress, WooCommerce, ACF) and never fail.
// Blind spots: class_alias beyond the Digitalis shim, eval and generated classes, `__call` methods, parameter renames
// (named-argument callers only), and everything else PHP reports at compile time, such as two traits colliding.
PHP_SAPI === 'cli' || exit;
if (PHP_VERSION_ID < 80000) { fwrite(STDERR, "PHP 8.0+ required\n"); exit(2); }

// Mirrors compat/digitalis-namespace.php; ~/lattice-v1-tests/variance.sh asserts the two lists match.
const SUB_NAMESPACES = ['Component', 'Field', 'DB', 'WP', 'Woo', 'ACF', 'Oxygen'];
const BUILTIN_TYPES  = ['int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'void', 'null', 'never', 'false', 'true', 'self', 'static', 'parent'];

$framework = dirname(__DIR__);
$matrix    = false;
$roots     = [];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--framework=')) $framework = substr($arg, 12);
    elseif ($arg === '--matrix')               $matrix = true;
    elseif (str_starts_with($arg, '--'))       usage("Unknown option $arg");
    else                                       $roots[] = $arg;
}

if (!$roots) usage('No consumer roots given');
$framework = realpath($framework);
if (!$framework || !is_dir("$framework/include")) usage('No include/ under the framework root');
foreach ($roots as &$root) { $real = realpath($root); if (!$real || !is_dir($real)) usage("Not a directory: $root"); $root = $real; }
unset($root);

function usage (string $message): void {
    fwrite(STDERR, "$message\nUsage: php bin/variance.php [--framework=PATH] [--matrix] CONSUMER_ROOT...\n");
    exit(2);
}

// ---------------------------------------------------------------------------------------------------------------------
// Parsing

// Dot-directories, vendor/, node_modules/ and any nested repository (a `.git` file or directory) are skipped; the root never is.
function php_files (string $root): array {
    $files = [];
    $walk  = function (string $dir) use (&$walk, &$files): void {
        foreach (scandir($dir) as $entry) {
            if ($entry[0] === '.') continue;
            $path = "$dir/$entry";
            if (is_dir($path)) {
                if ($entry === 'vendor' || $entry === 'node_modules' || file_exists("$path/.git")) continue;
                $walk($path);
            } elseif (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }
    };
    $walk($root);
    return $files;
}

function is_tok (mixed $tok, array $ids): bool { return is_array($tok) && in_array($tok[0], $ids, true); }
// PHP 8.1+ tokenizes `&` as T_AMPERSAND_*; 8.0 as a bare character.
function is_amp (mixed $tok): bool { return $tok === '&' || (is_array($tok) && $tok[1] === '&'); }

$NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
$SKIP_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
$MODIFIERS   = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_VAR];
if (defined('T_READONLY')) $MODIFIERS[] = T_READONLY;
$DECL_TOKENS = [T_CLASS, T_TRAIT, T_INTERFACE];
if (defined('T_ENUM')) $DECL_TOKENS[] = T_ENUM;

// A class-like name written in a file, resolved against that file's namespace and imports. Builtin type names pass through.
function resolve_name (string $raw, string $ns, array $uses): string {
    $lower = strtolower($raw);
    if (in_array($lower, BUILTIN_TYPES, true)) return $lower;
    if ($raw[0] === '\\') return substr($raw, 1);
    if (str_starts_with($lower, 'namespace\\')) return ($ns ? "$ns\\" : '') . substr($raw, 10);
    $first = strstr($raw, '\\', true) ?: $raw;
    if (isset($uses[strtolower($first)])) return $uses[strtolower($first)] . substr($raw, strlen($first));
    return ($ns ? "$ns\\" : '') . $raw;
}

// Type text (`?A|B`, `A&B`, `(A&B)|null`) -> sorted set of resolved lowercased names. null when no type was written.
function parse_type (array $parts, string $ns, array $uses): ?array {
    if (!$parts) return null;
    $set = []; $group = [];
    $flush = function () use (&$set, &$group): void { if ($group) { sort($group); $set[implode('&', $group)] = true; } $group = []; };
    foreach ($parts as $part) {
        if ($part === '?') { $set['null'] = true; continue; }
        if ($part === '|') { $flush(); continue; }
        if (in_array($part, ['&', '(', ')'], true)) continue;                // `A&B` stays one member, joined below
        $group[] = strtolower(resolve_name($part, $ns, $uses));
    }
    $flush();
    $set = array_keys($set);
    if (in_array('mixed', $set, true)) return ['mixed'];
    sort($set);
    return $set;
}

function parse_file (string $file): array {
    global $NAME_TOKENS, $SKIP_TOKENS, $MODIFIERS, $DECL_TOKENS;
    $tokens = token_get_all(file_get_contents($file));
    $n      = count($tokens);
    $ns     = '';
    $nsd    = null;   // depth of a braced namespace block, so the namespace resets when it closes
    $uses   = [];
    $decls  = [];
    $cur    = null;
    $depth  = 0;
    $cdepth = null;   // depth inside the current declaration's body
    $mods   = [];     // pending modifier and type tokens at class depth
    $skip   = fn (int $i, int $dir = 1) => adjacent($tokens, $i, $dir, $SKIP_TOKENS);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if ($t === '{' || is_tok($t, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { $depth++; $mods = []; continue; }
        if ($t === '}') {
            $depth--; $mods = [];
            if ($cur !== null && $depth < $cdepth) { $decls[] = $cur; $cur = null; }
            if ($nsd !== null && $depth < $nsd) { $ns = ''; $uses = []; $nsd = null; }
            continue;
        }
        if (is_tok($t, [T_ATTRIBUTE])) { $i = skip_brackets($tokens, $i, '[', ']'); continue; }
        if (!is_array($t)) {
            if ($t === ';') $mods = [];
            elseif (($t === '?' || $t === '|') && $cur !== null && $depth === $cdepth) $mods[] = [0, $t];   // nullable and union property types
            continue;
        }

        if ($t[0] === T_NAMESPACE) {
            $j = $skip($i);
            $ns = ''; $uses = [];
            if (is_tok($tokens[$j], $NAME_TOKENS)) { $ns = $tokens[$j][1]; $j = $skip($j); }
            if ($tokens[$j] === '{') $nsd = $depth + 1;
            $i = $j - 1;
            continue;
        }

        if ($t[0] === T_USE && $cur === null && ($depth === 0 || $depth === $nsd)) {
            // File-level import; `) use (` is a closure capture. A `use` deeper down belongs to an anonymous class.
            $j = $skip($i);
            if ($tokens[$j] === '(') continue;
            $end = index_of($tokens, $i, ';') ?? $n - 1;
            $text = '';
            for ($k = $i + 1; $k < $end; $k++) $text .= is_array($tokens[$k]) ? (in_array($tokens[$k][0], $SKIP_TOKENS, true) ? ' ' : $tokens[$k][1]) : $tokens[$k];
            $text = trim($text);
            if (!preg_match('/^(function|const)\b/i', $text)) {
                $prefix = '';
                if (preg_match('/^(.*?)\\\\?\{(.*)\}$/s', $text, $m)) { $prefix = ltrim($m[1], '\\') . '\\'; $text = $m[2]; }
                foreach (explode(',', $text) as $item) {
                    $item = trim($item);
                    if ($item === '' || preg_match('/^(function|const)\b/i', $item)) continue;
                    $bits  = preg_split('/\s+as\s+/i', $item);
                    $full  = $prefix . ltrim($bits[0], '\\');
                    $alias = $bits[1] ?? substr(strrchr('\\' . $full, '\\'), 1);
                    $uses[strtolower($alias)] = $full;
                }
            }
            $i = $end;
            continue;
        }

        if ($cur === null && in_array($t[0], [T_FINAL, T_ABSTRACT], true)) { $mods[] = $t; continue; }   // modifiers of the coming declaration
        if (in_array($t[0], $DECL_TOKENS, true) && $cur === null) {
            $prev = $tokens[$skip($i, -1)] ?? null;
            if (is_tok($prev, [T_NEW, T_DOUBLE_COLON])) continue;                 // `new class`, `::class`
            $j = $skip($i);
            if (!is_tok($tokens[$j], [T_STRING])) continue;                      // anonymous class
            $kind = ['class', 'trait', 'interface', 'enum'][array_search($t[0], [T_CLASS, T_TRAIT, T_INTERFACE, defined('T_ENUM') ? T_ENUM : -1], true)];
            $cur  = [
                'kind' => $kind, 'name' => ($ns ? "$ns\\" : '') . $tokens[$j][1], 'file' => $file, 'line' => $t[2], 'ns' => $ns, 'uses' => $uses,
                'final' => false, 'abstract' => false, 'parent' => null, 'interfaces' => [], 'traits' => [], 'exclusions' => [], 'aliases' => [],
                'methods' => [], 'properties' => [],
            ];
            foreach ($mods as $m) { if ($m[0] === T_FINAL) $cur['final'] = true; if ($m[0] === T_ABSTRACT) $cur['abstract'] = true; }
            $mods = [];
            // Header: extends / implements up to the body.
            $mode = null;
            for ($k = $j + 1; $k < $n && $tokens[$k] !== '{'; $k++) {
                $tk = $tokens[$k];
                if (is_tok($tk, [T_EXTENDS]))    { $mode = 'extends';    continue; }
                if (is_tok($tk, [T_IMPLEMENTS])) { $mode = 'implements'; continue; }
                if (is_tok($tk, $NAME_TOKENS) && $mode) {
                    $name = resolve_name($tk[1], $ns, $uses);
                    if ($mode === 'extends' && $kind !== 'interface' && $cur['parent'] === null) $cur['parent'] = $name;
                    else $cur['interfaces'][] = $name;                           // interfaces extend many; classes implement many
                }
            }
            $cdepth = $depth + 1;
            $i = $k - 1;
            continue;
        }

        if ($cur === null || $depth !== $cdepth) continue;

        if ($t[0] === T_USE) {                                                   // trait use, with an optional adaptation block
            $end = index_of($tokens, $i, ';') ?? $n - 1;
            $brace = index_of($tokens, $i, '{');
            $stop = ($brace !== null && $brace < $end) ? $brace : $end;
            $names = [];
            for ($k = $i + 1; $k < $stop; $k++) if (is_tok($tokens[$k], $NAME_TOKENS)) $names[] = resolve_name($tokens[$k][1], $ns, $uses);
            foreach ($names as $name) $cur['traits'][] = $name;
            if ($stop === $brace) {
                $close = skip_brackets($tokens, $brace, '{', '}');
                foreach (explode(';', tokens_text($tokens, $brace + 1, $close)) as $rule) {
                    $rule = trim($rule);
                    if ($rule === '') continue;
                    if (preg_match('/^(?:([\w\\\\]+)::)?(\w+)\s+insteadof\s+(.+)$/i', $rule, $m)) {
                        foreach (preg_split('/\s*,\s*/', trim($m[3])) as $loser) $cur['exclusions'][strtolower(resolve_name($loser, $ns, $uses))][strtolower($m[2])] = true;
                    } elseif (preg_match('/^(?:([\w\\\\]+)::)?(\w+)\s+as\s+(?:(public|protected|private)\b\s*)?(\w+)?$/i', $rule, $m)) {
                        $cur['aliases'][] = ['trait' => $m[1] !== '' ? strtolower(resolve_name($m[1], $ns, $uses)) : null, 'method' => strtolower($m[2]), 'vis' => $m[3] !== '' ? strtolower($m[3]) : null, 'alias' => $m[4] ?? null];
                    }
                }
                $i = $close;                                                     // the loop never saw the block's braces, so depth is untouched
            } else {
                $i = $end;
            }
            $mods = [];
            continue;
        }

        if (in_array($t[0], [T_CONST, T_CASE], true)) { $i = index_of($tokens, $i, ';') ?? $n - 1; $mods = []; continue; }

        if (in_array($t[0], $MODIFIERS, true) || in_array($t[0], $NAME_TOKENS, true) || $t[0] === T_ARRAY || $t[0] === T_CALLABLE) { $mods[] = $t; continue; }

        if ($t[0] === T_FUNCTION) {
            $method = parse_function($tokens, $i, $mods, $ns, $uses, $end);
            if ($method) {
                $cur['methods'][strtolower($method['name'])] = $method;
                foreach ($method['params'] as $param) if ($param['promoted'])
                    $cur['properties'][strtolower($param['name'])] = ['name' => $param['name'], 'line' => $method['line'], 'vis' => $param['vis'], 'static' => false, 'readonly' => $param['readonly'], 'type' => $param['type'], 'default' => null];
            }
            $mods = [];
            $i = $end - 1;                                                       // let the loop count the body's `{`
            continue;
        }

        if ($t[0] === T_VARIABLE) {                                              // property, possibly one of several: `static $a = [], $b;`
            $prop = property_from_modifiers($mods, $ns, $uses);
            $prop['name'] = substr($t[1], 1);
            $prop['line'] = $t[2];
            $end = index_of_any($tokens, $i, [',', ';']);
            $prop['default'] = $end !== null && $tokens[$skip($i)] === '=' ? trim(tokens_text($tokens, $skip($i) + 1, $end)) : null;
            $cur['properties'][strtolower($prop['name'])] = $prop;
            if ($end !== null && $tokens[$end] === ',') { $i = $end; continue; }                 // the modifiers carry over to the next property
            $mods = [];
            $i = $end ?? $i;
            continue;
        }
    }

    if ($cur !== null) $decls[] = $cur;
    return $decls;
}

function adjacent (array $tokens, int $i, int $dir, array $skip): int {
    $n = count($tokens);
    for ($j = $i + $dir; $j >= 0 && $j < $n; $j += $dir) if (!is_tok($tokens[$j], $skip)) return $j;
    return $dir > 0 ? $n - 1 : 0;
}

function index_of (array $tokens, int $from, string $char): ?int {
    for ($n = count($tokens), $j = $from + 1; $j < $n; $j++) if ($tokens[$j] === $char) return $j;
    return null;
}

// First of the chars at bracket depth 0 (arrays and calls in defaults contain commas).
function index_of_any (array $tokens, int $from, array $chars): ?int {
    $d = 0;
    for ($n = count($tokens), $j = $from + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if ($d === 0 && in_array($t, $chars, true)) return $j;
        if ($t === '(' || $t === '[' || $t === '{' || is_tok($t, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $d++;
        elseif ($t === ')' || $t === ']' || $t === '}') $d--;
    }
    return null;
}

function skip_brackets (array $tokens, int $open, string $o, string $c): int {
    $d = 0;
    for ($n = count($tokens), $j = $open; $j < $n; $j++) {
        $t = $tokens[$j];
        if ($t === $o || ($o === '[' && is_tok($t, [T_ATTRIBUTE])) || ($o === '{' && is_tok($t, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]))) $d++;
        elseif ($t === $c && --$d === 0) return $j;
    }
    return $n - 1;
}

function tokens_text (array $tokens, int $from, int $to): string {
    $s = '';
    for ($j = $from; $j < $to; $j++) $s .= is_array($tokens[$j]) ? (in_array($tokens[$j][0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $tokens[$j][1]) : $tokens[$j];
    return preg_replace('/\s+/', ' ', $s);
}

// Composition compares values, not text: `$x;` equals `$x = null`, `array()` equals `[]`, whitespace is nothing.
function default_value (?string $text): string {
    $v = strtolower(preg_replace('/\s+/', '', (string) $text));
    $v = preg_replace('/^array\(\)$/', '[]', $v);
    return $v === '' ? 'null' : $v;
}

function property_from_modifiers (array $mods, string $ns, array $uses): array {
    $prop = ['vis' => 'public', 'static' => false, 'readonly' => false, 'type' => null];
    $type = [];
    foreach ($mods as $m) {
        if ($m[0] === T_PROTECTED) $prop['vis'] = 'protected';
        elseif ($m[0] === T_PRIVATE) $prop['vis'] = 'private';
        elseif ($m[0] === T_STATIC) $prop['static'] = true;
        elseif (defined('T_READONLY') && $m[0] === T_READONLY) $prop['readonly'] = true;
        elseif (!in_array($m[0], [T_PUBLIC, T_VAR, T_ABSTRACT, T_FINAL], true)) $type[] = $m[1];
    }
    $prop['type'] = parse_type($type, $ns, $uses);
    return $prop;
}

function parse_function (array $tokens, int $i, array $mods, string $ns, array $uses, ?int &$end): ?array {
    global $NAME_TOKENS, $SKIP_TOKENS;
    $n = count($tokens);
    $j = adjacent($tokens, $i, 1, $SKIP_TOKENS);
    $byref = false;
    if (is_amp($tokens[$j])) { $byref = true; $j = adjacent($tokens, $j, 1, $SKIP_TOKENS); }
    if (!is_array($tokens[$j]) || !preg_match('/^[A-Za-z_]\w*$/', $tokens[$j][1])) { $end = index_of_any($tokens, $i, ['{', ';']) ?? $i + 1; return null; } // a closure at class depth cannot happen; be safe
    $method = ['name' => $tokens[$j][1], 'line' => $tokens[$j][2], 'vis' => 'public', 'static' => false, 'abstract' => false, 'final' => false, 'byref' => $byref, 'params' => [], 'return' => null, 'return_raw' => ''];
    foreach ($mods as $m) {
        if ($m[0] === T_PROTECTED) $method['vis'] = 'protected';
        elseif ($m[0] === T_PRIVATE) $method['vis'] = 'private';
        elseif ($m[0] === T_STATIC) $method['static'] = true;
        elseif ($m[0] === T_ABSTRACT) $method['abstract'] = true;
        elseif ($m[0] === T_FINAL) $method['final'] = true;
    }
    $open  = index_of($tokens, $j, '(');
    $close = skip_brackets($tokens, $open, '(', ')');
    // Parameters, split on commas at bracket depth 0.
    $param = []; $d = 0;
    for ($k = $open + 1; $k <= $close; $k++) {
        $t = $tokens[$k];
        if ($k === $close || ($t === ',' && $d === 0)) {
            if ($param) $method['params'][] = parse_param($param, $ns, $uses);
            $param = [];
            continue;
        }
        if ($t === '(' || $t === '[') $d++;
        if ($t === ')' || $t === ']') $d--;
        if (is_tok($t, [T_ATTRIBUTE])) { $k = skip_brackets($tokens, $k, '[', ']'); continue; }
        if (is_tok($t, $SKIP_TOKENS)) continue;
        $param[] = $t;
    }
    // Return type up to `{` or `;`.
    $end = index_of_any($tokens, $close, ['{', ';']) ?? $n - 1;
    $type = [];
    for ($k = $close + 1; $k < $end; $k++) {
        $t = $tokens[$k];
        if ($t === ':' || is_tok($t, $SKIP_TOKENS)) continue;
        if (is_amp($t)) continue;
        $type[] = is_array($t) ? $t[1] : $t;
    }
    $method['return']     = parse_type($type, $ns, $uses);
    $method['return_raw'] = implode('', $type);
    return $method;
}

function parse_param (array $toks, string $ns, array $uses): array {
    $p = ['name' => '', 'type' => null, 'raw' => '', 'byref' => false, 'variadic' => false, 'optional' => false, 'promoted' => false, 'vis' => 'public', 'readonly' => false];
    $type = []; $seen_var = false; $default = [];
    foreach ($toks as $idx => $t) {
        if ($seen_var) { if ($t !== '=') $default[] = is_array($t) ? $t[1] : $t; continue; }
        if (is_amp($t)) {
            $next = $toks[$idx + 1] ?? null;
            if (is_tok($next, [T_VARIABLE, T_ELLIPSIS])) $p['byref'] = true;  // otherwise an intersection type's `&`
            else $type[] = '&';
            continue;
        }
        if (is_array($t)) {
            if ($t[0] === T_VARIABLE) { $p['name'] = substr($t[1], 1); $seen_var = true; continue; }
            if (in_array($t[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) { $p['promoted'] = true; $p['vis'] = strtolower($t[1]); continue; }
            if (defined('T_READONLY') && $t[0] === T_READONLY) { $p['promoted'] = true; $p['readonly'] = true; continue; }
            if ($t[0] === T_ELLIPSIS) { $p['variadic'] = true; continue; }
            $type[] = $t[1];
        } else {
            $type[] = $t;
        }
    }
    $p['raw'] = implode('', $type);
    $p['type'] = parse_type($type, $ns, $uses);
    if ($default || $p['variadic']) $p['optional'] = true;
    if ($default && strtolower(trim(implode('', $default))) === 'null' && $p['type'] !== null && $p['type'] !== ['mixed'] && !in_array('null', $p['type'], true)) { $p['type'][] = 'null'; sort($p['type']); }
    return $p;
}

// ---------------------------------------------------------------------------------------------------------------------
// Tables

$fw_decls = [];
foreach (php_files("$framework/include") as $file) foreach (parse_file($file) as $decl) $fw_decls[strtolower($decl['name'])] = $decl;

$consumers  = [];                                                            // lowercased FQN => list of decls (duplicates kept)
$per_root   = [];
$warnings   = [];
$namespaces = [];                                                            // top-level segments declared anywhere
foreach ($fw_decls as $d) $namespaces[strtolower(strstr($d['name'], '\\', true) ?: '')] = true;

foreach ($roots as $root) {
    $label = basename($root);
    $per_root[$label] = 0;
    foreach (php_files($root) as $file) {
        foreach (parse_file($file) as $decl) {
            $decl['root'] = $label;
            $decl['rel']  = $label . '/' . substr($file, strlen($root) + 1);
            $key = strtolower($decl['name']);
            if (lookup_framework($decl['name'])) { $warnings[] = "skipped {$decl['rel']}:{$decl['line']}: {$decl['name']} redeclares a framework class (a stub)"; continue; }
            if (isset($consumers[$key])) $warnings[] = "duplicate {$decl['name']} in {$decl['rel']}:{$decl['line']} (also {$consumers[$key][0]['rel']}:{$consumers[$key][0]['line']}); both compared";
            $consumers[$key][] = $decl;
            $per_root[$label]++;
            $namespaces[strtolower(strstr($decl['name'], '\\', true) ?: '')] = true;
        }
    }
}
unset($namespaces['']);

function alias_digitalis (string $name): string { return str_starts_with(strtolower($name), 'digitalis\\') ? 'Lattice\\' . substr($name, 10) : $name; }

// A framework declaration by name, resolved as the compat shim would: Digitalis\X -> Lattice\X, then the sub-namespaces.
function lookup_framework (string $name): ?array {
    global $fw_decls;
    $aliased = strtolower(alias_digitalis($name));
    if (isset($fw_decls[$aliased])) return $fw_decls[$aliased];
    if (str_starts_with($aliased, 'lattice\\') && !str_contains(substr($aliased, 8), '\\')) {
        foreach (SUB_NAMESPACES as $sub) { $q = 'lattice\\' . strtolower($sub) . '\\' . substr($aliased, 8); if (isset($fw_decls[$q])) return $fw_decls[$q]; }
    }
    return null;
}

// A declaration by name: framework first, then the consumers (raw name as a fallback for real Digitalis\ consumer classes).
function lookup (string $name): ?array {
    global $consumers;
    if ($fw = lookup_framework($name)) return $fw;
    $aliased = strtolower(alias_digitalis($name));
    if (isset($consumers[$aliased])) return $consumers[$aliased][0];
    if (isset($consumers[strtolower($name)])) return $consumers[strtolower($name)][0];
    return null;
}

function is_framework (string $name): bool { global $fw_decls; return isset($fw_decls[strtolower(alias_digitalis($name))]); }
function is_namespaced (string $name): bool { return str_contains($name, '\\'); }
function in_scanned_namespace (string $name): bool { global $namespaces; return is_namespaced($name) && isset($namespaces[strtolower(strstr($name, '\\', true))]); }

// ---------------------------------------------------------------------------------------------------------------------
// Flatten: own members, then each used trait in order, adaptations applied. `into` is the class the traits land in, which
// is what `self` and `static` mean inside them.

$flat_memo = [];
$fatals = []; $checks = []; $floor = []; $externals = []; $shadows = []; $matrix_rows = []; $transitive = 0;

function flatten (array $decl, string $into): array {
    global $flat_memo, $fatals, $externals, $shadows;
    $memo = strtolower($decl['name']) . '|' . strtolower($into) . '|' . $decl['file'];
    if (isset($flat_memo[$memo])) return $flat_memo[$memo];
    if (count($flat_memo) > 100000) return ['methods' => [], 'properties' => []];
    $flat_memo[$memo] = ['methods' => [], 'properties' => []];                  // cycle guard
    $methods = []; $props = [];
    foreach ($decl['methods'] as $k => $m)    $methods[$k] = ['m' => $m, 'by' => $decl['name'], 'self' => $into, 'decl' => $decl];
    foreach ($decl['properties'] as $k => $p) $props[$k]   = ['p' => $p, 'by' => $decl['name'], 'decl' => $decl];
    foreach ($decl['traits'] as $trait_name) {
        $trait = lookup($trait_name);
        if (!$trait) {
            if (is_namespaced($trait_name)) $fatals[] = fatal_line($decl, null, "uses unresolved trait $trait_name (missing consumer root, or it does not exist)");
            else $externals[strtolower($trait_name)] = $trait_name;
            continue;
        }
        $sub = flatten($trait, $into);
        $exclusions = $decl['exclusions'][strtolower($trait['name'])] ?? [];
        foreach ($sub['methods'] as $k => $entry) {
            if (isset($exclusions[$k])) continue;
            foreach ($decl['aliases'] as $alias) {
                if ($alias['method'] !== $k || ($alias['trait'] !== null && $alias['trait'] !== strtolower($trait['name']))) continue;
                $copy = $entry;
                if ($alias['vis']) $copy['m']['vis'] = $alias['vis'];
                if ($alias['alias']) { $copy['m']['name'] = $alias['alias']; $methods[strtolower($alias['alias'])] ??= $copy; }   // an own method wins over an alias
                else $entry = $copy;
            }
            if (isset($methods[$k])) {
                if ($methods[$k]['by'] === $decl['name']) {
                    $shadows[] = ['own' => $methods[$k], 'trait' => $entry, 'into' => $decl];
                    if ($entry['m']['abstract']) compare_methods($methods[$k], $entry, $decl, false, true);
                }
                continue;                                                        // two traits colliding is PHP's own fatal
            }
            $methods[$k] = $entry;
        }
        foreach ($sub['properties'] as $k => $entry) {
            if (isset($props[$k])) {
                if (!is_framework($props[$k]['by']) && is_framework($entry['by'])) {
                    $a = $props[$k]['p']; $b = $entry['p'];
                    if ($a['vis'] !== $b['vis'] || $a['static'] !== $b['static'] || $a['readonly'] !== $b['readonly'] || $a['type'] !== $b['type'] || default_value($a['default']) !== default_value($b['default']))
                        $fatals[] = fatal_line($decl, "\${$a['name']}", "defines \${$a['name']} differently from {$entry['by']} composed into it (composition needs an identical definition, default included)", $a['line'], "{$entry['by']}::\${$a['name']}", $props[$k]['decl']);
                }
                continue;
            }
            $props[$k] = $entry;
        }
    }
    return $flat_memo[$memo] = ['methods' => $methods, 'properties' => $props];
}

// ---------------------------------------------------------------------------------------------------------------------
// Comparison. Rules verified against PHP 8.2 (see ~/lattice-v1-tests/plans/variance-tool.md).

function type_str (?array $set): string { return $set === null ? '' : implode('|', $set); }

function sig_str (array $m): string {
    $parts = [];
    foreach ($m['params'] as $p) $parts[] = ($p['raw'] !== '' ? $p['raw'] . ' ' : '') . ($p['byref'] ? '&' : '') . ($p['variadic'] ? '...' : '') . '$' . $p['name'] . ($p['optional'] && !$p['variadic'] ? ' = …' : '');
    return ($m['static'] ? 'static ' : '') . ($m['byref'] ? '&' : '') . '(' . implode(', ', $parts) . ')' . ($m['return_raw'] !== '' ? ': ' . $m['return_raw'] : '');
}

function shape_str (array $m): string { return preg_replace('/\$\w+/', '$x', sig_str($m)); }

function fatal_line (array $child, ?string $member, string $reason, ?int $line = null, ?string $parent = null, ?array $declaring = null): string {
    $d = $declaring ?? $child;
    $where = ($d['rel'] ?? $d['file']) . ':' . ($line ?? $child['line']);
    return sprintf("%-10s %s%s  %s%s  %s", $child['root'] ?? 'framework', $child['name'], $member ? "::$member" : '', $where, $parent ? "  <- $parent" : '', $reason);
}

// Is `sub` a subtype of `super`? Both are resolved lowercased names that differ. 'yes', 'no', 'check', or 'missing:<name>'.
function relation (string $sub, string $super): string {
    $super  = strtolower(alias_digitalis($super));
    $native = fn (string $n) => !is_namespaced($n) && (class_exists($n) || interface_exists($n));
    $decl = lookup($sub);
    if ($decl) {
        $seen = []; $queue = [$decl['name']]; $external = false;
        while ($queue) {
            $name = array_shift($queue);
            if (isset($seen[strtolower($name)])) continue;
            $seen[strtolower($name)] = true;
            if (strtolower(alias_digitalis($name)) === $super || strtolower($name) === $super) return 'yes';
            $d = lookup($name);
            if (!$d) { if ($native($name) && $native($super)) { if (is_a($name, $super, true)) return 'yes'; } else $external = true; continue; }
            if ($d['parent']) $queue[] = $d['parent'];
            foreach ($d['interfaces'] as $i) $queue[] = $i;
        }
        if (!lookup($super) && !$native($super)) return in_scanned_namespace($super) ? "missing:$super" : 'check';
        return $external ? 'check' : 'no';
    }
    if (in_scanned_namespace($sub)) return "missing:$sub";
    if (is_namespaced($sub)) return 'check';
    if ($native($sub) && $native($super)) return is_a($sub, $super, true) ? 'yes' : 'no';
    if (!lookup($super) && in_scanned_namespace($super)) return "missing:$super";
    return 'check';
}

function resolve_keyword (string $name, string $self): string {
    if ($name === 'self') return strtolower($self);
    if ($name === 'parent') { $d = lookup($self); return strtolower($d && $d['parent'] ? alias_digitalis($d['parent']) : $name); }
    return $name;
}

// relation() over intersection members: `A&B` is a subtype of A and of B; a name is a subtype of `A&B` when it is one of both.
function class_relation (string $sub, string $super): string {
    if (str_contains($sub, '&')) {
        $best = 'no';
        foreach (explode('&', $sub) as $part) { $r = class_relation($part, $super); if ($r === 'yes') return 'yes'; if ($r !== 'no') $best = $r; }
        return $best;
    }
    if (str_contains($super, '&')) {
        foreach (explode('&', $super) as $part) { $r = class_relation($sub, $part); if ($r !== 'yes') return $r; }
        return 'yes';
    }
    return $sub === $super ? 'yes' : relation($sub, $super);
}

// Every name in `narrow` must be accepted by `wide`. Returns 'ok', 'check', or a reason string.
function accepts (array $wide, array $narrow, string $wide_self, string $narrow_self, bool $return_side): string {
    $wide   = array_map(fn ($n) => resolve_keyword($n, $wide_self), $wide);
    $narrow = array_map(fn ($n) => resolve_keyword($n, $narrow_self), $narrow);
    $result = 'ok';
    foreach ($narrow as $name) {
        if (in_array($name, $wide, true)) continue;
        if ($return_side && $name === 'void') return 'void is compatible with void only';
        if (in_array('mixed', $wide, true)) continue;
        if ($return_side && $name === 'never') continue;
        if ($return_side && $name === 'static' && (in_array('static', $wide, true) || in_array(strtolower($wide_self), $wide, true))) continue;
        if (in_array('bool', $wide, true) && ($name === 'false' || $name === 'true')) continue;
        if (in_array('iterable', $wide, true) && ($name === 'array' || $name === 'traversable' || (!in_array($name, BUILTIN_TYPES, true) && class_relation($name, 'traversable') === 'yes'))) continue;
        if (in_array($name, BUILTIN_TYPES, true) && $name !== 'static') return "$name is not accepted by " . implode('|', $wide);
        if (in_array('object', $wide, true)) continue;
        $best = 'no';
        foreach ($wide as $w) {
            if (in_array($w, BUILTIN_TYPES, true)) continue;
            $r = class_relation($name, $w);
            if ($r === 'yes') { $best = 'yes'; break; }
            if ($r === 'check' || str_starts_with($r, 'missing:')) $best = $r;
        }
        if ($best === 'yes') continue;
        if (str_starts_with($best, 'missing:')) return 'class ' . substr($best, 8) . ' does not exist (a missing `use`?)';
        if ($best === 'check') { $result = 'check'; continue; }
        return "$name is not a subtype of " . implode('|', $wide);
    }
    return $result;
}

function compare_methods (array $c, array $p, array $child_decl, bool $ctor_checked, bool $is_shadow = false, ?string $label = null): void {
    global $fatals, $checks, $floor;
    $cm = $c['m']; $pm = $p['m'];
    $name = $cm['name'];
    $parent_label = $label ?? $p['by'] . '::' . $pm['name'];
    $declaring = $c['decl'] ?? $child_decl;
    $where = fn (string $reason) => fatal_line($child_decl, $name, $reason, $cm['line'], $parent_label, $declaring);

    if ($pm['vis'] === 'private' && !$pm['abstract']) return;
    if ($pm['final'])                     { $fatals[] = $where('overrides a final method'); return; }
    if ($cm['abstract'] && !$pm['abstract'] && !$is_shadow) { $fatals[] = $where('abstract over a concrete method'); return; }
    if (strtolower($name) === '__construct') {
        if ($cm['static']) { $fatals[] = $where('a static constructor'); return; }
        if (!$ctor_checked) return;                                              // constructors are exempt unless an abstract or interface one is in the chain
    }
    if ($cm['static'] !== $pm['static'])  { $fatals[] = $where($cm['static'] ? 'static where the parent is an instance method' : 'instance method where the parent is static'); return; }
    $rank = ['public' => 0, 'protected' => 1, 'private' => 2];
    if ($rank[$cm['vis']] > $rank[$pm['vis']]) { $fatals[] = $where("visibility {$cm['vis']} narrower than {$pm['vis']}"); return; }
    if ($pm['byref'] && !$cm['byref'])    { $fatals[] = $where('drops the return by reference'); return; }

    $cp = $cm['params']; $pp = $pm['params'];
    $c_variadic = null;
    foreach ($cp as $i => $param) if ($param['variadic']) { $c_variadic = $i; break; }
    if ($c_variadic === null && count($cp) < count($pp)) { $fatals[] = $where(sprintf('fewer parameters (%d, parent has %d)', count($cp), count($pp))); return; }
    foreach ($cp as $i => $param) {
        if ($i >= count($pp) && !$param['optional']) { $fatals[] = $where("adds a required parameter \${$param['name']}"); return; }
        if ($i < count($pp) && $pp[$i]['optional'] && !$param['optional'] && !$param['variadic']) { $fatals[] = $where("makes \${$param['name']} required"); return; }
    }
    $check = null;
    foreach ($pp as $i => $pparam) {
        $cparam = $c_variadic !== null && $i >= $c_variadic ? $cp[$c_variadic] : ($cp[$i] ?? null);
        if ($cparam === null) break;
        if ($cparam['byref'] !== $pparam['byref']) { $fatals[] = $where(($pparam['byref'] ? 'drops' : 'adds') . " the reference on \${$cparam['name']}"); return; }
        if ($pparam['variadic'] && !$cparam['variadic']) { $fatals[] = $where("\${$cparam['name']} is not variadic where the parent's is"); return; }
        $pt = $pparam['type']; $ct = $cparam['type'];
        if ($pt === null || $pt === ['mixed']) {
            if ($ct !== null && $ct !== ['mixed']) { $fatals[] = $where("types \${$cparam['name']} ({$cparam['raw']}) where the parent leaves it untyped"); return; }
            continue;
        }
        if ($ct === null || $ct === ['mixed']) continue;
        $r = accepts($ct, $pt, $c['self'], $p['self'], false);
        if ($r === 'check') { $check ??= "\${$cparam['name']}: {$pparam['raw']} -> {$cparam['raw']}"; continue; }
        if ($r !== 'ok') { $fatals[] = $where("narrows \${$cparam['name']}: {$pparam['raw']} -> {$cparam['raw']} ($r)"); return; }
    }
    $pr = $pm['return']; $cr = $cm['return'];
    if ($pr !== null && $cr === null) { $fatals[] = $where("drops the return type {$pm['return_raw']}"); return; }
    if ($pr !== null && $cr !== null) {
        $r = accepts($pr, $cr, $p['self'], $c['self'], true);
        if ($r === 'check') $check ??= "return: {$pm['return_raw']} -> {$cm['return_raw']}";
        elseif ($r !== 'ok') { $fatals[] = $where("widens the return: {$pm['return_raw']} -> {$cm['return_raw']} ($r)"); return; }
    }
    if ($pr === null && $cr !== null && !$is_shadow) $floor[] = ['parent' => $parent_label, 'line' => sprintf("%-10s %s::%s(): %s  %s:%d  <- %s", $child_decl['root'], $child_decl['name'], $name, $cm['return_raw'], $declaring['rel'], $cm['line'], $parent_label)];
    if ($check) $checks[] = fatal_line($child_decl, $name, "unverified relation, $check", $cm['line'], $parent_label, $declaring);
}

function compare_properties (array $c, array $p, array $child_decl): void {
    global $fatals;
    $cp = $c['p']; $pp = $p['p'];
    if ($pp['vis'] === 'private') return;
    $name = '$' . $cp['name'];
    $where = fn (string $reason) => fatal_line($child_decl, $name, $reason, $cp['line'], $p['by'] . '::$' . $pp['name'], $c['decl'] ?? $child_decl);
    if ($cp['static'] !== $pp['static'])       { $fatals[] = $where($cp['static'] ? 'static where the parent property is not' : 'non-static where the parent property is static'); return; }
    $rank = ['public' => 0, 'protected' => 1, 'private' => 2];
    if ($rank[$cp['vis']] > $rank[$pp['vis']]) { $fatals[] = $where("visibility {$cp['vis']} narrower than {$pp['vis']}"); return; }
    if ($cp['readonly'] !== $pp['readonly'])   { $fatals[] = $where($cp['readonly'] ? 'readonly where the parent property is not' : 'not readonly where the parent property is'); return; }
    if ($pp['type'] !== null && $cp['type'] !== $pp['type']) { $fatals[] = $where('type must be ' . type_str($pp['type']) . ($cp['type'] === null ? ' (declared untyped)' : ', not ' . type_str($cp['type']))); return; }
    if ($pp['type'] === null && $cp['type'] !== null)        { $fatals[] = $where('declares a type (' . type_str($cp['type']) . ') where the parent property has none'); }
}

// ---------------------------------------------------------------------------------------------------------------------
// Match every consumer class against its chain.

$on_chain = 0; $overrides = 0; $prop_overrides = 0;

foreach ($consumers as $decls) foreach ($decls as $decl) {
    if ($decl['kind'] === 'trait' || $decl['kind'] === 'interface') continue;
    $flat  = flatten($decl, $decl['name']);
    $chain = [];                                                                 // [decl, flat] from the direct parent upwards
    $ctor_checked = false;
    $seen  = [strtolower($decl['name']) => true];
    $interfaces = $decl['interfaces'];
    $parent_name = $decl['parent'];
    $touches_fw = false;
    while ($parent_name !== null) {
        $parent = lookup($parent_name);
        if (!$parent) {
            if (is_namespaced($parent_name)) $fatals[] = fatal_line($decl, null, "unresolved parent $parent_name (missing consumer root, or the class does not exist)");
            else $externals[strtolower($parent_name)] = $parent_name;
            break;
        }
        if (isset($seen[strtolower($parent['name'])])) break;
        $seen[strtolower($parent['name'])] = true;
        if ($parent['final']) $fatals[] = fatal_line($decl, null, "extends the final class {$parent['name']}");
        $pflat = flatten($parent, $parent['name']);
        $chain[] = [$parent, $pflat];
        if (isset($pflat['methods']['__construct']) && $pflat['methods']['__construct']['m']['abstract']) $ctor_checked = true;
        if (is_framework($parent['name'])) $touches_fw = true;
        foreach ($parent['interfaces'] as $i) $interfaces[] = $i;
        $parent_name = $parent['parent'];
    }
    // Framework interfaces anywhere on the chain contribute their methods as abstract parents.
    $iface_methods = [];
    $iq = $interfaces; $iseen = [];
    while ($iq) {
        $iname = array_shift($iq);
        if (isset($iseen[strtolower($iname)])) continue;
        $iseen[strtolower($iname)] = true;
        $iface = lookup($iname);
        if (!$iface) { if (is_namespaced($iname)) $fatals[] = fatal_line($decl, null, "implements unresolved interface $iname"); else $externals[strtolower($iname)] = $iname; continue; }
        foreach ($iface['interfaces'] as $i) $iq[] = $i;
        if (!is_framework($iface['name'])) continue;
        $touches_fw = true;
        foreach ($iface['methods'] as $k => $m) { $m['abstract'] = true; $iface_methods[$k] ??= ['m' => $m, 'by' => $iface['name'], 'self' => $iface['name']]; if ($k === '__construct') $ctor_checked = true; }
    }
    if ($touches_fw) $on_chain++;

    foreach ($flat['methods'] as $k => $entry) {
        // Every entry here is the class's own or composed into it by a `use`, including a framework trait used directly:
        // PHP checks a composed trait method against the parent's exactly as it checks an own method.
        $direct = null; $nearest_fw = null;
        foreach ($chain as [$anc, $aflat]) {
            if (!isset($aflat['methods'][$k])) continue;
            $direct ??= [$anc, $aflat['methods'][$k]];
            if (is_framework($aflat['methods'][$k]['by'])) { $nearest_fw = [$anc, $aflat['methods'][$k]]; break; }
        }
        if ($direct === null && isset($iface_methods[$k])) $direct = $nearest_fw = [null, $iface_methods[$k]];
        if ($nearest_fw === null) continue;
        $overrides++;
        [$fw_anc, $fw_entry] = $nearest_fw;
        // Keyed on the framework class the method arrives through, or on the trait itself when a consumer class uses it directly.
        $anchor = $fw_anc && is_framework($fw_anc['name']) ? $fw_anc['name'] : $fw_entry['by'];
        $via    = $anchor !== $fw_entry['by'] ? " (via {$fw_entry['by']})" : '';
        $key    = $anchor . '::' . $fw_entry['m']['name'] . $via;
        $tag = '';
        if ($direct[1]['by'] !== $fw_entry['by'] || ($direct[0] && $fw_anc && $direct[0]['name'] !== $fw_anc['name'])) { $transitive++; $tag = ' via ' . $direct[0]['name']; }
        $matrix_rows[$key]['fw'] = $fw_entry['m'];
        $matrix_rows[$key]['overrides'][] = ['decl' => $decl, 'm' => $entry['m'], 'tag' => $tag, 'by' => $entry['by']];
        if ($tag === '') compare_methods($entry, $fw_entry, $decl, $ctor_checked, false, $key);
        elseif ($entry['m']['return'] !== null && $fw_entry['m']['return'] === null) $floor[] = ['parent' => $key, 'line' => sprintf("%-10s %s::%s(): %s  %s:%d  <- %s%s", $decl['root'], $decl['name'], $entry['m']['name'], $entry['m']['return_raw'], $entry['decl']['rel'], $entry['m']['line'], $key, $tag)];
    }
    foreach ($flat['properties'] as $k => $entry) {
        if (is_framework($entry['by'])) continue;
        foreach ($chain as [$anc, $aflat]) {
            if (!isset($aflat['properties'][$k])) continue;
            if (is_framework($aflat['properties'][$k]['by'])) {
                $prop_overrides++;
                $anchor = is_framework($anc['name']) ? $anc['name'] : $aflat['properties'][$k]['by'];
                $matrix_rows["$anchor::\$$k"]['prop'] = true;
                $matrix_rows["$anchor::\$$k"]['overrides'][] = ['decl' => $decl, 'p' => $entry['p'], 'tag' => '', 'by' => $entry['by']];
                compare_properties($entry, $aflat['properties'][$k], $decl);
            }
            break;                                                               // PHP checks against the nearest declaration only
        }
    }
}

// ---------------------------------------------------------------------------------------------------------------------
// Report

$consumer_total = array_sum($per_root);
printf("framework: %d declarations under %s/include\n", count($fw_decls), $framework);
foreach ($per_root as $label => $count) printf("consumer:  %-12s %d declarations\n", $label, $count);
printf("%d consumer classes on a framework chain (of %d)\n\n", $on_chain, $consumer_total);

foreach ($warnings as $w) fwrite(STDERR, "warning: $w\n");

$fatals = array_values(array_unique($fatals));
$checks = array_values(array_unique($checks));
foreach ($fatals as $f) echo "FATAL  $f\n";
foreach ($checks as $c) echo "CHECK  $c\n";
if ($fatals || $checks) echo "\n";

$externals = array_filter($externals, fn ($n) => !class_exists($n) && !interface_exists($n));
if ($externals) { echo "external parents, not judged: " . implode(', ', $externals) . "\n\n"; }

if ($matrix) {
    uasort($matrix_rows, fn ($a, $b) => count($b['overrides']) <=> count($a['overrides']));
    foreach ($matrix_rows as $key => $row) {
        if (!empty($row['prop'])) { printf("%-64s property%40s overrides: %3d\n", $key, '', count($row['overrides'])); continue; }
        printf("%-64s fw: %-44s overrides: %3d\n", $key, sig_str($row['fw']), count($row['overrides']));
        $shapes = [];
        foreach ($row['overrides'] as $o) $shapes[shape_str($o['m']) . $o['tag']][] = $o;
        foreach ($shapes as $shape => $list) printf("    %3d  %s\n", count($list), $shape);
    }
    if ($shadows) {
        echo "\nshadows (a class method over a used framework trait's method; PHP does not check these):\n";
        $seen = [];
        foreach ($shadows as $s) {
            if (!is_framework($s['trait']['by']) || !isset($s['into']['root'])) continue;
            $line = "    {$s['own']['by']}::{$s['own']['m']['name']} shadows {$s['trait']['by']}::{$s['trait']['m']['name']}" . ($s['trait']['m']['abstract'] ? ' (abstract, checked)' : ' (concrete)');
            if (isset($seen[$line])) continue;
            $seen[$line] = true;
            echo "$line\n";
        }
    }
    echo "\n";
}

if ($floor) {
    echo "FLOOR: child return types the parent lacks (the parent may declare only what these allow; the migration list for a\n       method gaining a return type is its full override list in --matrix, transitive overriders included):\n";
    usort($floor, fn ($a, $b) => strcmp($a['parent'], $b['parent']) ?: strcmp($a['line'], $b['line']));
    foreach ($floor as $f) echo "  {$f['line']}\n";
    echo "\n";
}

$prop_rows = count(array_filter($matrix_rows, fn ($r) => !empty($r['prop'])));
printf("%d framework methods overridden (%d overrides, %d transitive), %d properties redeclared (%d times), %d on the floor, %d fatal, %d to check\n",
    count($matrix_rows) - $prop_rows, $overrides, $transitive, $prop_rows, $prop_overrides, count($floor), count($fatals), count($checks));
printf("RESULT: %s\n", $fatals ? count($fatals) . ' incompatibilit' . (count($fatals) === 1 ? 'y' : 'ies') : 'clean');
exit($fatals ? 1 : 0);
