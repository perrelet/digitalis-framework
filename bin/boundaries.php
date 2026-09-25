<?php
// The boundary rule: include/{core,models,views,routing,query,registration} never name an ACF, WooCommerce or page-builder
// symbol. Tokenizer-based, so strings and comments never match. `php bin/boundaries.php [framework-root]`; exit 1 on a hit.
PHP_SAPI === 'cli' || exit;
PHP_VERSION_ID >= 80000 || exit(fwrite(STDERR, "PHP 8.0+ required\n") && 2);

$root = realpath($argv[1] ?? dirname(__DIR__));
$dirs = ['core', 'models', 'views', 'routing', 'query', 'registration'];

// Functions are case-insensitive in PHP. Classes stay case-sensitive: Lattice's own ACF_Row would match acf_* otherwise.
$functions = '/^(acf|acf_[a-z_]+|get_field|get_fields|get_field_object|get_field_objects|have_rows|the_row|get_sub_field|get_sub_field_object|get_row|get_row_index|get_row_layout|the_field|the_sub_field|update_field|update_sub_field|delete_field|delete_sub_field|add_row|add_sub_row|update_row|update_sub_row|delete_row|delete_sub_row|wc_[a-z_]+|WC|woocommerce_[a-z_]+|is_woocommerce|is_shop|is_cart|is_checkout|is_account_page|is_product|is_product_[a-z_]+|is_wc_[a-z_]+|is_order_received_page|is_add_payment_method_page|bricks_[a-z_]+|oxygen_[a-z_]+|ct_[a-z_]+)$/i';
$classes   = '/^\\\\?(WC_[A-Za-z_]+|WooCommerce|ACF|acf_[a-z_]+|Automattic\\\\WooCommerce(\\\\.*)?|Bricks(\\\\.*)?|Oxygen[A-Za-z_]*|CT_[A-Za-z_]+)$/';
$constants = '/^(SHOW_CT_BUILDER|OXYGEN_IFRAME|BRICKS_[A-Z_]+|ACF_[A-Z_]+|WC_[A-Z_]+)$/';
$skip      = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
$names     = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
$hits      = [];
$is        = fn ($tok, $ids) => is_array($tok) && in_array($tok[0], $ids, true);

if (!$root || !is_dir("$root/include")) { fwrite(STDERR, "No include/ under " . ($root ?: 'the given root') . "\n"); exit(2); }

foreach ($dirs as $dir) {

    if (!is_dir("$root/include/$dir")) { fwrite(STDERR, "Missing include/$dir\n"); exit(2); }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/include/$dir")) as $file) {

        if ($file->getExtension() !== 'php') continue;

        $tokens = token_get_all(file_get_contents($file));
        $n      = count($tokens);
        $rel    = substr((string) $file, strlen($root) + 1);

        for ($i = 0; $i < $n; $i++) {

            $t = $tokens[$i];
            if (!is_array($t) || !in_array($t[0], $names, true)) continue;

            for ($p = $i - 1; $p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0], $skip, true); $p--);
            for ($q = $i + 1; $q < $n && is_array($tokens[$q]) && in_array($tokens[$q][0], $skip, true); $q++);

            $prev = $tokens[$p] ?? null;
            $next = $tokens[$q] ?? null;

            // Method calls, static calls, declarations, enum cases, named arguments and namespace names are not global symbols.
            if ($is($prev, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_CONST, T_NAMESPACE, T_CASE])) continue;
            if ($next === ':' && ($prev === '(' || $prev === ','))                                                       continue;

            // `function name (` declares; `use function name` imports the global and counts.
            if ($is($prev, [T_FUNCTION])) {

                for ($r = $p - 1; $r >= 0 && is_array($tokens[$r]) && in_array($tokens[$r][0], $skip, true); $r--);
                if ($is($tokens[$r] ?? null, [T_USE]) && preg_match($functions, ltrim($t[1], '\\'))) $hits[] = "$rel:$t[2] imports $t[1]";
                continue;

            }

            $name = preg_replace('/^namespace\\\\/', '', $t[1]);
            $line = $t[2];

            if ($next === '(' && !$is($prev, [T_NEW]) && preg_match($functions, ltrim($name, '\\'))) { $hits[] = "$rel:$line calls $name()"; continue; }
            if (preg_match($classes, $name))                                                          { $hits[] = "$rel:$line names $name";   continue; }
            if (preg_match($constants, $name) && $next !== '(')                                       { $hits[] = "$rel:$line reads $name";   continue; }

        }

    }

}

foreach ($hits as $hit) echo "  $hit\n";
printf("RESULT: %s\n", $hits ? count($hits) . ' boundary violation(s)' : 'clean');
exit($hits ? 1 : 0);
