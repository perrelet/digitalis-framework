# Spec: Menu — active state

**Status:** Draft
**Date:** 2026-05-19

Defines when a `Menu_Item` is marked `is_current` (the page being viewed) or `is_ancestor` (a hierarchical parent of the current page).

## Ownership

The pipeline runs **once per root `Menu`** — any `Menu` instantiated with `level === 1`. The root walks the full coerced tree depth-first and computes state for every item. Nested `Menu`s (`level > 1`) skip the pipeline at their own `params()` time; their state has already been set during the root's walk.

This requires the full tree to exist before the pipeline runs. [SPEC §4.4 step 3](./SPEC.md#44-lifecycle) handles this: it eagerly instantiates every `Menu` / `Menu_Item` in the tree before reaching step 5 (the pipeline pass). Step 5 of *this* document — menu-tree bubble — relies on that full-tree visibility to propagate ancestor state across nested Menu boundaries.

Mega-menu panels with arbitrary `content` are out of scope. If the panel's `content` is itself a `Menu`, that Menu is independently a root (`level === 1`) and runs its own pipeline over its own tree.

Each item is matched using the config of the `Menu` that directly owns it. Tree-wide config (`ancestor_taxonomies`, `match_current`, `match_ancestor`) propagates from parent to nested Menus at coercion time via [SPEC §4.5](./SPEC.md#45-param-propagation), so by default the root's config governs the whole tree. A nested Menu opts out by setting its own values in the submenu array.

## Definitions

| State | Meaning | Cardinality per menu |
|---|---|---|
| `is_current` | Item represents the page being viewed | At most one |
| `is_ancestor` | Item represents a content / URL / menu-tree parent of the current page | Many |

If something would qualify as both, `is_current` wins.

## Matching pipeline

For each item, in order. Steps short-circuit when `is_current` is set.

```
1. Object-ID match     → may set is_current
2. URL exact match     → may set is_current (fallback when no object_id)
3. Content-ancestor    → may set is_ancestor
4. URL prefix match    → may set is_ancestor (catch-all)
5. Menu-tree bubble    → any item with a current/ancestor descendant becomes ancestor
```

## Step 1 — Object-ID match

`item.object_id` + `item.object_type` are compared to `get_queried_object()`:

| `object_type` | `object_id` is | Match condition |
|---|---|---|
| `post_type` | Post ID | `is_singular()` && `get_queried_object_id() === object_id` |
| `taxonomy` | Term ID | (`is_tax()` \|\| `is_category()` \|\| `is_tag()`) && queried term ID === object_id |
| `post_type_archive` | Post type slug | `is_post_type_archive(object_id)` |
| `custom` | (none) | Never — falls through |

`post_type_archive` is a synthetic type. WordPress doesn't emit it natively. `Nav_Menu_Item::detect_post_type_archive()` rewrites `object_type='custom'` items whose URL matches `get_post_type_archive_link($pt)` for some registered post type.

## Step 2 — URL exact match

After normalisation, `item.url === current_url` sets `is_current`.

## Step 3 — Content-ancestor rules

Applied in order. First to fire sets `is_ancestor`.

1. **CPT-archive item, queried is singular of that CPT.**
   `item.object_type === 'post_type_archive'` && `is_singular($item.object_id)`.

2. **Taxonomy-term item, queried post is classified by that term.**
   `item.object_type === 'taxonomy'` && queried post has term `item.object_id` in the matching taxonomy.

   Scoped by the `ancestor_taxonomies` `Menu` param — see Configuration.

3. **Taxonomy-term item, queried is a descendant term.**
   `item.object_type === 'taxonomy'`, queried object is a term, item term is in `get_ancestors(queried_id, taxonomy, 'taxonomy')`.

4. **CPT-archive item, queried is a term in a taxonomy applied to that CPT.**
   `item.object_type === 'post_type_archive'`, queried is a term, its taxonomy is registered against `item.object_id` post type.

5. **Hierarchical post-type item, queried is a descendant post.**
   `item.object_type === 'post_type'`, post type is hierarchical, item post ID is in `get_post_ancestors(queried)`.

## Step 4 — URL prefix match

If no current/ancestor yet and `item.url` is set: after normalisation, if `current_url` starts with `item.url + '/'`, set `is_ancestor`. The home URL (`/`) is excluded — never an ancestor of anything.

## Step 5 — Menu-tree bubble

Post-order pass: any item with a current-or-ancestor descendant becomes `is_ancestor`. A current item does not upgrade itself to ancestor — only its parents do.

## URL normalisation

Applied to both sides of every URL comparison:

1. Strip scheme + host if it matches `home_url()`. Otherwise URL is external — no match possible.
2. Strip query string and fragment.
3. URL-decode.
4. Strip trailing slash; ensure leading slash.
5. Empty path → `/`.

## Configuration

`Menu` params relevant to active-state:

| Param | Default | Effect |
|---|---|---|
| `ancestor_taxonomies` | `null` | Restricts content-rule 2 to the listed taxonomy slugs. `null` means all hierarchical taxonomies count (WP-like default). Set to `['primary-tax']` when only one taxonomy represents navigation hierarchy and others are cross-cuts that shouldn't bubble. |
| `match_current` | `true` | If false, skip steps 1–2; no item gets `is_current`. |
| `match_ancestor` | `true` | If false, skip steps 3–5; no item gets `is_ancestor`. |

**Per-item override.** If an input item array sets `is_current` or `is_ancestor` explicitly, the pipeline skips matching for that item. Escape hatch for cases the rules can't model (e.g., a "Featured" item that's current on a marketing campaign page only).

## Per-source behaviour

| Source | `object_id` / `object_type` | `url` | `is_current` / `is_ancestor` |
|---|---|---|---|
| `Nav_Menu` / `Nav_Menu_Item` | From the decorated `WP_Post` menu item, with archive-detection rewrite | From WP | Computed by pipeline |
| Hand-built array | Optional, consumer-supplied | Optional | Optional override — explicit values skip the pipeline |
| Adapted (callable) | Whatever adapter returns | Whatever adapter returns | Optional override — adapter may pre-compute |

## Worked examples

Illustrative content model: a site with `product` (CPT), `product-category` (hierarchical taxonomy on product), `article` (CPT), and standard `page`. No project-specific assumptions — adjust slugs to your own.

### Single product `/products/widgets/super-widget/`

Queried: product post with term `widgets` in `product-category`.

| Item | Object | Step | State |
|---|---|---|---|
| Products | archive: product | Content rule 1 | ancestor |
| └ Widgets | term: widgets | Content rule 2 | ancestor |
| └ Gizmos | term: gizmos | — | — |
| About | page | — | — |

### Taxonomy archive `/products/widgets/`

Queried: term `widgets`.

| Item | Object | Step | State |
|---|---|---|---|
| Products | archive: product | Content rule 4 | ancestor |
| └ Widgets | term | Object-ID | **current** |

### Descendant under a URL-only parent `/about/team/jane-doe/`

Queried: a CPT post under a `/about/team/` archive, where `/about/` is a separate page. Not connected by post-parent — connected by URL.

| Item | Object | Step | State |
|---|---|---|---|
| About | page `/about/` | URL prefix | ancestor |
| └ Team | archive: team-member | Content rule 1 | ancestor |

### Grouped CPT archive `/articles/`

Queried: `is_post_type_archive('article')`.

| Item | Object | Step | State |
|---|---|---|---|
| Resources (no URL) | — | Tree bubble | ancestor |
| └ Articles | archive: article | Object-ID | **current** |
| └ Reports | archive: report | — | — |

## Edge cases

- **No queried object** (`get_queried_object()` returns null on 404, search, some custom views) — all steps fail gracefully; nothing is marked.
- **Home page** — `is_front_page()` matches the home item via Object-ID or URL exact match; the home URL `/` never participates in URL prefix matching.
- **External URLs** — items with URLs outside `home_url()` never match.
- **Same URL twice in the menu** — both items get marked. Visual differentiation is a consumer concern.
- **Mega-menu panels** — panels with arbitrary content (non–`Menu_Item` views) don't participate in matching. Nested `Menu_Item`s inside a panel still do.
- **Deep nesting** — recursion handles arbitrary depth; tree bubble (step 5) propagates ancestor state to the root.

## Out of scope

- Multilingual URL variants — delegated to WPML / Polylang / equivalents; the consumer (or those plugins' filters) supplies translated URLs upstream of `Menu`.
- Multisite cross-site URL matching.
- Client-side active-state updates (no SPA behaviour).
- "Recently viewed" / history-derived state.
