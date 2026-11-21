<?php
// The 1.0 script: rewrites a consumer tree from the 0.x forms to 1.0's and lists what it cannot rewrite.
// `php bin/lattice-1.0.php CONSUMER_ROOT [--write]`. A dry run prints every rewrite, then the hand edits; --write applies the
// rewrites to a clean git checkout and prints the same. Exit 0 when nothing is left by hand, 1 when hand edits remain, 2 on usage.
// The walk stops at nested repositories, .gitmodules paths, vendor/ and node_modules/. Names resolve against the framework
// tree beside this script; the shim's order, the constants and the legacy hooks are embedded, since compat/ leaves in 1.0.
PHP_SAPI === 'cli' || exit;
if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP 8.2+ required\n"); exit(2); }

// compat/digitalis-namespace.php's order: a flat name lands where the shim resolved it (`Table` is Component's, not DB's).
const SUB_NAMESPACES = ['Component', 'Field', 'DB', 'WP', 'Woo', 'ACF', 'Oxygen'];

const CONSTANTS = [
    'DIGITALIS_FRAMEWORK_VERSION' => 'LATTICE_VERSION',
    'DIGITALIS_FRAMEWORK_PATH'    => 'LATTICE_PATH',
    'DIGITALIS_FRAMEWORK_URI'     => 'LATTICE_URI',
    'DIGITALIS_LIBRARY_PATH'      => 'LATTICE_LIBRARY_PATH',
];

// Lattice\Deprecated_Hooks, inverted.
const LEGACY_HOOK_EXACT = [
    'Digitalis/Instantiate/'      => 'lattice.instantiate',
    'Digitalis/DB/WPDB'           => 'lattice.db.wpdb',
    'Digitalis/Field_Group/Field' => 'lattice.field_group.field',
    'Digitalis/Form/Params'       => 'lattice.form.params',
];

const LEGACY_HOOK_PREFIXES = [
    'Digitalis/Instantiate/' => 'lattice.instantiate',
    'Digitalis/Class/'       => 'lattice.class',
    'Digitalis/Post_Type/'   => 'lattice.post_type',
    'Digitalis/Taxonomy/'    => 'lattice.taxonomy',
];

const HOOK_FUNCTIONS = ['add_filter', 'add_action', 'remove_filter', 'remove_action', 'has_filter', 'has_action', 'apply_filters', 'apply_filters_ref_array', 'do_action', 'do_action_ref_array', 'did_action'];
const FIRE_FUNCTIONS = ['apply_filters', 'apply_filters_ref_array', 'do_action', 'do_action_ref_array'];

// Classes that leave the framework in 1.0, whether or not the tree beside the script still declares them.
const LEAVING = [
    'Digitalis_Query'         => 'Digitalis_Query leaves in 1.0: build the query with Query_Vars and run it through Query_Manager',
    'View_Route'              => 'View_Route leaves in 1.0: extend Route and declare $view',
    'Deprecated_View'         => 'Deprecated_View leaves in 1.0 with include/deprecated/',
    'Deprecated_Component'    => 'Deprecated_Component leaves in 1.0 with include/deprecated/',
    'Has_Components'          => 'Has_Components leaves in 1.0 with include/deprecated/',
    'Has_Integrations'        => 'Has_Integrations leaves in 1.0 with include/deprecated/',
    'Has_Post_Types'          => 'Has_Post_Types leaves in 1.0 with include/deprecated/',
    'Has_Taxonomies'          => 'Has_Taxonomies leaves in 1.0 with include/deprecated/',
    'Deprecated_Hooks'        => 'Deprecated_Hooks leaves in 1.0: subscribe to the lattice. names',
    'Can_Load'                => 'Can_Load leaves in 1.0: own a copy (UPGRADING.md)',
    'Theme'                   => 'Theme leaves in 1.0: own a copy (UPGRADING.md)',
    'Woocommerce_Theme'       => 'Woocommerce_Theme leaves in 1.0: own a copy (UPGRADING.md)',
    'Woocommerce_Clean_Theme' => 'Woocommerce_Clean_Theme leaves in 1.0: own a copy (UPGRADING.md)',
];

$root  = null;
$write = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--write')              $write = true;
    elseif (str_starts_with($arg, '--')) usage("Unknown option $arg");
    elseif ($root === null)              $root = $arg;
    else                                 usage('One consumer root only');
}

if ($root === null) usage('No consumer root given');
$real = realpath($root);
if (!$real || !is_dir($real)) usage("Not a directory: $root");
$root      = $real;
$framework = dirname(__DIR__);
if (!is_dir("$framework/include")) usage("No include/ beside the script at $framework");

if ($write) {
    exec('git -C ' . escapeshellarg($root) . ' status --porcelain -- . 2>&1', $status, $rc);
    if ($rc !== 0) usage("--write needs a git checkout, so the rewrite can be read and undone: $root is not one");
    if ($status)   usage("--write needs a clean tree; commit or stash first:\n" . implode("\n", array_slice($status, 0, 10)));
}

function usage (string $message): never {
    fwrite(STDERR, "$message\nUsage: php bin/lattice-1.0.php CONSUMER_ROOT [--write]\n");
    exit(2);
}

// ---------------------------------------------------------------------------------------------------------------------
// Walk

function consumer_files (string $root): array {
    $skip = [];
    if (is_file("$root/.gitmodules") && preg_match_all('/^\s*path\s*=\s*(.+?)\s*$/m', file_get_contents("$root/.gitmodules"), $m)) {
        foreach ($m[1] as $path) $skip[rtrim("$root/$path", '/')] = true;
    }
    $files = [];
    $walk  = function (string $dir) use (&$walk, &$files, $skip): void {
        $entries = scandir($dir);
        sort($entries);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git') continue;
            $path = "$dir/$entry";
            if (is_dir($path)) {
                if ($entry === 'vendor' || $entry === 'node_modules' || isset($skip[$path]) || file_exists("$path/.git")) continue;
                $walk($path);
            } elseif (preg_match('/\.(php|md)$/', $entry)) {
                $files[] = $path;
            }
        }
    };
    $walk($root);
    return $files;
}

// ---------------------------------------------------------------------------------------------------------------------
// Tokens

function is_tok (mixed $tok, array $ids): bool { return is_array($tok) && in_array($tok[0], $ids, true); }
function tok_text (mixed $tok): string { return is_array($tok) ? $tok[1] : $tok; }

function sig (array $t, int $i, int $step): int {
    for ($i += $step; $i >= 0 && $i < count($t); $i += $step) {
        if (!is_tok($t[$i], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) return $i;
    }
    return -1;
}

function lines_of (array $t): array {
    $lines = [];
    $line  = 1;
    foreach ($t as $i => $tok) {
        if (is_array($tok)) $line = $tok[2];
        $lines[$i] = $line;
        $line += substr_count(tok_text($tok), "\n");
    }
    return $lines;
}

// ---------------------------------------------------------------------------------------------------------------------
// Names

function framework_names (string $framework): array {
    $classes    = [];
    $namespaces = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$framework/include", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') continue;
        $t  = token_get_all(file_get_contents($file->getPathname()));
        $ns = '';
        foreach ($t as $i => $tok) {
            if (is_tok($tok, [T_NAMESPACE])) {
                $j  = sig($t, $i, 1);
                $ns = is_tok($t[$j] ?? null, [T_STRING, T_NAME_QUALIFIED]) ? $t[$j][1] : '';
            }
            if (!is_tok($tok, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) continue;
            if (is_tok($t[sig($t, $i, -1)] ?? null, [T_DOUBLE_COLON, T_NEW])) continue;
            $j = sig($t, $i, 1);
            if (!is_tok($t[$j] ?? null, [T_STRING])) continue;
            $name = ($ns ? "$ns\\" : '') . $t[$j][1];
            $classes[strtolower($name)] = $name;
            for ($parts = explode('\\', $ns); $parts; array_pop($parts)) $namespaces[strtolower(implode('\\', $parts))] = implode('\\', $parts);
        }
    }
    return [$classes, $namespaces];
}

[$CLASSES, $NAMESPACES] = framework_names($framework);

function resolve_digitalis (string $rest): array {
    global $CLASSES, $NAMESPACES;
    $leaving = array_change_key_case(LEAVING);
    if (isset($leaving[strtolower($rest)])) return [null, $leaving[strtolower($rest)]];
    $candidates = ["Lattice\\$rest"];
    foreach (SUB_NAMESPACES as $sub) $candidates[] = "Lattice\\$sub\\$rest";
    foreach ($candidates as $candidate) if (isset($CLASSES[strtolower($candidate)])) return [$CLASSES[strtolower($candidate)], null];
    if (isset($NAMESPACES[strtolower("Lattice\\$rest")])) return [$NAMESPACES[strtolower("Lattice\\$rest")], null];
    return [null, "Digitalis\\$rest has no 1.0 name: port or delete it"];
}

function resolve_flat_lattice (string $short): ?string {
    global $CLASSES;
    if (str_contains($short, '\\') || isset($CLASSES[strtolower("Lattice\\$short")])) return null;
    foreach (SUB_NAMESPACES as $sub) if (isset($CLASSES[strtolower("Lattice\\$sub\\$short")])) return $CLASSES[strtolower("Lattice\\$sub\\$short")];
    return null;
}

function rewrite_name (string $written): array {
    $lead = str_starts_with($written, '\\') ? '\\' : '';
    $name = ltrim($written, '\\');
    if (preg_match('/^Digitalis\\\\(.+)$/', $name, $m)) {
        [$target, $reason] = resolve_digitalis($m[1]);
        return $target === null ? [null, $reason] : [$lead . $target, null];
    }
    if (preg_match('/^Lattice\\\\([^\\\\]+)$/', $name, $m) && ($target = resolve_flat_lattice($m[1]))) return [$lead . $target, null];
    return [$written, null];
}

const NAME_IN_TEXT = '/(?<![\w\\\\])(\\\\{0,2})(Digitalis|Lattice)(\\\\{1,2})([A-Za-z_]\w*(?:\\\\{1,2}[A-Za-z_]\w*)*)/';

function rewrite_names_in_text (string $text, array &$unresolved): string {
    return preg_replace_callback(NAME_IN_TEXT, function ($m) use (&$unresolved) {
        $sep   = $m[3];
        $parts = preg_split('/\\\\{1,2}/', $m[4]);
        if ($m[2] === 'Lattice') {
            $target = count($parts) === 1 ? resolve_flat_lattice($parts[0]) : null;
            return $target === null ? $m[0] : $m[1] . str_replace('\\', $sep, $target);
        }
        [$target, $reason] = resolve_digitalis(implode('\\', $parts));
        if ($target === null) { $unresolved[] = $reason; return $m[0]; }
        return $m[1] . str_replace('\\', $sep, $target);
    }, $text);
}

// ---------------------------------------------------------------------------------------------------------------------
// Hooks

function sanitize_hook (string $name): string {
    $name = str_replace('\\', '.', $name);
    $name = preg_replace('/[^\w.]+/', '.', $name);
    $name = strtolower(trim($name, '.'));
    return preg_replace('/\.{2,}/', '.', $name);
}

function legacy_hook (string $name): ?string {
    if (isset(LEGACY_HOOK_EXACT[$name])) return LEGACY_HOOK_EXACT[$name];
    foreach (LEGACY_HOOK_PREFIXES as $old => $new) {
        if (!str_starts_with($name, $old) || $name === $old) continue;
        $tail = substr($name, strlen($old));
        if (preg_match('#^Digitalis[/\\\\]#', $tail)) $tail = 'Lattice' . substr($tail, 9);
        return "$new." . sanitize_hook($tail);
    }
    return null;
}

function is_legacy_prefix (string $literal): bool {
    foreach (array_merge(array_keys(LEGACY_HOOK_EXACT), array_keys(LEGACY_HOOK_PREFIXES)) as $old) {
        if (str_starts_with($literal, $old) || str_starts_with($old, $literal)) return true;
    }
    return false;
}

function hook_family (string $name): string { return implode('/', array_slice(explode('/', $name), 0, 2)); }

function hook_sites (array $t): array {
    $sites = [];
    $in_get_hooks = null;
    $depth = 0;
    foreach ($t as $i => $tok) {
        $text = tok_text($tok);
        if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
        if ($text === '}') { $depth--; if ($in_get_hooks !== null && $depth < $in_get_hooks) $in_get_hooks = null; }
        if (is_tok($tok, [T_FUNCTION]) && tok_text($t[sig($t, $i, 1)] ?? '') === 'get_hooks') $in_get_hooks = $depth + 1;
        if ($in_get_hooks !== null && is_tok($tok, [T_CONSTANT_ENCAPSED_STRING]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_DOUBLE_ARROW])) {
            $sites[] = [$i, 'get_hooks', false];
            continue;
        }
        if (!is_tok($tok, [T_STRING]) || !in_array(strtolower($text), HOOK_FUNCTIONS, true)) continue;
        $prev = $t[sig($t, $i, -1)] ?? null;
        if (is_tok($prev, [T_DOUBLE_COLON, T_FUNCTION, T_NEW])) continue;
        $open = sig($t, $i, 1);
        if (tok_text($t[$open] ?? '') !== '(') continue;
        $arg = sig($t, $open, 1);
        if ($arg < 0) continue;
        $sites[] = [$arg, strtolower($text), tok_text($t[sig($t, $arg, 1)] ?? '') === '.'];
    }
    return $sites;
}

function literal_value (mixed $tok): ?string {
    if (!is_tok($tok, [T_CONSTANT_ENCAPSED_STRING])) return null;
    $body = substr($tok[1], 1, -1);
    return $tok[1][0] === "'" ? strtr($body, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($body);
}

// ---------------------------------------------------------------------------------------------------------------------
// Rules

function rule_names (array $t, array $lines, array &$out, array &$rewrites, array &$hand): void {
    $ns       = '';
    $ns_depth = 0;
    $depth    = 0;
    for ($i = 0; $i < count($t); $i++) {
        $tok  = $t[$i];
        $text = tok_text($tok);
        if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
        if ($text === '}') $depth--;

        if (is_tok($tok, [T_NAMESPACE])) {
            $j = sig($t, $i, 1);
            if (!is_tok($t[$j] ?? null, [T_STRING, T_NAME_QUALIFIED]) && tok_text($t[$j] ?? '') !== '{') continue;
            $ns       = is_array($t[$j]) ? $t[$j][1] : '';
            $braced   = tok_text($t[is_array($t[$j]) ? sig($t, $j, 1) : $j] ?? '') === '{';
            $ns_depth = $braced ? $depth + 1 : 0;
            continue;
        }

        if (is_tok($tok, [T_USE]) && $depth === $ns_depth && tok_text($t[sig($t, $i, 1)] ?? '') !== '(') {
            $i = rule_names_import($t, $i, $lines, $out, $rewrites, $hand);
            continue;
        }

        if (is_tok($tok, [T_NAME_FULLY_QUALIFIED]) || (is_tok($tok, [T_NAME_QUALIFIED]) && $ns === '')) {
            $next = $t[sig($t, $i, 1)] ?? null;
            if (tok_text($next) === '(' && !is_tok($t[sig($t, $i, -1)] ?? null, [T_NEW])) continue;
            [$new, $reason] = rewrite_name($text);
            if ($reason !== null) $hand[] = [$lines[$i], $reason];
            elseif ($new !== $text) { $out[$i] = $new; $rewrites[] = [$lines[$i], 'names', "$text -> $new"]; }
            continue;
        }

        if (is_tok($tok, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
            $unresolved = [];
            $new = rewrite_names_in_text($text, $unresolved);
            foreach ($unresolved as $reason) $hand[] = [$lines[$i], $reason];
            if ($new !== $text) { $out[$i] = $new; $rewrites[] = [$lines[$i], 'names', "$text -> $new"]; }
            continue;
        }

        if (is_tok($tok, [T_COMMENT, T_DOC_COMMENT])) {
            $unresolved = [];
            $new = rewrite_names_in_text($text, $unresolved);
            if ($new !== $text) { $out[$i] = $new; $rewrites[] = [$lines[$i], 'names', 'comment']; }
        }
    }
}

function rule_names_import (array $t, int $i, array $lines, array &$out, array &$rewrites, array &$hand): int {
    $end = $i;
    while ($end < count($t) && tok_text($t[$end]) !== ';') $end++;
    $first = sig($t, $i, 1);
    if (is_tok($t[$first] ?? null, [T_FUNCTION, T_CONST])) return $end;

    $brace = null;
    for ($j = $first; $j < $end; $j++) if (tok_text($t[$j]) === '{') { $brace = $j; break; }

    if ($brace === null) {
        for ($j = $first; $j < $end; $j++) {
            if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING])) continue;
            if (tok_text($t[sig($t, $j, -1)]) === 'as' || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
            [$new, $reason] = rewrite_name('\\' . ltrim($t[$j][1], '\\'));
            if ($reason !== null) { $hand[] = [$lines[$j], $reason]; continue; }
            $new = str_starts_with($t[$j][1], '\\') ? $new : ltrim($new, '\\');
            if ($new !== $t[$j][1]) { $out[$j] = $new; $rewrites[] = [$lines[$j], 'names', "use {$t[$j][1]} -> $new"]; }
        }
        return $end;
    }

    // Group: `use Digitalis\{A, Field\B}` becomes `use Lattice\{A, Component\B}` with each member placed where it resolves.
    $prefix = '';
    $prefix_token = null;
    for ($j = $first; $j < $brace; $j++) {
        $prefix .= tok_text($t[$j]);
        if ($prefix_token === null && is_tok($t[$j], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) $prefix_token = $j;
    }
    $prefix = trim(ltrim(trim($prefix), '\\'), '\\');
    if (!preg_match('/^(Digitalis|Lattice)(\\\\|$)/', $prefix)) return $end;

    if ($prefix === 'Digitalis') $new_prefix = 'Lattice';
    else {
        [$new_prefix, $reason] = rewrite_name("\\$prefix");
        if ($reason !== null) { $hand[] = [$lines[$i], $reason]; return $end; }
        $new_prefix = ltrim($new_prefix, '\\');
    }

    $members = [];
    for ($j = $brace + 1; $j < $end; $j++) {
        if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_STRING])) continue;
        if (is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
        [$new, $reason] = rewrite_name("\\$prefix\\" . $t[$j][1]);
        if ($reason !== null) { $hand[] = [$lines[$j], $reason]; return $end; }
        $new = ltrim($new, '\\');
        if (!str_starts_with($new, "$new_prefix\\")) { $hand[] = [$lines[$j], "use $prefix\\{...}: {$t[$j][1]} moves to $new, outside the group: split the use"]; return $end; }
        $members[$j] = substr($new, strlen($new_prefix) + 1);
    }

    $changed = array_filter($members, fn ($m, $j) => $m !== $t[$j][1], ARRAY_FILTER_USE_BOTH);
    if ($new_prefix === $prefix && !$changed) return $end;
    $out[$prefix_token] = (str_starts_with(tok_text($t[$prefix_token]), '\\') ? '\\' : '') . $new_prefix;
    foreach ($changed as $j => $member) $out[$j] = $member;
    $rewrites[] = [$lines[$i], 'names', "use $prefix\\{" . implode(', ', array_map(fn ($j) => $t[$j][1], array_keys($members))) . "} -> $new_prefix\\{" . implode(', ', $members) . '}'];
    return $end;
}

function rule_constants (array $t, array $lines, array &$out, array &$rewrites, array &$hand): void {
    foreach ($t as $i => $tok) {
        if (is_tok($tok, [T_STRING, T_NAME_FULLY_QUALIFIED]) && isset(CONSTANTS[ltrim($tok[1], '\\')])) {
            if (is_tok($t[sig($t, $i, -1)] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_CONST, T_FUNCTION])) continue;
            $new = (str_starts_with($tok[1], '\\') ? '\\' : '') . CONSTANTS[ltrim($tok[1], '\\')];
            $out[$i] = $new;
            $rewrites[] = [$lines[$i], 'constants', "{$tok[1]} -> $new"];
        }
        if (is_tok($tok, [T_CONSTANT_ENCAPSED_STRING]) && isset(CONSTANTS[literal_value($tok)])) {
            $new = $tok[1][0] . CONSTANTS[literal_value($tok)] . $tok[1][0];
            $out[$i] = $new;
            $rewrites[] = [$lines[$i], 'constants', "{$tok[1]} -> $new"];
        }
    }
}

function rule_hooks (array $t, array $lines, array &$out, array &$rewrites, array &$hand, array $fired): void {
    foreach (hook_sites($t) as [$i, $function, $concatenated]) {
        $tok = $t[$i];
        if (tok_text($tok) === '"') {
            $text = '';
            for ($j = $i + 1; $j < count($t) && tok_text($t[$j]) !== '"'; $j++) $text .= tok_text($t[$j]);
            if (str_contains($text, 'Digitalis/') && is_legacy_prefix(substr($text, strpos($text, 'Digitalis/')))) $hand[] = [$lines[$i], "a legacy hook name built at run time ($function): move it to its lattice. name"];
            continue;
        }
        $name = literal_value($tok);
        if ($name === null || !str_starts_with($name, 'Digitalis/')) continue;
        if ($concatenated) {
            if (is_legacy_prefix($name)) $hand[] = [$lines[$i], "a legacy hook name built at run time ($function '$name' . ...): move it to its lattice. name"];
            continue;
        }
        $new = legacy_hook($name);
        if ($new !== null) {
            $out[$i] = $tok[1][0] . $new . $tok[1][0];
            $rewrites[] = [$lines[$i], 'hooks', "$name -> $new"];
        } elseif (!in_array($function, FIRE_FUNCTIONS, true) && !isset($fired[hook_family($name)])) {
            $hand[] = [$lines[$i], "'$name' is not a framework hook and nothing in this tree fires it: delete the subscription, or ask whether the runtime names row renames it"];
        }
    }
}

// Families of `Digitalis/...` hooks the consumer fires itself (somm's `Digitalis/Banner/...`): its own, left alone.
function fired_families (array $files): array {
    $fired = [];
    foreach ($files as $file) {
        if (!str_ends_with($file, '.php')) continue;
        $t = token_get_all(file_get_contents($file));
        foreach (hook_sites($t) as [$i, $function]) {
            $name = literal_value($t[$i]);
            if ($name !== null && in_array($function, FIRE_FUNCTIONS, true) && str_starts_with($name, 'Digitalis/')) $fired[hook_family($name)] = true;
        }
    }
    return $fired;
}

function markdown_lines (string $source): array {
    $hits = [];
    $patterns = ['/(?<![\w\\\\])\\\\?Digitalis\\\\{1,2}[A-Za-z_]/', '/\bDIGITALIS_(FRAMEWORK_[A-Z]+|LIBRARY_PATH)\b/', '#Digitalis/(Instantiate|Class|Post_Type|Taxonomy|DB/WPDB|Field_Group/Field|Form/Params)\b#'];
    foreach (preg_split('/\R/', $source) as $n => $line) {
        foreach ($patterns as $pattern) if (preg_match($pattern, $line)) { $hits[] = $n + 1; break; }
    }
    return $hits;
}

// ---------------------------------------------------------------------------------------------------------------------
// Run

$files = consumer_files($root);
$fired = fired_families($files);
$total_rewrites = 0;
$changed_files  = 0;
$hand_edits     = [];

foreach ($files as $file) {
    $relative = substr($file, strlen($root) + 1);
    $source   = file_get_contents($file);

    if (str_ends_with($file, '.md')) {
        if ($hits = markdown_lines($source)) $hand_edits[] = [$relative, $hits[0], count($hits) . ' doc line(s) name forms the script rewrites in code (lines ' . implode(', ', $hits) . '): move them by hand'];
        continue;
    }

    $t        = token_get_all($source);
    $lines    = lines_of($t);
    $out      = [];
    $rewrites = [];
    $hand     = [];

    $declares_digitalis = false;
    foreach ($t as $i => $tok) {
        if (!is_tok($tok, [T_NAMESPACE])) continue;
        $j = sig($t, $i, 1);
        if (is_tok($t[$j] ?? null, [T_STRING, T_NAME_QUALIFIED]) && preg_match('/^Digitalis(\\\\|$)/', $t[$j][1])) {
            $declares_digitalis = true;
            $hand[] = [$lines[$i], "declares namespace {$t[$j][1]}: move it into the plugin's own namespace and import what its unqualified names meant"];
        }
    }

    if (!$declares_digitalis) {
        rule_names($t, $lines, $out, $rewrites, $hand);
        rule_constants($t, $lines, $out, $rewrites, $hand);
        rule_hooks($t, $lines, $out, $rewrites, $hand, $fired);
    }

    foreach ($hand as [$line, $reason]) $hand_edits[] = [$relative, $line, $reason];
    if (!$rewrites) continue;

    usort($rewrites, fn ($a, $b) => $a[0] <=> $b[0]);
    echo "$relative\n";
    foreach ($rewrites as [$line, $rule, $what]) printf("  %5d  %-9s %s\n", $line, $rule, preg_replace('/\s+/', ' ', $what));
    $total_rewrites += count($rewrites);
    $changed_files++;

    if ($write) {
        $new = '';
        foreach ($t as $i => $tok) $new .= $out[$i] ?? tok_text($tok);
        file_put_contents($file, $new);
    }
}

if ($hand_edits) {
    echo "\nHand edits\n";
    foreach ($hand_edits as [$relative, $line, $reason]) echo "  $relative:$line  $reason\n";
}

printf("\nRESULT: %d rewrite(s) in %d file(s)%s, %d hand edit(s)\n", $total_rewrites, $changed_files, $write ? ' written' : '', count($hand_edits));
exit($hand_edits ? 1 : 0);
