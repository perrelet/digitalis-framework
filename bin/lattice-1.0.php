<?php
// The 1.0 script: rewrites a consumer tree from the 0.x forms to 1.0's and lists what it cannot rewrite.
// `php bin/lattice-1.0.php CONSUMER_ROOT [--write] [--deps DIR]`. A dry run prints every rewrite, then the hand edits; --write applies the
// rewrites to a clean git checkout and prints the same. Exit 0 when nothing is left by hand, 1 when hand edits remain, 2 on usage.
// The walk stops at nested repositories, .gitmodules paths, vendor/ and node_modules/. Names resolve against the framework
// tree beside this script; the shim's order, the constants and the legacy hooks are embedded, since compat/ leaves in 1.0.
// An empty nested tree (an uninitialised submodule) is read for class ancestry from DIR/<its name>, default the root's siblings.
// What leaves the framework for the consumer to own (the theme chain, Can_Load, scss/) is copied from the framework repository at COPY_SOURCE.
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
$deps  = null;
$args  = array_slice($argv, 1);

while (($arg = array_shift($args)) !== null) {
    if ($arg === '--write')                    $write = true;
    elseif ($arg === '--deps')                 $deps = array_shift($args) ?? usage('--deps needs a directory');
    elseif (str_starts_with($arg, '--deps='))  $deps = substr($arg, 7);
    elseif (str_starts_with($arg, '--'))       usage("Unknown option $arg");
    elseif ($root === null)              $root = $arg;
    else                                 usage('One consumer root only');
}

if ($root === null) usage('No consumer root given');
$real = realpath($root);
if (!$real || !is_dir($real)) usage("Not a directory: $root");
$root      = $real;
$deps      = realpath($deps ?? dirname($root)) ?: usage("Not a directory: $deps");
$framework = dirname(__DIR__);
if (!is_dir("$framework/include")) usage("No include/ beside the script at $framework");

if ($write) {
    exec('git -C ' . escapeshellarg($root) . ' status --porcelain -- . 2>&1', $status, $rc);
    if ($rc !== 0) usage("--write needs a git checkout, so the rewrite can be read and undone: $root is not one");
    if ($status)   usage("--write needs a clean tree; commit or stash first:\n" . implode("\n", array_slice($status, 0, 10)));
}

function usage (string $message): never {
    fwrite(STDERR, "$message\nUsage: php bin/lattice-1.0.php CONSUMER_ROOT [--write] [--deps DIR]\n");
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
            } elseif (preg_match('/\.(php|md|scss)$/', $entry)) {
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
$COPIED = [];

function resolve_digitalis (string $rest): array {
    global $CLASSES, $NAMESPACES, $COPIED;
    if (isset($COPIED[strtolower($rest)])) return [$COPIED[strtolower($rest)], null];
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
    global $COPIED;
    $lead = str_starts_with($written, '\\') ? '\\' : '';
    $name = ltrim($written, '\\');
    if (preg_match('/^Digitalis\\\\(.+)$/', $name, $m)) {
        [$target, $reason] = resolve_digitalis($m[1]);
        return $target === null ? [null, $reason] : [$lead . $target, null];
    }
    if (preg_match('/^Lattice\\\\([^\\\\]+)$/', $name, $m) && ($target = $COPIED[strtolower($m[1])] ?? resolve_flat_lattice($m[1]))) return [$lead . $target, null];
    return [$written, null];
}

const NAME_IN_TEXT = '/(?<![\w\\\\])(\\\\{0,2})(Digitalis|Lattice)(\\\\{1,2})([A-Za-z_]\w*(?:\\\\{1,2}[A-Za-z_]\w*)*)/';

function rewrite_names_in_text (string $text, array &$unresolved): string {
    global $COPIED;
    return preg_replace_callback(NAME_IN_TEXT, function ($m) use (&$unresolved, $COPIED) {
        $sep   = $m[3];
        $parts = preg_split('/\\\\{1,2}/', $m[4]);
        if ($m[2] === 'Lattice') {
            $target = count($parts) === 1 ? ($COPIED[strtolower($parts[0])] ?? resolve_flat_lattice($parts[0])) : null;
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
            if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
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
    $patterns = ['/(?<![\w\\\\])\\\\?Digitalis\\\\{1,2}[A-Za-z_]/', '/\bDIGITALIS_(FRAMEWORK_[A-Z]+|LIBRARY_PATH)\b/', '#Digitalis/(Instantiate|Class|Post_Type|Taxonomy|DB/WPDB|Field_Group/Field|Form/Params)\b#', "/['\"]field['\"]\\s*=>\\s*[\\\\\\w]+::class/", '/::(render|inst)\s*\(/i'];
    foreach (preg_split('/\R/', $source) as $n => $line) {
        foreach ($patterns as $pattern) if (preg_match($pattern, $line)) { $hits[] = $n + 1; break; }
    }
    return $hits;
}

// ---------------------------------------------------------------------------------------------------------------------
// Render

function class_index (string $root, string $deps, string $framework): array {
    $index = [];
    $seen  = [];
    $files = [];
    $walk  = function (string $dir) use (&$walk, &$files, &$seen, $deps): void {
        if (isset($seen[realpath($dir)])) return;
        $seen[realpath($dir)] = true;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git' || $entry === 'vendor' || $entry === 'node_modules') continue;
            $path = "$dir/$entry";
            if (is_dir($path)) {
                $empty = !array_diff(scandir($path), ['.', '..', '.git']);
                if ($empty && is_dir("$deps/$entry")) $walk("$deps/$entry");
                elseif (!$empty) $walk($path);
            } elseif (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }
    };
    $walk($root);
    $walk("$framework/include");
    foreach ($files as $file) {
        $t       = @token_get_all(file_get_contents($file));
        $ns      = '';
        $uses    = [];
        $depth   = 0;
        $stack   = [];
        $pending = null;
        foreach ($t as $i => $tok) {
            $text = tok_text($tok);
            if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { $depth++; if ($pending !== null && $text === '{') { $stack[] = [$pending, $depth]; $pending = null; } }
            if ($text === '}') { if ($stack && end($stack)[1] === $depth) array_pop($stack); $depth--; }
            if (is_tok($tok, [T_NAMESPACE]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING, T_NAME_QUALIFIED])) { $ns = $t[sig($t, $i, 1)][1]; $uses = []; }
            $body = $stack && end($stack)[1] === $depth ? end($stack)[0] : null;
            if ($body !== null && is_tok($tok, [T_FUNCTION]) && strtolower(tok_text($t[sig($t, $i, 1)] ?? '')) === 'inst' && !preg_match('/^(lattice|digitalis)\\\\/', $body)) $index[$body][2] = true;
            if (is_tok($tok, [T_USE]) && $body !== null) {
                for ($j = sig($t, $i, 1); $j > 0 && tok_text($t[$j]) !== ';' && tok_text($t[$j]) !== '{'; $j = sig($t, $j, 1)) {
                    if (is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING])) $index[$body][3][] = canonical_class(qualify($t[$j][1], $ns, $uses));
                }
            } elseif (is_tok($tok, [T_USE]) && !$stack && tok_text($t[sig($t, $i, 1)] ?? '') !== '(') {
                for ($j = sig($t, $i, 1); $j > 0 && tok_text($t[$j]) !== ';' && tok_text($t[$j]) !== '{'; $j = sig($t, $j, 1)) {
                    if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
                    $full  = ltrim($t[$j][1], '\\');
                    $alias = is_tok($t[sig($t, $j, 1)] ?? null, [T_AS]) ? tok_text($t[sig($t, sig($t, $j, 1), 1)]) : substr(strrchr("\\$full", '\\'), 1);
                    $uses[strtolower($alias)] = $full;
                }
            }
            if (!is_tok($tok, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) || is_tok($t[sig($t, $i, -1)] ?? null, [T_DOUBLE_COLON, T_NEW])) continue;
            $j = sig($t, $i, 1);
            if (!is_tok($t[$j] ?? null, [T_STRING])) continue;
            $name   = ($ns ? "$ns\\" : '') . $t[$j][1];
            $parent = null;
            $k = sig($t, $j, 1);
            if (is_tok($t[$k] ?? null, [T_EXTENDS])) $parent = canonical_class(qualify(tok_text($t[sig($t, $k, 1)]), $ns, $uses));
            $index[strtolower($name)] ??= [$name, $parent, false, [], $file];
            $pending = strtolower($name);
        }
    }
    return $index;
}

function qualify (string $name, string $ns, array $uses): string {
    if ($name[0] === '\\') return substr($name, 1);
    $first = strtolower(strstr($name, '\\', true) ?: $name);
    if (in_array(strtolower($name), ['self', 'static', 'parent'], true)) return strtolower($name);
    if (isset($uses[$first])) return $uses[$first] . substr($name, strlen($first));
    return ($ns ? "$ns\\" : '') . $name;
}

function canonical_class (string $name): string {
    [$new] = rewrite_name('\\' . $name);
    return ltrim($new ?? $name, '\\');
}

// true, false, or null when the ancestry leaves every indexed tree before it ends.
function is_a_class (?string $class, string $base, array $index): ?bool {
    for ($hops = 0; $class !== null && $hops < 40; $hops++) {
        if (strtolower($class) === strtolower($base)) return true;
        if (!isset($index[strtolower($class)])) return null;
        $class = $index[strtolower($class)][1];
    }
    return false;
}

// Class names something swaps through lattice.class (or its legacy name), from every tree in DIR and the consumer.
function swapped_classes (array $trees): array {
    $swapped = [];
    foreach ($trees as $tree) {
        $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS), fn ($f) => !in_array($f->getFilename(), ['.git', 'vendor', 'node_modules'], true)));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            if (!str_contains($source, 'Class/') && !str_contains($source, 'lattice.class.')) continue;
            if (!preg_match_all('#[\'"](?:Digitalis/Class/|lattice\.class\.)([A-Za-z0-9_./\\\\]+)[\'"]#', $source, $m)) continue;
            foreach ($m[1] as $tail) {
                $tail = preg_replace('#^(Digitalis|lattice)[/.]#i', 'Lattice/', $tail);
                $swapped[sanitize_hook($tail)] = true;
            }
        }
    }
    return $swapped;
}

function call_args (array $t, int $open): array {
    $args  = [];
    $depth = 0;
    $start = $open + 1;
    for ($i = $open; $i < count($t); $i++) {
        $text = tok_text($t[$i]);
        if (in_array($text, ['(', '[', '{'], true) || is_tok($t[$i], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
        if (in_array($text, [')', ']', '}'], true)) {
            $depth--;
            if ($depth === 0) { if (trim(tokens_text($t, $start, $i - 1, [])) !== '') $args[] = [$start, $i - 1]; return [$args, $i]; }
        }
        if ($text === ',' && $depth === 1) { $args[] = [$start, $i - 1]; $start = $i + 1; }
    }
    return [$args, count($t) - 1];
}

function tokens_text (array $t, int $from, int $to, array $out): string {
    $text = '';
    for ($i = $from; $i <= $to; $i++) $text .= $out[$i] ?? tok_text($t[$i]);
    return $text;
}

// A `:` that closes a ternary (`? a : b`, `?:`), not a `case` label or an alternative-syntax header.
function is_ternary_else (array $t, int $colon): bool {
    for ($depth = 0, $i = $colon - 1; $i >= 0; $i--) {
        $text = tok_text($t[$i]);
        if (in_array($text, [')', ']'], true)) $depth++;
        elseif (in_array($text, ['(', '['], true)) { if ($depth-- === 0) return false; }
        elseif ($depth === 0 && in_array($text, [';', '{', '}'], true) || is_tok($t[$i], [T_OPEN_TAG, T_CASE, T_DEFAULT])) return false;
        elseif ($depth === 0 && $text === '?') return true;
    }
    return false;
}

function rule_render (array $t, array $lines, array &$out, array &$rewrites, array &$hand, array $index, array $swapped): void {
    $ns      = '';
    $uses    = [];
    $depth   = 0;
    $classes = [];
    $pending = null;
    for ($i = 0; $i < count($t); $i++) {
        $tok  = $t[$i];
        $text = tok_text($tok);
        if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { $depth++; if ($pending !== null && $text === '{') { $classes[] = [$pending, $depth]; $pending = null; } }
        if ($text === '}') { if ($classes && end($classes)[1] === $depth) array_pop($classes); $depth--; }
        if (is_tok($tok, [T_NAMESPACE]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING, T_NAME_QUALIFIED])) { $ns = $t[sig($t, $i, 1)][1]; $uses = []; }
        if (is_tok($tok, [T_USE]) && !$classes && tok_text($t[sig($t, $i, 1)] ?? '') !== '(') {
            for ($j = sig($t, $i, 1); $j > 0 && tok_text($t[$j]) !== ';' && tok_text($t[$j]) !== '{'; $j = sig($t, $j, 1)) {
                if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
                $full  = ltrim($t[$j][1], '\\');
                $alias = is_tok($t[sig($t, $j, 1)] ?? null, [T_AS]) ? tok_text($t[sig($t, sig($t, $j, 1), 1)]) : substr(strrchr("\\$full", '\\'), 1);
                $uses[strtolower($alias)] = $full;
            }
        }
        if (is_tok($tok, [T_CLASS, T_TRAIT]) && !is_tok($t[sig($t, $i, -1)] ?? null, [T_DOUBLE_COLON, T_NEW]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING])) {
            $pending = ($ns ? "$ns\\" : '') . $t[sig($t, $i, 1)][1];
        }

        if (is_tok($tok, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE]) && preg_match('/::render\b/', $text)) {
            $hand[] = [$lines[$i], "a string callable to render ($text): build the view with new, resolving the class with Call::get_class_name() if it is swapped"];
            continue;
        }
        if (is_tok($tok, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && preg_match('/(^|\\\\)Call$/', $text) && tok_text($t[sig($t, $i, 1)] ?? '') === '::' && in_array(strtolower(tok_text($t[sig($t, sig($t, $i, 1), 1)] ?? '')), ['static', 'static_array'], true)) {
            [$args] = call_args($t, sig($t, sig($t, sig($t, $i, 1), 1), 1));
            if (isset($args[1]) && literal_value($t[sig($t, $args[1][0] - 1, 1)] ?? null) === 'render') $hand[] = [$lines[$i], "Call::static(..., 'render', ...): build the view with new, resolving the class with Call::get_class_name()"];
            continue;
        }

        if (!is_tok($tok, [T_DOUBLE_COLON]) || tok_text($t[sig($t, $i, 1)] ?? '') !== 'render' || tok_text($t[sig($t, sig($t, $i, 1), 1)] ?? '') !== '(') continue;
        $recv = sig($t, $i, -1);
        $open = sig($t, sig($t, $i, 1), 1);
        $line = $lines[$recv];
        $written = tok_text($t[$recv]);
        $current = $classes ? end($classes)[0] : null;

        if (is_tok($t[$recv], [T_VARIABLE]) || $written === ')' || $written === '}') { $hand[] = [$line, "$written::render(): build the view with new"]; continue; }
        $keyword = strtolower($written);
        if (is_tok($t[$recv], [T_STATIC]) || $keyword === 'self' || $keyword === 'parent') {
            $class = $keyword === 'parent' ? ($current !== null ? ($index[strtolower($current)][1] ?? null) : null) : $current;
            $new_class = $keyword === 'parent' ? null : $keyword;
        } else {
            $class = canonical_class(qualify($written, $ns, $uses));
            $new_class = $out[$recv] ?? $written;
        }
        $view = is_a_class($class, 'Lattice\\View', $index);
        if ($view === false) continue;
        if ($view === null) { $hand[] = [$line, "$written::render(): its ancestry leaves every tree the script can read (pass --deps): rewrite it by hand if it is a View"]; continue; }
        if ($new_class === null) { $hand[] = [$line, "parent::render() on a View: build the parent's view with new"]; continue; }

        [$args, $close] = call_args($t, $open);
        $before = sig($t, $recv, -1);
        $after  = sig($t, $close, 1);
        $second = isset($args[1]) ? strtolower(trim(tokens_text($t, $args[1][0], $args[1][1], $out))) : 'true';
        $params = isset($args[0]) ? trim(tokens_text($t, $args[0][0], $args[0][1], $out)) : '';
        $prev   = tok_text($t[$before] ?? '');
        $controlled = false;
        if ($prev === ')') {
            for ($d = 0, $q = $before; $q >= 0; $q--) { $v = tok_text($t[$q]); if ($v === ')') $d++; if ($v === '(' && --$d === 0) break; }
            $controlled = is_tok($t[sig($t, $q, -1)] ?? null, [T_IF, T_ELSEIF, T_FOREACH, T_FOR, T_WHILE]);
        }
        $label = $prev === ':' && !is_ternary_else($t, $before);
        $statement = ($controlled || $label || in_array($prev, [';', '{', '}'], true) || is_tok($t[$before] ?? null, [T_OPEN_TAG, T_ELSE])) && (tok_text($t[$after] ?? '') === ';' || is_tok($t[$after] ?? null, [T_CLOSE_TAG]));

        if (count($args) > 2 || !in_array($second, ['true', 'false'], true)) { $hand[] = [$line, "$written::render(...) with a print flag known only at run time: echo new $written(...) or (string) new $written(...)"]; continue; }
        if ($second === 'false') $new = "(string) new $new_class($params)";
        elseif ($statement)      $new = "echo new $new_class($params)";
        else                     { $hand[] = [$line, "$written::render() prints and its result is used (" . (is_tok($t[$before] ?? null, [T_RETURN]) ? 'returned' : 'an expression') . "): print it with echo new, then return or use what you meant"]; continue; }

        $out[$recv] = $new;
        for ($k = $recv + 1; $k <= $close; $k++) $out[$k] = '';
        $rewrites[] = [$line, 'render', preg_replace('/\s+/', ' ', "$written::render(" . substr($params, 0, 40) . (strlen($params) > 40 ? '...' : '') . ($second === 'false' ? ', false' : '') . ") -> " . substr($new, 0, strpos($new, '(', strpos($new, 'new ')) ?: strlen($new)) . '(...)')];
        if (isset($swapped[sanitize_hook((string) $class)])) {
            $hand[] = [$line, "$written is swapped through lattice.class: resolve it first, \$class = Call::get_class_name($written::class), then new \$class(...)"];
        }
    }
}

// ---------------------------------------------------------------------------------------------------------------------
// Fields

// Each entry of the array opening at $open: first and last significant token, its literal key (if any) and where its value starts.
function array_entries (array $t, int $open): array {
    [$items] = call_args($t, $open);
    $entries = [];
    foreach ($items as [$from, $to]) {
        $first = sig($t, $from - 1, 1);
        $last  = sig($t, $to + 1, -1);
        if ($first < 0 || $first > $to) continue;
        $arrow = null;
        for ($depth = 0, $k = $first; $k <= $last; $k++) {
            $text = tok_text($t[$k]);
            if (in_array($text, ['(', '[', '{'], true) || is_tok($t[$k], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
            elseif (in_array($text, [')', ']', '}'], true)) $depth--;
            elseif ($depth === 0 && is_tok($t[$k], [T_DOUBLE_ARROW])) { $arrow = $k; break; }
        }
        $keyed = $arrow !== null && sig($t, $first, 1) === $arrow;
        $entries[] = ['from' => $first, 'to' => $last, 'key' => $keyed ? literal_value($t[$first]) : null, 'value' => $arrow !== null ? sig($t, $arrow, 1) : $first];
    }
    return $entries;
}

function entry_named (array $entries, string $key): ?array {
    foreach ($entries as $entry) if ($entry['key'] === $key) return $entry;
    return null;
}

function class_constant (array $t, array $entry): ?int {
    $v = $entry['value'];
    if (!is_tok($t[$v], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) return null;
    $colon = sig($t, $v, 1);
    return tok_text($t[$colon]) === '::' && is_tok($t[sig($t, $colon, 1)], [T_CLASS]) && sig($t, $colon, 1) === $entry['to'] ? $v : null;
}

function drop_entry (array $t, array $entry, array &$out): void {
    $comma = sig($t, $entry['to'], 1);
    $last = in_array(tok_text($t[sig($t, $comma, 1)] ?? ''), [']', ')'], true);
    if (tok_text($t[$comma]) === ',' && $last) {
        $from = is_tok($t[$entry['from'] - 1], [T_WHITESPACE]) ? $entry['from'] - 1 : $entry['from'];
        $to   = $comma;
    } elseif (tok_text($t[$comma]) === ',') {
        $from = $entry['from'];
        $to   = is_tok($t[$comma + 1] ?? null, [T_WHITESPACE]) ? $comma + 1 : $comma;
    } else {
        $prev = sig($t, $entry['from'], -1);
        $from = tok_text($t[$prev]) === ',' ? $prev : $entry['from'];
        $to   = $entry['to'];
    }
    for ($k = $from; $k <= $to; $k++) $out[$k] = '';
}

function rule_fields (array $t, array $lines, array &$out, array &$rewrites, array &$hand, array $index): void {
    $ns      = '';
    $uses    = [];
    $braces  = [];
    $pending = null;
    $init    = null;
    $stack   = [];
    $arrays  = [];
    for ($i = 0; $i < count($t); $i++) {
        $tok  = $t[$i];
        $text = tok_text($tok);
        if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { $braces[] = $text === '{' ? ($pending ?? 'block') : 'block'; $pending = null; continue; }
        if ($text === '}') { array_pop($braces); continue; }
        if (is_tok($tok, [T_NAMESPACE]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING, T_NAME_QUALIFIED])) { $ns = $t[sig($t, $i, 1)][1]; $uses = []; }
        if (is_tok($tok, [T_USE]) && !$braces || is_tok($tok, [T_USE]) && $braces === ['block'] && $ns !== '') {
            for ($j = sig($t, $i, 1); $j > 0 && tok_text($t[$j]) !== ';' && tok_text($t[$j]) !== '{' && tok_text($t[$j]) !== '('; $j = sig($t, $j, 1)) {
                if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
                $full  = ltrim($t[$j][1], '\\');
                $alias = is_tok($t[sig($t, $j, 1)] ?? null, [T_AS]) ? tok_text($t[sig($t, sig($t, $j, 1), 1)]) : substr(strrchr("\\$full", '\\'), 1);
                $uses[strtolower($alias)] = $full;
            }
        }
        if (is_tok($tok, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && !is_tok($t[sig($t, $i, -1)] ?? null, [T_DOUBLE_COLON])) $pending = 'class';
        if (is_tok($tok, [T_FUNCTION])) $pending = 'fn';
        if ($text === ';' && $pending === 'fn') $pending = null;
        if ($text === ';' && $init === count($braces) && !$stack) $init = null;
        // PHP allows no `new` in a property default or a class constant, so a field array there cannot become an object in place.
        if (end($braces) === 'class' && !$stack && $pending === null && $init === null && is_tok($tok, [T_VARIABLE, T_CONST, T_CASE])) $init = count($braces);

        $prev = sig($t, $i, -1);
        if (is_tok($tok, [T_ATTRIBUTE])) { $stack[] = ['open' => $i, 'array' => false, 'start' => $i]; continue; }
        if ($text === '[' || $text === '(') {
            $is_array = $text === '[' ? !is_tok($t[$prev] ?? null, [T_VARIABLE, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_CONSTANT_ENCAPSED_STRING]) && !in_array(tok_text($t[$prev] ?? ''), [']', ')', '}'], true) : is_tok($t[$prev] ?? null, [T_ARRAY]);
            $stack[] = ['open' => $i, 'array' => $is_array, 'start' => $is_array && $text === '(' ? $prev : $i];
            continue;
        }
        if (($text === ']' || $text === ')') && $stack) {
            $frame = array_pop($stack);
            if (!$frame['array']) continue;
            $arrays[$frame['start']] = $frame + ['close' => $i, 'parent' => $stack ? end($stack)['start'] : null, 'init' => $init !== null, 'ns' => $ns, 'uses' => $uses];
        }
    }
    if (!$arrays) return;

    $class_of = function (int $tok, array $a) use ($t, $index): array {
        $class = canonical_class(qualify(tok_text($t[$tok]), $a['ns'], $a['uses']));
        return [is_a_class($class, 'Lattice\\Field', $index), is_a_class($class, 'Lattice\\Component\\Field_Group', $index)];
    };
    foreach ($arrays as &$a) {
        $a['entries'] = array_entries($t, $a['open']);
        $a['field']   = null;
        if (($entry = entry_named($a['entries'], 'field')) && ($cls = class_constant($t, $entry)) !== null) {
            [$field, $group] = $class_of($cls, $a);
            if ($field || $group) $a['field'] = ['entry' => $entry, 'class' => $cls, 'is_field' => $field];
            elseif ($field === null || $group === null) $hand[] = [$lines[$cls], tok_text($t[$cls]) . "::class under 'field': its ancestry leaves every tree the script can read (pass --deps): if it is a Field, build it with new " . tok_text($t[$cls]) . '([...])'];
        }
    }
    unset($a);

    $entry_holding = function (?int $parent, int $start) use ($arrays): ?array {
        if ($parent === null || !isset($arrays[$parent])) return null;
        foreach ($arrays[$parent]['entries'] as $entry) if ($entry['value'] === $start) return $entry;
        return null;
    };
    $assigned_to = function (array $a) use ($t): ?string {
        $eq = sig($t, $a['start'], -1);
        if (tok_text($t[$eq] ?? '') !== '=') return null;
        $q = sig($t, $eq, -1);
        while (tok_text($t[$q] ?? '') === ']') { for ($d = 0; $q >= 0; $q--) { $v = tok_text($t[$q]); if ($v === ']') $d++; if ($v === '[' && --$d === 0) break; } $q = sig($t, $q, -1); }
        return is_tok($t[$q] ?? null, [T_VARIABLE]) && $q !== sig($t, $eq, -1) ? $t[$q][1] : null;
    };
    $field_vars = [];
    foreach ($arrays as $a) if ($a['field'] && ($var = $assigned_to($a)) !== null) $field_vars[$var] = true;

    $rename_key = function (array $a) use ($t, $lines, &$out, &$rewrites, &$hand): void {
        if (!($key = entry_named($a['entries'], 'key'))) return;
        $name = entry_named($a['entries'], 'name');
        $quote = tok_text($t[$key['from']])[0];
        $out[$key['from']] = "{$quote}name{$quote}";
        $pad = $t[$key['from'] + 1] ?? null;
        if (is_tok($pad, [T_WHITESPACE]) && !isset($out[$key['from'] + 1]) && preg_match('/^  +$/', $pad[1])) $out[$key['from'] + 1] = substr($pad[1], 1);
        if ($name === null) { $rewrites[] = [$lines[$key['from']], 'fields', "'key' -> 'name'"]; return; }
        drop_entry($t, $name, $out);
        $rewrites[] = [$lines[$key['from']], 'fields', "'key' -> 'name', its own 'name' dropped ('key' won at render)"];
        $value = fn ($entry) => trim(tokens_text($t, $entry['value'], $entry['to'], []));
        if ($value($key) !== $value($name)) $hand[] = [$lines[$key['from']], "a field carried 'key' (" . $value($key) . ") and 'name' (" . $value($name) . "): 'key' won at render and is kept as 'name'; check what read 'name' before render (Field_Group's defaults and field_params)"];
    };
    $wrap = function (array $a, string $before, string $after) use ($t, &$out): void {
        $out[$a['start']] = $before;
        for ($k = $a['start'] + 1; $k <= $a['open']; $k++) $out[$k] = '';
        $out[$a['close']] = $after;
    };

    foreach ($arrays as $a) {
        $line   = $lines[$a['start']];
        $holder = $entry_holding($a['parent'], $a['start']);
        $parent = $a['parent'] !== null ? ($arrays[$a['parent']] ?? null) : null;
        $type   = $parent ? entry_named($parent['entries'], 'type') : null;

        if ($holder && $holder['key'] === 'args' && $type && in_array(literal_value($t[$type['value']]), ['field', 'meta'], true) && $type['value'] === $type['to']) {
            $filter = $entry_holding($parent['parent'], $parent['start'])['key'] ?? null;
            if ($key = entry_named($a['entries'], 'key')) {
                $value = literal_value($t[$key['value']]);
                if ($value !== null && $value === $filter && $key['value'] === $key['to']) {
                    drop_entry($t, $key, $out);
                    $rewrites[] = [$lines[$key['from']], 'fields', "args 'key' => '$value' dropped (the filter's name)"];
                } else {
                    $hand[] = [$lines[$key['from']], "an admin table filter's args 'key' (" . trim(tokens_text($t, $key['value'], $key['to'], [])) . ") differs from its filter's name '$filter': 1.0 names the field after the filter, so rename the filter or move the key to its 'meta_key', then pass 'field' => new X([...])"];
                    continue;
                }
            }
            $others = array_filter($a['entries'], fn ($e) => !in_array($e['key'], ['field', 'key'], true));
            if (!$a['field']) {
                if ($others && !entry_named($a['entries'], 'field')) $hand[] = [$line, "an admin table filter's args with no 'field' (Input by default): pass 'field' => new \\Lattice\\Field\\Input([...]) holding them"];
                continue;
            }
            $class = $out[$a['field']['class']] ?? tok_text($t[$a['field']['class']]);
            if ($a['init']) { $hand[] = [$line, "an admin table filter's args in a property default, where PHP allows no new: move the filters into filters() and pass 'field' => new $class([...])"]; continue; }
            drop_entry($t, $a['field']['entry'], $out);
            $wrap($a, "['field' => new $class([", '])]');
            $rewrites[] = [$line, 'fields', "args 'field' => $class::class -> 'field' => new $class([...])"];
            continue;
        }

        if ($a['field']) {
            if ($a['field']['is_field']) $rename_key($a);
            $class = $out[$a['field']['class']] ?? tok_text($t[$a['field']['class']]);
            if ($a['init']) { $hand[] = [$line, "'field' => $class::class in a property default or constant, where PHP allows no new: build the field list in a method (get_fields(), params()) as new $class([...])"]; continue; }
            drop_entry($t, $a['field']['entry'], $out);
            $wrap($a, "new $class([", '])');
            $rewrites[] = [$line, 'fields', "['field' => $class::class, ...] -> new $class([...])"];
            continue;
        }

        $call = sig($t, $a['start'], -1);
        if (tok_text($t[$call] ?? '') === '(' && ($callee = sig($t, $call, -1)) >= 0) {
            $before = sig($t, $callee, -1);
            $recv   = is_tok($t[$before] ?? null, [T_NEW]) ? $callee : (strtolower(tok_text($t[$callee])) === 'render' && tok_text($t[$before] ?? '') === '::' ? sig($t, $before, -1) : null);
            if ($recv !== null && is_tok($t[$recv], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && $class_of($recv, $a)[0]) { $rename_key($a); continue; }
        }

        if (!entry_named($a['entries'], 'key') && !entry_named($a['entries'], 'name')) continue;
        $in_list = $parent && $holder && $holder['key'] === null && array_filter($parent['entries'], fn ($e) => isset($arrays[$e['value']]) && $arrays[$e['value']]['field']);
        $var     = $assigned_to($a);
        if ($in_list || $var !== null && isset($field_vars[$var])) $hand[] = [$line, "a field array with no 'field' (Input by default) beside field arrays: build it as new \\Lattice\\Field\\Input([...]), its 'key' as 'name'"];
    }
}

// ---------------------------------------------------------------------------------------------------------------------
// Life cycle

// A trait's own uses are walked too; $seen stops a cycle.
function own_inst (?string $class, array $index, array $seen = []): ?string {
    for ($hops = 0; $class !== null && $hops < 40 && isset($index[$key = strtolower($class)]) && !isset($seen[$key]); $hops++) {
        $seen[$key] = true;
        if ($index[$key][2]) return $index[$key][0];
        foreach ($index[$key][3] as $trait) if ($owner = own_inst($trait, $index, $seen)) return $owner;
        $class = $index[$key][1];
    }
    return null;
}

function rule_lifecycle (array $t, array $lines, array &$out, array &$rewrites, array &$hand, array $index): void {
    $ns      = '';
    $uses    = [];
    $depth   = 0;
    $classes = [];
    $pending = null;
    for ($i = 0; $i < count($t); $i++) {
        $tok  = $t[$i];
        $text = tok_text($tok);
        if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { $depth++; if ($pending !== null && $text === '{') { $classes[] = [$pending, $depth]; $pending = null; } }
        if ($text === '}') { if ($classes && end($classes)[1] === $depth) array_pop($classes); $depth--; }
        if (is_tok($tok, [T_NAMESPACE]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING, T_NAME_QUALIFIED])) { $ns = $t[sig($t, $i, 1)][1]; $uses = []; }
        if (is_tok($tok, [T_USE]) && !$classes && tok_text($t[sig($t, $i, 1)] ?? '') !== '(') {
            for ($j = sig($t, $i, 1); $j > 0 && tok_text($t[$j]) !== ';' && tok_text($t[$j]) !== '{'; $j = sig($t, $j, 1)) {
                if (!is_tok($t[$j], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) || is_tok($t[sig($t, $j, -1)], [T_AS])) continue;
                $full  = ltrim($t[$j][1], '\\');
                $alias = is_tok($t[sig($t, $j, 1)] ?? null, [T_AS]) ? tok_text($t[sig($t, sig($t, $j, 1), 1)]) : substr(strrchr("\\$full", '\\'), 1);
                $uses[strtolower($alias)] = $full;
            }
        }
        if (is_tok($tok, [T_CLASS, T_TRAIT]) && !is_tok($t[sig($t, $i, -1)] ?? null, [T_DOUBLE_COLON, T_NEW]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING])) {
            $pending = ($ns ? "$ns\\" : '') . $t[sig($t, $i, 1)][1];
        }

        if (is_tok($tok, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE]) && preg_match('/::inst\b/i', $text)) { $hand[] = [$lines[$i], "a string callable to inst ($text): use get_instance"]; continue; }
        if (strtolower((string) literal_value($tok)) === 'inst' && tok_text($t[sig($t, $i, -1)] ?? '') === ',') { $hand[] = [$lines[$i], "a callable naming inst ($text): use get_instance"]; continue; }
        if (!is_tok($tok, [T_DOUBLE_COLON])) continue;
        $verb_at = sig($t, $i, 1);
        $verb    = strtolower(tok_text($t[$verb_at] ?? ''));
        if (!in_array($verb, ['inst', 'get_instance'], true) || tok_text($t[sig($t, $verb_at, 1)] ?? '') !== '(') continue;
        $recv    = sig($t, $i, -1);
        $open    = sig($t, $verb_at, 1);
        $line    = $lines[$recv];
        $written = tok_text($t[$recv]);
        $shown   = $out[$recv] ?? $written;
        $named   = is_tok($t[$recv], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]);
        $current = $classes ? end($classes)[0] : null;
        $keyword = strtolower($written);
        $class   = match (true) {
            is_tok($t[$recv], [T_STATIC]) || $keyword === 'self' => $current,
            $keyword === 'parent'                               => $current !== null ? ($index[strtolower($current)][1] ?? null) : null,
            $named                                              => canonical_class(qualify($written, $ns, $uses)),
            default                                             => null,
        };

        if ($verb === 'inst') {
            if ($owner = own_inst($class, $index)) { $hand[] = [$line, "$written::inst() reaches $owner's own inst(), so it is left as written: rename that method, or call get_instance() if the framework's find was meant"]; continue; }
            $out[$verb_at] = 'get_instance';
            $rewrites[] = [$line, 'lifecycle', "$written::inst() -> get_instance()"];
        }

        [$args] = call_args($t, $open);
        $first  = $args ? sig($t, $args[0][0] - 1, 1) : -1;
        $array  = null;
        if (count($args) === 1 && (tok_text($t[$first]) === '[' || is_tok($t[$first], [T_ARRAY]))) {
            $array_open = tok_text($t[$first]) === '[' ? $first : sig($t, $first, 1);
            [$items, $array_close] = call_args($t, $array_open);
            if ($array_close === sig($t, $args[0][1] + 1, -1)) $array = $items ? array_entries($t, $array_open) : [];
        }

        if ($array === null) continue;
        if ($array === []) { $hand[] = [$line, "$written::$verb([]) is a bare find already: write $shown::get_instance()"]; continue; }
        $file = count($array) === 1 && $array[0]['key'] === 'file' && $array[0]['value'] === $array[0]['to'] ? $t[$array[0]['value']] : null;
        $hand[] = [$line, is_tok($file, [T_CONSTANT_ENCAPSED_STRING]) && is_a_class($class, 'Lattice\\Log', $index)
            ? "$written::$verb([...]) passes a config, which get_instance() refuses in 1.0: the owner declares the log, $shown::declare({$file[1]});"
            : "$written::$verb([...]) passes a config, which get_instance() refuses in 1.0: the owner makes it live with $shown::declare([...]) (a \$named class: declare('name', [...])), or create([...]) builds one nothing finds"];
    }
}

// A field group's field hook acts on the arrays the Fields rule turns into objects, so its callback stops matching silently.
function rule_field_hooks (array $t, array $lines, array &$hand): void {
    foreach (hook_sites($t) as [$i, $function]) {
        if (!in_array($function, ['add_filter', 'add_action', 'get_hooks'], true)) continue;
        if (!in_array(literal_value($t[$i]), ['Digitalis/Field_Group/Field', 'lattice.field_group.field'], true)) continue;
        $hand[] = [$lines[$i], "a callback on lattice.field_group.field: fields reach it as objects once the 'field' shorthand is new X([...]), so a callback that tests is_array(\$field) or edits \$field['...'] stops matching without an error; port it to the field object"];
    }
}

// ---------------------------------------------------------------------------------------------------------------------
// Copies

// The stream's base, the same tree UPGRADING.md pastes from, whatever 0.x copy of the framework the consumer carries.
const COPY_SOURCE = '45b11fc';

const COPY_CLASSES = [
    'Theme'                   => ['include/hooks/theme.abstract.php', 'lattice-copy/theme.abstract.php', null],
    'Woocommerce_Theme'       => ['include/integrations/_woocommerce/woocommerce.theme.php', 'lattice-copy/~woocommerce/woocommerce-theme.abstract.php', 'Theme'],
    'Woocommerce_Clean_Theme' => ['include/integrations/_woocommerce/woocommerce-clean.theme.php', 'lattice-copy/~woocommerce/woocommerce-clean-theme.abstract.php', 'Woocommerce_Theme'],
    'Can_Load'                => ['include/deprecated/loaders.php', 'lattice-copy/can-load.trait.php', null],
];

function copy_failure (string $message): never {
    fwrite(STDERR, "$message\n");
    exit(2);
}

function copy_git (string $framework, array $args): string {
    static $cache = [];
    $run = function (array $args) use ($framework): array {
        $proc = proc_open(array_merge(['git', '-C', $framework], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out  = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $out];
    };
    if (!$cache && $run(['cat-file', '-e', COPY_SOURCE . '^{commit}'])[0] !== 0) {
        copy_failure('The copies come from the framework at ' . COPY_SOURCE . ", which the repository at $framework does not have: fetch its history (git fetch --unshallow) and run again");
    }
    $key = implode("\0", $args);
    if (!isset($cache[$key])) {
        [$rc, $cache[$key]] = $run($args);
        if ($rc !== 0) copy_failure('git ' . implode(' ', $args) . " failed in $framework");
    }
    return $cache[$key];
}

function copy_edit (string &$text, string $from, string $to, string $what, array &$rewrites): void {
    if (substr_count($text, $from) !== 1) copy_failure('The copy source at ' . COPY_SOURCE . " does not read `$from` exactly once: the Copies rule needs updating");
    $at = strpos($text, $from);
    $rewrites[] = [substr_count($text, "\n", 0, $at) + 1, 'copies', $what];
    $text = substr_replace($text, $to, $at, strlen($from));
}

// loaders.php declares Can_Load first, then the Has_* traits that leave without a copy.
function trait_source (string $source, string $name): string {
    $t     = token_get_all($source);
    $text  = '';
    $found = false;
    $depth = 0;
    foreach ($t as $i => $tok) {
        $text .= tok_text($tok);
        if (!$found) { $found = is_tok($tok, [T_TRAIT]) && tok_text($t[sig($t, $i, 1)] ?? '') === $name; continue; }
        if (tok_text($tok) === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
        if (tok_text($tok) === '}' && --$depth === 0) return "$text\n";
    }
    copy_failure('The copy source at ' . COPY_SOURCE . " declares no trait $name");
}

// A framework class the copy names unqualified would resolve inside the copy's namespace, so each gets a use line.
function class_copy (string $source, string $namespace, array $imports): array {
    global $CLASSES;
    $t = token_get_all($source);
    foreach ($t as $i => $tok) {
        if (!is_tok($tok, [T_STRING]) || isset(COPY_CLASSES[$tok[1]]) || !isset($CLASSES[strtolower("Lattice\\{$tok[1]}")])) continue;
        if (is_tok($t[sig($t, $i, -1)] ?? null, [T_NEW, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF]) || is_tok($t[sig($t, $i, 1)] ?? null, [T_DOUBLE_COLON])) $imports[] = $CLASSES[strtolower("Lattice\\{$tok[1]}")];
    }
    $imports = array_unique($imports);
    sort($imports);
    $rewrites = [];
    $use = $imports ? "\n\n" . implode("\n", array_map(fn ($name) => "use $name;", $imports)) : '';
    copy_edit($source, 'namespace Lattice;', "namespace $namespace;$use", "namespace Lattice -> $namespace" . ($imports ? '; use ' . implode(', ', $imports) : ''), $rewrites);
    return [$source, $rewrites];
}

function scss_mask (string $line, bool $block): array {
    $code  = $line;
    $bare  = $line;
    $quote = null;
    for ($i = 0, $n = strlen($line); $i < $n; $i++) {
        $c    = $line[$i];
        $next = $line[$i + 1] ?? '';
        if ($block) {
            if ($c === '*' && $next === '/') { $code[$i] = $bare[$i] = $code[$i + 1] = $bare[$i + 1] = ' '; $i++; $block = false; }
            elseif ($c !== "\n") $code[$i] = $bare[$i] = ' ';
        } elseif ($quote !== null) {
            if ($c === '\\' && $i + 1 < $n) $bare[$i++] = ' ';
            elseif ($c === $quote) $quote = null;
            $bare[$i] = ' ';
        } elseif ($c === '"' || $c === "'") {
            $quote    = $c;
            $bare[$i] = ' ';
        } elseif ($c === '/' && $next === '/') {
            for (; $i < $n && $line[$i] !== "\n"; $i++) $code[$i] = $bare[$i] = ' ';
        } elseif ($c === '/' && $next === '*') {
            $code[$i] = $bare[$i] = $code[$i + 1] = $bare[$i + 1] = ' ';
            $i++;
            $block = true;
        }
    }
    return [$code, $bare, $block];
}

function scss_imports (string $source): array {
    $imports = [];
    $strays  = [];
    $block   = false;
    $depth   = 0;
    $offset  = 0;
    foreach (preg_split('/(?<=\n)/', $source) as $n => $line) {
        [$code, $bare, $block] = scss_mask($line, $block);
        preg_match_all('/@(?:import|use|forward)\b[^;]*/', $bare, $statements, PREG_OFFSET_CAPTURE);
        foreach ($statements[0] as [$statement, $at]) {
            $nested_depth = $depth + substr_count($bare, '{', 0, $at) - substr_count($bare, '}', 0, $at);
            preg_match_all('/([\'"])(.*?)\1/', substr($code, $at, strlen($statement)), $quoted, PREG_OFFSET_CAPTURE);
            foreach ($quoted[2] as [$path, $from]) {
                if (preg_match('#^\#\{\$digitalis_path\}/?scss/(.+)$#', $path, $m))                         $nested = false;
                elseif (!str_starts_with($path, '#{') && preg_match('#(?:^|/)framework/scss/(.+)$#', $path, $m)) $nested = true;
                else continue;
                $imports[] = [$n + 1, $offset + $at + $from, strlen($path), $path, "lattice/$m[1]", $nested_depth > 0, $nested];
                $code = substr_replace($code, str_repeat(' ', strlen($path)), $at + $from, strlen($path));
            }
        }
        if (preg_match('/\$digitalis_(path|uri)\b/', $code)) $strays[] = $n + 1;
        $depth  += substr_count($bare, '{') - substr_count($bare, '}');
        $offset += strlen($line);
    }
    return [$imports, $strays];
}

// Every copy is decided from the whole tree before any file is rewritten, since the names rule aims each use at its copy.
function plan_copies (string $root, array $files, array $index, string $framework): array {
    global $COPIED;
    $copies   = [];
    $scss     = [];
    $hand     = [];
    $need     = [];
    $comments = [];
    $strings  = '';

    foreach ($files as $file) {
        if (!str_ends_with($file, '.php')) continue;
        $relative = substr($file, strlen($root) + 1);
        $source   = file_get_contents($file);
        $t        = token_get_all($source);
        $lines    = lines_of($t);
        $skip     = (bool) preg_match('/^\s*namespace\s+Digitalis\s*[;{\\\\]/m', $source);
        $ns       = '';
        $ns_depth = 0;
        $depth    = 0;
        $import   = false;
        foreach ($t as $i => $tok) {
            $text = tok_text($tok);
            if (is_tok($tok, [T_COMMENT, T_DOC_COMMENT])) { $comments[] = [$relative, $lines[$i], $text]; continue; }
            if (is_tok($tok, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) { $strings .= "$text\n"; continue; }
            if ($skip) continue;
            if ($text === '{' || is_tok($tok, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $depth++;
            if ($text === '}') $depth--;
            if ($text === ';') $import = false;
            if (is_tok($tok, [T_NAMESPACE]) && is_tok($t[sig($t, $i, 1)] ?? null, [T_STRING, T_NAME_QUALIFIED])) {
                $ns       = $t[sig($t, $i, 1)][1];
                $ns_depth = tok_text($t[sig($t, sig($t, $i, 1), 1)] ?? '') === '{' ? $depth + 1 : 0;
            }
            if (is_tok($tok, [T_USE]) && $depth === $ns_depth && tok_text($t[sig($t, $i, 1)] ?? '') !== '(') $import = true;
            if (!is_tok($tok, [T_NAME_FULLY_QUALIFIED]) && !(is_tok($tok, [T_NAME_QUALIFIED]) && ($ns === '' || $import))) continue;
            if (!preg_match('/^\\\\?(?:Digitalis|Lattice)\\\\(\w+)$/', $text, $m)) continue;
            foreach (array_keys(COPY_CLASSES) as $short) if (strcasecmp($short, $m[1]) === 0) $need[$short] ??= [$relative, $lines[$i]];
        }
    }

    $dirs = [];
    foreach ($files as $file) {
        if (!str_ends_with($file, '.scss')) continue;
        $relative = substr($file, strlen($root) + 1);
        $source   = file_get_contents($file);
        [$imports, $strays] = scss_imports($source);
        foreach ($strays as $line) $hand[$relative][] = [$line, 'names $digitalis_path or $digitalis_uri, which 1.0 no longer injects: point it at a path of the consumer\'s own'];
        if (!$imports) continue;
        $off = array_values(array_filter($comments, fn ($comment) => str_contains($comment[2], $relative)));
        if ($off && !str_contains($strings, $relative)) {
            foreach ($imports as $import) $hand[$relative][] = [$import[0], "imports the framework's scss/ ({$import[3]}), but the stylesheet's only enqueue is commented out ({$off[0][0]}:{$off[0][1]}): no copy made; delete the import, or re-aim it at a copy if the stylesheet comes back"];
            continue;
        }
        $rewrites = [];
        foreach ($imports as [$line, , , $old, $new, $in_selector, $nested]) {
            $rewrites[] = [$line, 'scss', "$old -> $new"];
            if ($in_selector) $hand[$relative][] = [$line, "an import inside a selector, re-aimed to $new: compile it and compare the output"];
            if ($nested)      $hand[$relative][] = [$line, "an import that reached the framework's scss/ through a nested tree ($old), re-aimed to its own copy: compile it and compare the output"];
        }
        foreach (array_reverse($imports) as [, $at, $length, , $new]) $source = substr_replace($source, $new, $at, $length);
        $scss[$relative] = [$source, $rewrites];
        $dirs[dirname($relative) === '.' ? 'lattice' : dirname($relative) . '/lattice'] = true;
    }
    if ($dirs) {
        $sources = preg_split('/\n/', trim(copy_git($framework, ['ls-tree', '-r', '--name-only', COPY_SOURCE, '--', 'scss/'])));
        foreach (array_keys($dirs) as $dir) foreach ($sources as $from) $copies["$dir/" . substr($from, 5)] = [copy_git($framework, ['show', COPY_SOURCE . ":$from"]), [], COPY_SOURCE . ":$from"];
    }

    if ($need) {
        foreach (array_keys($need) as $short) for ($parent = COPY_CLASSES[$short][2]; $parent !== null; $parent = COPY_CLASSES[$parent][2]) $need[$parent] ??= $need[$short];
        $own  = array_flip($files);
        $apps = [];
        foreach ($index as $entry) if (isset($own[$entry[4] ?? '']) && is_a_class($entry[0], 'Lattice\App', $index)) $apps[substr($entry[0], 0, (int) strrpos($entry[0], '\\'))][] = $entry[4];
        $plugin = null;
        foreach (glob("$root/*.php") as $file) if (preg_match('/^[ \t\/*#]*Plugin Name:/mi', (string) file_get_contents($file, false, null, 0, 8192))) { $plugin = basename($file); break; }
        $why = match (true) {
            count($apps) !== 1 || array_key_first($apps) === '' => 'no one App subclass names the plugin\'s namespace (found: ' . (implode(', ', array_keys($apps)) ?: 'none') . ')',
            count(array_unique(array_map('dirname', reset($apps)))) !== 1 => 'its App subclasses sit in more than one directory',
            isset($need['Woocommerce_Clean_Theme']) && $plugin === null => 'no plugin file (a root .php with a Plugin Name header) to aim woo-clean.js at',
            default => null,
        };
        [$at_file, $at_line] = reset($need);
        if ($why !== null) {
            $hand[$at_file][] = [$at_line, implode(', ', array_keys($need)) . " leave(s) in 1.0 and no copy is made: $why; copy from UPGRADING.md by hand"];
        } else {
            $namespace = array_key_first($apps) . '\\Lattice_Copy';
            $base      = ltrim(substr(dirname(reset($apps)[0]), strlen($root)), '/');
            $prefix    = $base === '' ? '' : "$base/";
            foreach (COPY_CLASSES as $short => [$from, $to]) {
                if (!isset($need[$short])) continue;
                $COPIED[strtolower($short)] = "$namespace\\$short";
                $source = copy_git($framework, ['show', COPY_SOURCE . ":$from"]);
                if ($short === 'Can_Load') $source = trait_source($source, 'Can_Load');
                [$text, $rewrites] = class_copy($source, $namespace, $short === 'Can_Load' ? ['Lattice\Hook'] : []);
                $up = 'dirname(__DIR__, ' . substr_count("$prefix$to", '/') . ')';
                if ($short === 'Woocommerce_Clean_Theme') {
                    copy_edit($text, "LATTICE_PATH . 'templates/woocommerce-clean/'", "$up . '/templates/lattice-copy/woocommerce-clean/'", "the clean template from the consumer's templates/lattice-copy/", $rewrites);
                    copy_edit($text, "LATTICE_URI . 'assets/js/woo-clean.js'", "plugin_dir_url($up . '/$plugin') . 'assets/js/lattice-copy/woo-clean.js'", "woo-clean.js from the consumer's assets/js/lattice-copy/", $rewrites);
                }
                if ($short === 'Can_Load') copy_edit($text, "apply_filters('Digitalis/Instantiate/' . ltrim(\$class_name, '\\\\'), true, \$file_path)", "Hook::filter(['lattice', 'instantiate', ltrim(\$class_name, '\\\\')], true, \$file_path)", 'Digitalis/Instantiate/{class} -> lattice.instantiate.{class}', $rewrites);
                $copies[$prefix . $to] = [$text, $rewrites, COPY_SOURCE . ":$from"];
            }
            if (isset($need['Woocommerce_Clean_Theme'])) {
                foreach (['templates/woocommerce-clean/myaccount/navigation.php' => 'templates/lattice-copy/woocommerce-clean/myaccount/navigation.php', 'assets/js/woo-clean.js' => 'assets/js/lattice-copy/woo-clean.js'] as $from => $to) {
                    $copies[$to] = [copy_git($framework, ['show', COPY_SOURCE . ":$from"]), [], COPY_SOURCE . ":$from"];
                }
            }
        }
    }

    foreach ($copies as $path => [$text]) {
        if (!file_exists("$root/$path")) continue;
        if (file_get_contents("$root/$path") !== $text) $hand[$path][] = [1, 'the copy from ' . COPY_SOURCE . ' would land here, but a different file is in the way: not overwritten; compare them by hand'];
        unset($copies[$path]);
    }
    foreach ($hand as &$edits) usort($edits, fn ($a, $b) => $a[0] <=> $b[0]);
    return [$copies, $scss, $hand];
}

// ---------------------------------------------------------------------------------------------------------------------
// Run

$files = consumer_files($root);
$fired = fired_families($files);
$index = class_index($root, $deps, $framework);
$swapped = swapped_classes(array_merge([$root], glob("$deps/*", GLOB_ONLYDIR)));
[$copies, $stylesheets, $planned] = plan_copies($root, $files, $index, $framework);
$total_rewrites = 0;
$changed_files  = 0;
$copied_files   = 0;
$hand_edits     = [];

foreach (array_merge($files, array_map(fn ($path) => "$root/$path", array_keys($copies))) as $file) {
    $relative = substr($file, strlen($root) + 1);
    $copy     = $copies[$relative] ?? null;
    $source   = $copy[0] ?? file_get_contents($file);
    $rewrites = $copy ? [['-', 'copies', "new, from {$copy[2]}"], ...$copy[1]] : [];
    $hand     = $planned[$relative] ?? [];
    unset($planned[$relative]);

    if (str_ends_with($file, '.md')) {
        if ($hits = markdown_lines($source)) $hand_edits[] = [$relative, $hits[0], count($hits) . ' doc line(s) name forms the script rewrites in code (lines ' . implode(', ', $hits) . '): move them by hand'];
        continue;
    }

    if (!str_ends_with($file, '.php')) {
        [$new, $more] = $stylesheets[$relative] ?? [$source, []];
        array_push($rewrites, ...$more);
        foreach ($hand as [$line, $reason]) $hand_edits[] = [$relative, $line, $reason];
        if (!$rewrites) continue;
        echo "$relative\n";
        foreach ($rewrites as [$line, $rule, $what]) printf("  %5s  %-9s %s\n", $line, $rule, $what);
        $total_rewrites += count($more);
        $copy ? $copied_files++ : $changed_files++;
        if ($write) {
            if ($copy && !is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
            file_put_contents($file, $new);
        }
        continue;
    }

    $t     = token_get_all($source);
    $lines = lines_of($t);
    $out   = [];

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
        rule_field_hooks($t, $lines, $hand);
        // Before Fields and Render: both blank whole ranges and rebuild them from $out, which would drop or resurrect a later rename.
        rule_lifecycle($t, $lines, $out, $rewrites, $hand, $index);
        rule_fields($t, $lines, $out, $rewrites, $hand, $index);
        rule_render($t, $lines, $out, $rewrites, $hand, $index, $swapped);
    }

    foreach ($hand as [$line, $reason]) $hand_edits[] = [$relative, $line, $reason];
    if (!$rewrites) continue;

    usort($rewrites, fn ($a, $b) => ($a[0] === '-' ? 0 : $a[0]) <=> ($b[0] === '-' ? 0 : $b[0]));
    echo "$relative\n";
    foreach ($rewrites as [$line, $rule, $what]) printf("  %5s  %-9s %s\n", $line, $rule, preg_replace('/\s+/', ' ', $what));
    $total_rewrites += count(array_filter($rewrites, fn ($rewrite) => $rewrite[1] !== 'copies'));
    $copy ? $copied_files++ : $changed_files++;

    if ($write) {
        $new = '';
        foreach ($t as $i => $tok) $new .= $out[$i] ?? tok_text($tok);
        if ($copy && !is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
        file_put_contents($file, $new);
    }
}
foreach ($planned as $relative => $hand) foreach ($hand as [$line, $reason]) $hand_edits[] = [$relative, $line, $reason];

if ($hand_edits) {
    echo "\nHand edits\n";
    foreach ($hand_edits as [$relative, $line, $reason]) echo "  $relative:$line  $reason\n";
}

printf("\nRESULT: %d rewrite(s) in %d file(s)%s, %d file(s) copied, %d hand edit(s)\n", $total_rewrites, $changed_files, $write ? ' written' : '', $copied_files, count($hand_edits));
exit($hand_edits ? 1 : 0);
