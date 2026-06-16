# Spec: `Post::query` / `Archive::get_items` migration

**Status:** Draft
**Date:** 2026-05-20

Breaking change to clean up a long-standing arg-discard smell in `Post::query()` and its `Archive::get_items()` consumer. This spec is the per-site migration plan: what changed, why, the audit greps in safe order, and verification.

---

## What changed

| API | Before | After |
|---|---|---|
| `Post::query()` | `($args, &$query, $skip_main = false)` — silently reused `$wp_query` and discarded `$args` when `$skip_main` was false and the main query matched `static::$post_type` | `($args, &$query)` — always runs a fresh DB query, always honors `$args` |
| `Post::get_from_main_query()` | — (did not exist) | `(&$query)` — returns `$GLOBALS['wp_query']->posts` as model instances. Use for archive pages where you want the page's current-loop slice (with its pagination/orderby) |
| `Archive::get_items()` | `($query_vars, &$query, $skip_main)` | `($query_vars, &$query)` — dispatches internally via `item_model::is_main_query()` auto-detect |
| `Archive` `$defaults` | `'skip_main' => false` | `'use_main_query' => null` — `null` auto-detects, `true` forces `get_from_main_query()`, `false` forces `query()` |

## Why

The previous `Post::query()` silently dropped caller-supplied `$args` whenever `$skip_main` was false (the default) and the current `$wp_query` matched `static::$post_type`. Symptoms in consumer code:

- `Post::query(['orderby' => 'menu_order'])` on a same-post-type archive page returned the main loop's posts in its own order, ignoring the requested `orderby`.
- `Post::query(['fields' => 'ids'])` similarly returned hydrated `WP_Post` objects, breaking `wp_get_object_terms()` and other int[]-expecting helpers downstream.
- `Post::query(['meta_query' => ...])` on a single-post page (where `is_main_query()` returns true for that post type) silently returned just the current post, defeating the meta filter.

No warning, no error — diagnosing was expensive. The split aligns the API with intent: `query()` is a data accessor that does what its args say; `get_from_main_query()` is the explicit way to ask for the page's current loop.

---

## Migration

Three greps in order. **The first is the dangerous one — missing a `get_items` override fails at class load.**

### 1. `get_items` overrides — fatal at load if missed

```sh
grep -rn "function get_items" include/
```

For every match, drop the third arg from the signature:

```php
// Before
public function get_items ($query_vars, &$query, $skip_main) { ... }

// After
public function get_items ($query_vars, &$query) { ... }
```

The body almost always stays the same — these overrides typically ignored `$skip_main` and did source-based lookups (e.g. `$source->get_related_products()`).

Symptom if missed:

> PHP Fatal error: Declaration of X::get_items($query_vars, &$query, $skip_main) must be compatible with Digitalis\Archive::get_items($query_vars, &$query)

The class fatals at load, so the page 500s before rendering. Affects any URL that triggers the autoload of the offending file — not just pages that use the component.

### 2. `Archive` subclass `$defaults` with `skip_main`

```sh
grep -rn "'skip_main' =>" include/
```

| Existing | New | Reason |
|---|---|---|
| `'skip_main' => false` (or absent) | drop the entry | Default `'use_main_query' => null` auto-detects via `is_main_query()` — same behavior in the common case |
| `'skip_main' => true` (explicit opt-out of main-query reuse) | `'use_main_query' => false` | Same intent: force fresh query with `query_vars` |

Symptom if missed: the `skip_main` key becomes a silent no-op. Consumers that had `'skip_main' => true` to *avoid* main-query reuse will silently start reusing the main query on same-post-type archive pages.

### 3. Three-arg `::query()` call sites

```sh
grep -rnE "::query\s*\([^)]*,[^)]*,[^)]*\)" include/
```

| Existing third arg | New | Reason |
|---|---|---|
| `true` (force fresh) | drop the `true` | New `query()` is always fresh; behavior preserved |
| `false` (matches old default) | drop the `false` | Same |
| variable / expression | inspect case-by-case | Was the caller branching on context? Convert to explicit `query()` vs `get_from_main_query()` at the call site |

For the rare case where the original intent was *"give me the main query's posts"*, replace the call with `Post::get_from_main_query($query)` (note: no `$args`, no `$query_vars` — those don't apply when reusing the main loop).

---

## Verification

After all three greps + fixes:

1. `php -l` every modified file.
2. Hit the post-type archive pages for each registered post type — HTTP 200.
3. Hit a single-post page for each major post type — HTTP 200. The old short-circuit also fired on singles, so missed callers can still cause 500s there.
4. If you have admin / cron / CLI consumers of `::query()`, smoke-test those flows. They won't be exercised by HTTP requests.

If your site uses pagination on any archive, also confirm `wp_query->found_posts` and `wp_query->max_num_pages` still resolve correctly on those pages — both `query()` and `get_from_main_query()` populate the `&$query` reference, but the underlying source differs (a fresh `WP_Query` vs the global one).

---

## Rollback

If a downstream consumer breaks in a way the audit didn't catch:

1. Revert the Lattice submodule to the pre-change commit.
2. The site-side cleanup (dropped third args, renamed `skip_main` → `use_main_query`) is forward-compatible — those edits don't break against the old Lattice as long as no consumer relies on the now-removed `skip_main` key being read. The old Lattice will just see an unknown `use_main_query` default key (ignored) and the missing `skip_main` (falls back to its own default `false`).

Practically: a partial-rollback by reverting only Lattice is safe even if the site-side cleanup is left in place.

---

## d-pace as the reference site

The d-pace plugin (`/wp-content/plugins/d-pace`) was the first site upgraded. Audit results from that pass:

- `get_items` overrides: 1 (`Related_Products` at `include/views/product/related-products.product-archive.php`). Source-lookup pattern, body unchanged.
- `'skip_main' =>` in archive defaults: 0.
- Three-arg `::query()` calls: 2 (`Product_Category::get_count()`, `Product_Category::get_products()`). Both passed `true`; dropped.

Two-arg `::query()` calls (no signature change needed, but behavior changed from "silently ignore args on archive" to "honor args"):

- `Product::get_accessories()` (`include/models/product.post.php`) — `meta_query` LIKE was previously silently dropped on product detail pages, returning the current product instead of its accessories. After the fix the query is honored. Not currently surfaced in any rendered view, but the latent bug is gone.

Use this as a sanity check for the audit on other sites — the numbers won't match exactly, but the *shape* of what you find should look similar.

---

## Addendum: `main_query_is_archive` — singular-page reuse fix

Follow-up to the same main-query-reuse decision. Ships in the same coordinated bump.

### What changed

| API | Before | After |
|---|---|---|
| `Archive::get_items()` auto-detect (`use_main_query => null`) | reused the main loop when `item_model::is_main_query($wp_query)` | reuses only when `item_model::main_query_is_archive($wp_query)` |
| `Post::main_query_is_archive()` | — (did not exist) | `is_main_query($wp_query) && !$wp_query->is_singular()` — true only when the main query is a *listing* of this post type |
| `Post::is_main_query()` | unchanged | unchanged — still WP-parity (`$wp_query->is_main_query()` + post-type match) |

### Why

`is_main_query()` returns `true` on a **singular page** of the matching post type — the page's one post *is* of that type (see the `Product::get_accessories()` note above for the same fact). The `null` auto-detect used that predicate directly, so an `Archive` with the default `use_main_query => null`, rendered on a singular page of its `item_model`'s post type, reused the main loop and returned **the page's single post** instead of running its intended `query_vars`.

The fix is a new, intention-named predicate for the reuse decision. `is_main_query()` is deliberately **not** redefined — it keeps its WP-parity meaning so direct callers across other sites are untouched.

### The trigger is runtime-contextual — grep is not enough

This delta has **no static call-site change to grep for**. It surfaces only when all of these hold at render time: an `Archive` subclass, `use_main_query` unset/`null`, *not* overriding `get_items`, rendered on a singular page whose main-query post type equals the archive's `item_model` post type. A grepper who finds "no changed call sites" will wrongly conclude "safe."

So the audit is reassurance + a runtime check:

```sh
# 1. Direct callers of is_main_query() — confirm (and note) they are UNAFFECTED.
grep -rn "::is_main_query" include/

# 2. Archive subclasses + their use_main_query value — any on `null`/unset are
#    candidates IF embedded on a singular page of their item_model's type.
grep -rn "use_main_query" include/
grep -rln "extends Archive" include/

# 3. Non-Post item_models that hand-rolled is_main_query() to join the archive
#    auto-detect. Post subclasses inherit main_query_is_archive() for free; a
#    duck-typed model that does NOT extend Post must add main_query_is_archive()
#    or it silently loses null-auto-detect reuse and falls back to query().
grep -rn "function is_main_query" include/
```

The `null` auto-detect's required `item_model` interface changed: `is_main_query` → `main_query_is_archive`. Transparent for every `Post` subclass; only grep #3's non-`Post` duck-typed models need a one-method addition.

### Verification

1. `php -l` the modified files.
2. **The real check:** hit a **singular page for each post type that embeds an `Archive` component** (related-items strips, "more from this category" blocks, etc.). Confirm 200 **and** that the embedded archive shows its intended list, not the host page's single post.
3. Post-type archive and taxonomy pages are unchanged by this fix, but re-confirm 200 anyway.

### Rollback

Both edits (the new predicate and its sole call site) live in Lattice, so they revert atomically — there is **no site-side change** for this addendum, and reverting the submodule fully undoes it. Separately, the `get_items` `is_callable` guard means an `item_model` that doesn't extend `Post` (so lacks `main_query_is_archive`) just falls through to `query()` rather than fataling.
