# Spec: Menu

**Status:** Draft
**Date:** 2026-05-19

Comprehensive spec for the `Menu`, `Menu_Item`, and `Menu_Drawer` framework components. Algorithm for `is_current` / `is_ancestor` lives in [ACTIVE_STATE.md](./ACTIVE_STATE.md).

---

## 1. Overview

`Menu` is a recursive list renderer for site navigation. It emits a `<nav><ul>` tree of items, each of which may be a link, a disclosure (toggles a submenu), both, or a structural marker (divider, heading). It is source-agnostic — items can come from a WordPress nav menu, a hand-built array, or any iterable adapted via a callable.

`Menu_Item` renders a single `<li>` and decides its interactive shape (link, button, or both) from its params.

`Menu_Drawer` is an opt-in wrapper that composes a toggle button + a `Menu` for off-canvas / mobile-drawer chrome. It is not a `Menu` subclass.

### Scope

The framework primitive owns:

- Semantic markup with correct ARIA per item shape
- Recursive nesting to arbitrary depth
- Disclosure (default) and menubar (opt-in) ARIA patterns
- Keyboard reach + focus management
- A small JS that handles toggles, Escape, click-outside, and (optionally) hover
- Structural CSS for state visibility and orientation
- `is_current` / `is_ancestor` resolution (see ACTIVE_STATE)
- Active-state attributes on rendered markup

It does not own:

- Visual styling (colour, typography, spacing beyond gaps)
- Mega-menu panel content layout
- Collision flipping (hook reserved via `data-collision`, implementation deferred)
- Multilingual URL variants
- Persistent / cross-request state

---

## 2. ARIA pattern

Two patterns, picked per-menu via the `pattern` param:

| Pattern | Use for | Roles | Keyboard |
|---|---|---|---|
| `disclosure` (default) | Site navigation | `<nav>` + `<ul>` + `<button aria-expanded>` for submenu triggers | Tab through; Enter/Space toggles |
| `menubar` | Application-style menus (admin toolbars, in-app menus) | `role='menubar'` + `role='menu'` + `role='menuitem'` | Arrow keys between items |

Default is `disclosure`. Menubar is wrong for site nav — screen-reader users get application-menu semantics where they expect site-nav semantics. Available as opt-in for the rare admin-toolbar case.

---

## 3. Components

```
Menu                recursive list renderer
Menu_Item           single <li> with link / disclosure / both / structural
Menu_Drawer         off-canvas wrapper (button + drawer + Menu inside)
Nav_Menu            (Term model) wraps a WP nav_menu term
Nav_Menu_Item       (Post model) wraps a WP nav_menu_item post
```

### 3.1 Menu

Extends `Component`. Renders `<nav><ul>…</ul></nav>` at level 1 (when `landmark=true`), `<ul>…</ul>` at deeper levels or when landmark is off. Recursion happens when a `Menu_Item` has a `submenu` param — that param is eagerly coerced to a nested `Menu` instance with `level` incremented (see §4.4 step 3).

### 3.2 Menu_Item

Extends `Component`. Renders a single `<li>`. Decides its shape from params:

Dash (—) means "unset" for `url`/`submenu`/`content` and "ignored" for `divider`/`heading`.

| `url` | `submenu` | `content` | `divider` | `heading` | Shape |
|---|---|---|---|---|---|
| set | unset | unset | — | — | link only — `<a>` |
| unset | set | unset | — | — | disclosure only — `<button>` + `<ul>` |
| set | set | unset | — | — | both — `<a>` + `<button>` + `<ul>` |
| unset | unset | set | — | — | mega-menu only — `<button>` + `<div role='region'>` |
| set | unset | set | — | — | mega-menu link — `<a>` + `<button>` + `<div role='region'>` |
| — | — | — | true | — | divider — `<li role='separator'>` |
| — | — | — | — | set | heading — `<li role='presentation'><h_>` |

If both `submenu` and `content` are set, `submenu` wins. Active-state matching does not recurse into the panel's `content`; if the panel's `content` is a `Menu` instance, that `Menu` owns its own active-state. Other view types or raw markup inside `content` receive no automatic active-state.

### 3.3 Menu_Drawer

**Status:** Deferred to stage 6 — not yet implemented.

Extends `Component`. Renders a toggle `<button>` + a `<div>` drawer containing the wrapped `Menu`. Owns focus trap, scroll lock, Escape close, click-outside close. Hidden above its `breakpoint`.

Not a `Menu` subclass — composition over inheritance. The drawer toggle is always click-triggered; the drawer has no hover affordance.

### 3.4 Nav_Menu / Nav_Menu_Item

`Nav_Menu extends Term` (taxonomy `nav_menu`) and `Nav_Menu_Item extends Post` (post type `nav_menu_item`) are the model layer that wraps WP's nav menu storage. `Nav_Menu::get_instance($id_or_slug_or_name)` is the entry point; `->get_items_tree()` returns the nested array `Menu`'s `items` param expects. `Nav_Menu_Item::prepare_wp_post()` runs `wp_setup_nav_menu_item()` for the single-item path; the batch path via `wp_get_nav_menu_items()` short-circuits because items arrive already-decorated. Subclass `Nav_Menu_Item` and override `as_menu_item_params()` to surface ACF / project-specific fields onto each item. See section 9.

---

## 4. PHP API

**PHP target: 8.2.** Unlocks `readonly` properties (8.1) and `readonly` classes (8.2), enums, intersection / union / DNF types, `never` return type, and `new` in initialisers. The `/* readonly */` placeholders in lattice's existing source can be converted alongside this work. `array_is_list` is native (8.1); the polyfill in `lattice/include/functions.php` becomes a no-op fallback.

### 4.1 Menu params

| Param | Type | Default | Effect |
|---|---|---|---|
| `items` | `array\|null` | `[]` | Item arrays or `Menu_Item` instances. Each array entry is coerced via `Menu_Item::class` (see `item_class`). |
| `source` | `string\|int\|null` | `null` | When set and `items` is empty, items are loaded via `Nav_Menu::get_instance($source)->get_items_tree()`. Accepts menu ID, slug, or name. |
| `adapter` | `callable\|null` | `null` | `fn(mixed $entry): array` — applied to each entry of `items` before coercion. Useful for `Term[]`, `Post[]`, custom data. |
| `aria_label` | `string` | `'Menu'` | `aria-label` on the `<nav>` landmark. Used only when `landmark=true`. When false, the consumer's wrapper `<nav>` is responsible for its own label. |
| `pattern` | `string` | `'disclosure'` | `'disclosure'` or `'menubar'`. See section 2. Propagates to nested Menus. |
| `orientation` | `string` | `'horizontal'` | `'horizontal'` or `'vertical'`. Applies to this menu's `<ul>`; submenus default to `'vertical'`. Does **not** propagate. |
| `trigger` | `array` | `['click']` | Subset of `['click', 'hover']`. Hover only attaches on `(pointer: fine)`. Propagates to nested Menus. |
| `multi_open` | `bool` | `false` | When false, opening a sibling submenu closes the previously open one at that level. Propagates. |
| `expand_ancestor` | `bool` | `false` | When true, submenus on the path to the current item render with `data-state='open'` server-side. Propagates. |
| `ancestor_taxonomies` | `array\|null` | `null` | Restricts content-rule 2 in ACTIVE_STATE to listed taxonomies. See [ACTIVE_STATE](./ACTIVE_STATE.md#configuration). Propagates. |
| `match_current` | `bool` | `true` | Toggles steps 1–2 of the active-state pipeline. Propagates. |
| `match_ancestor` | `bool` | `true` | Toggles steps 3–5 of the active-state pipeline. Propagates. |
| `landmark` | `bool` | `true` | When true (level 1 only), wraps in `<nav>`. Set false when the parent is already a nav landmark. Ignored at `level > 1`. |
| `toggle_label_format` | `string` | `'Toggle %s submenu'` | `sprintf` format for disclosure-button `aria-label`, used **only** when the button has no visible text (link+disclosure / link+mega shapes). For disclosure-only / mega-only shapes the button's visible text serves as the accessible name and no `aria-label` is emitted. `%s` receives item text. Propagates. |
| `id` | `string\|null` | `null` | DOM id of the root `<ul>`. The `<nav>` wrapper (when present) carries no id — the `<ul>` is the canonical CSS / JS target. Skip-link targets jump into the `<ul>` directly. When null, derived — see §4.6. |
| `level` | `int` | `1` | Internal — set by parent on recursion. Do not pass at level 1. |
| `item_class` | `string` | `Menu_Item::class` | Class used to coerce array items. Subclass to extend. Propagates. |

### 4.2 Menu_Item params

| Param | Type | Default | Effect |
|---|---|---|---|
| `text` | `string` | `''` | Visible label. Required for link, disclosure, and heading shapes; ignored for divider. |
| `url` | `string\|null` | `null` | If set, renders `<a href>`. |
| `submenu` | `array\|Menu\|null` | `null` | Array is coerced to nested `Menu`. |
| `content` | `string\|View\|null` | `null` | Mega-menu panel content. If `submenu` is also set, `submenu` wins. |
| `divider` | `bool` | `false` | Renders `<li role='separator'>`. Other params ignored. |
| `heading` | `string\|null` | `null` | Renders `<li>` with a heading element. Other interactive params ignored. |
| `heading_level` | `int` | `3` | `<h2>`..`<h6>` element for heading items. |
| `is_current` | `bool\|null` | `null` | `null` = pipeline decides. `bool` = explicit override (skips pipeline for this item). |
| `is_ancestor` | `bool\|null` | `null` | Same. |
| `object_id` | `int\|string\|null` | `null` | Used by ACTIVE_STATE pipeline (post ID, term ID, or post type slug). |
| `object_type` | `string\|null` | `null` | `'post_type'`, `'taxonomy'`, `'post_type_archive'`, `'custom'`. |
| `description` | `string\|null` | `null` | Optional supporting text. When set, renders as `<span class='menu-item-description' id='{li_id}-desc'>` after the interactive element(s) and before any submenu/panel. The link/button receives `aria-describedby='{li_id}-desc'`. |
| `target` | `string\|null` | `null` | `target` attribute on the `<a>`. When `'_blank'`, framework also emits `rel='noopener noreferrer'` and augments the link's `aria-label` to indicate it opens in a new tab. |
| `level` | `int` | `1` | Set by parent. |
| `menu_class` | `string` | `Menu::class` | Class used to coerce array submenus. Subclass to extend. |
| `external_label_format` | `string` | `'%s (opens in new tab)'` | `sprintf` format for the `aria-label` augmentation applied to links with `target='_blank'`. `%s` receives the original link text. |
| `wp_post` | `WP_Post\|null` | `null` | When loaded via `Nav_Menu`, the underlying (decorated) menu-item post — for ACF / meta access in `Menu_Item` subclasses. |

Standard `Component` attribute params (`classes`, `attr`, `styles`, …) are inherited from the base class and target the `<li>`. They aren't re-declared here.

### 4.3 Menu_Drawer params

**Status:** Deferred to stage 6.

| Param | Type | Default | Effect |
|---|---|---|---|
| `menu` | `Menu` | required | The wrapped menu. Recommended `orientation: 'vertical'`. |
| `aria_label_open` | `string` | `'Open menu'` | Toggle button label when closed. |
| `aria_label_close` | `string` | `'Close menu'` | Toggle button label when open. |
| `position` | `string` | `'inline-end'` | `'inline-start'`, `'inline-end'`, `'block-start'`, `'block-end'`. Logical-property based — RTL-aware. |
| `breakpoint` | `string` | `'60rem'` | Toggle hidden and drawer non-functional above this width. |
| `trap_focus` | `bool` | `true` | Cycle Tab/Shift+Tab within the drawer while open. |
| `lock_scroll` | `bool` | `true` | Lock body scroll while open (preserves scroll position via `position: fixed` + offset). |
| `close_on_navigate` | `bool` | `true` | Close drawer on any in-drawer link click. |
| `id` | `string\|null` | `null` | DOM id of the drawer. |

### 4.4 Lifecycle

`Menu::condition()` returns `count($items) > 0` after source loading and adapter mapping. Empty menus render nothing.

`Menu_Item::condition()` returns false when the item has no resolvable shape — no `text` AND no `divider` AND no `heading` AND no `content`. Filters out empty entries from a WP menu without consumer intervention.

`Menu::params()` order of operations:

1. If `source` set and `items` empty → load via `Nav_Menu::get_instance($source)->get_items_tree()`.
2. If `adapter` set → map each entry through it.
3. **Eagerly coerce the full tree.** Each array entry becomes a `Menu_Item` instance (via `item_class`); within each `Menu_Item`, any `submenu` array becomes a nested `Menu` instance (via `menu_class`); recurse. After this step, the complete tree of `Menu` / `Menu_Item` instances exists in memory — including branches the user may never open. Tree-wide params propagate during the walk (see §4.5). Constructors must be side-effect-free (see §4.7).
4. Set each item's `level` to `$this['level']`.
5. **Run active-state pipeline — root only.** When `level === 1`, walk the coerced tree depth-first and run the pipeline from ACTIVE_STATE.md across it. Each item is matched using the config of the `Menu` that directly owns it (propagation in §4.5 keeps config consistent unless a nested Menu explicitly overrides). Nested Menus (`level > 1`) skip this step entirely; their state has already been set by the root's walk.
6. Compute `id` if null (see §4.6).
7. Set `aria-label`, `data-*` attributes on the `<ul>` (and `<nav>` when present).

`Menu_Item::params()`:

1. If `submenu` is still an array — only when this `Menu_Item` was instantiated standalone, outside a parent `Menu`'s eager coercion — coerce it to a nested `Menu` now. In normal nested usage this step is a no-op because the parent already coerced.
2. Decide shape (link / disclosure / both / mega / divider / heading).
3. Create interactive elements (`<a>` and/or `<button>`).
4. Set ARIA attributes per shape, including `aria-current='page'` and `rel`/`aria-label` augmentations for `target='_blank'`.
5. Apply `is_current` / `is_ancestor` to the `<li>` (`data-current`, `data-ancestor`) and link.

### 4.5 Param propagation

When a parent `Menu` coerces a child item's `submenu` array into a nested `Menu`, the following tree-wide params propagate from parent to child unless the child has already overridden them:

```
pattern, trigger, multi_open, expand_ancestor,
ancestor_taxonomies, match_current, match_ancestor,
toggle_label_format, item_class, menu_class
```

Per-Menu params that do **not** propagate (each nested Menu sets its own):

```
items, source, adapter, aria_label, orientation, landmark, id, level
```

Propagation runs once, at coercion time. Mutating a parent's param after instantiation does not retroactively update children.

**Coercion-time transformations.** Beyond propagation, two values are set automatically when a `Menu_Item` coerces its `submenu` array into a nested `Menu`:

- `level` ← parent's `level + 1`.
- `orientation` ← `'vertical'` regardless of the parent's orientation, unless the submenu array sets it explicitly.

These are boundary overrides — applied once at the coercion seam, not propagated recursively.

### 4.6 ID derivation

`view_index` here is `View`'s per-class render counter — a number that increments each time the class is rendered in a request. Opaque but request-stable.

```
Level-1 Menu:         id := $params['id'] ?? "menu-{view_index}"
Nested Menu:          id := "{parent_li_id}-submenu"
Panel <div> (mega):   id := "{parent_li_id}-panel"
<li>:                 id := "{parent_ul_id}-item-{item_index}"
```

`item_index` is the 0-based position within the parent Menu's items array, computed after empty items are filtered.

A nested Menu does not derive its own ID from `view_index` — it always takes its ID from the parent. The `view_index`-derived form is reserved for level-1 roots so each top-level Menu on a page is uniquely addressable.

The `Panel <div>` formula derives the id of the `<div role='region'>` wrapper for mega-menu items. If `content` is a `Menu` instance, that nested Menu computes its own id via the rules above (level-1 form if standalone; nested form if it was coerced as a submenu somewhere up the chain) — the panel `<div>` id is separate from any Menu id inside it.

Pass `id` explicitly when CSS or JS needs to target a specific menu by name.

### 4.7 Subclassing

Override `params()` like any View. Common extension points:

- Subclass `Menu` to customise item coercion (override the array-coercion loop in `params()`).
- Subclass `Menu_Item` to render extra markup per item (icon, badge, description). Templates branch on the item's params; override the template via `protected static $template` if the structure changes.
- Pass `'item_class' => My_Menu_Item::class` to use a `Menu_Item` subclass without subclassing `Menu`. Propagates to nested Menus (§4.5).

**Constructors must be side-effect-free.** `Menu` and `Menu_Item` (and any subclass via `item_class` / `menu_class`) are instantiated eagerly during the root's tree walk (§4.4 step 3) — including for branches the user never opens. Side effects in a constructor (asset enqueues, hook registration, DB writes, transient lookups) will fire whether the item renders or not. Put that work in `params()`, `before_first()`, or `before()` instead — those run only when the View actually renders.

---

## 5. Markup contract

The exact HTML emitted, by item shape. Whitespace not significant. All `data-*` attributes are part of the contract — JS and CSS depend on them.

**Attribute placement rule:** the structural `data-pattern` / `data-orientation` / `data-trigger` / `data-multi-open` attributes always live on the `<ul>`. The `<nav>` (when present) carries only `id` and `aria-label`. This makes CSS selectors (`[data-orientation='horizontal']`) target the actual flex container regardless of `landmark` setting, and lets JS scan for menu roots by a single attribute on a single element type.

### 5.1 Level-1 disclosure menu, landmark on

```html
<nav class='menu' aria-label='Primary'>
  <ul role='list' id='menu-0'
      data-pattern='disclosure'
      data-orientation='horizontal'
      data-trigger='click'
      data-multi-open='false'>
    <!-- items -->
  </ul>
</nav>
```

The `<nav>` wrapper carries only `class` and `aria-label` — no id. The `<ul>` is the canonical root: it carries the id, role, and structural data attributes. CSS and JS target the `<ul>`; skip-link `href='#menu-0'` jumps focus into it.

`role='list'` on the `<ul>` is deliberate — Safari strips list semantics when `list-style: none` is applied.

### 5.2 Level-1, landmark off

```html
<ul role='list' class='menu' id='menu-0'
    data-pattern='disclosure'
    data-orientation='horizontal'
    data-trigger='click'
    data-multi-open='false'>
  <!-- items -->
</ul>
```

Identical to §5.1's `<ul>` minus the `<nav>` wrapper. Consumer must provide their own landmark (or accept the lack of one).

### 5.3 Nested submenu

```html
<ul role='list' id='menu-0-item-0-submenu' data-orientation='vertical'>
  <!-- items -->
</ul>
```

Nested `<ul>`s carry only `role='list'`, `id`, and `data-orientation`. JS reads `data-pattern` and friends from the root by walking up to the nearest `[data-pattern]` ancestor.

### 5.4 Link-only item

```html
<li class='menu-item' id='menu-0-item-0'
    data-state='static'
    data-current='false'
    data-ancestor='false'>
  <a href='/about/'>About</a>
</li>
```

When current: `data-current='true'` on the `<li>`, `aria-current='page'` on the `<a>`. When ancestor: `data-ancestor='true'`. `aria-current` is omitted entirely when not current — `aria-current='false'` is technically valid but redundant and creates inconsistent SR announcement.

`data-state` has three values: `open`, `closed`, `static`. `static` is set server-side for items with no submenu and never changes — it exists as a CSS hook so selectors can branch on disclosure-bearing vs leaf items.

With `description` set, an additional span appears after the link and the link gains `aria-describedby`:

```html
<li class='menu-item' id='menu-0-item-0'
    data-state='static'
    data-current='false'
    data-ancestor='false'>
  <a href='/about/' aria-describedby='menu-0-item-0-desc'>About</a>
  <span class='menu-item-description' id='menu-0-item-0-desc'>Our company and team</span>
</li>
```

The same `<span>` + `aria-describedby` pattern applies in §5.5–§5.7: the description sits after the interactive element(s) and before any submenu/panel.

### 5.5 Disclosure-only item (button, no parent link)

```html
<li class='menu-item' id='menu-0-item-1'
    data-state='closed'
    data-has-submenu='true'>
  <button type='button'
          aria-expanded='false'
          aria-controls='menu-0-item-1-submenu'>
    <span>Resources</span>
    <span aria-hidden='true' class='chevron'></span>
  </button>
  <ul role='list' id='menu-0-item-1-submenu' data-orientation='vertical'>
    <!-- items -->
  </ul>
</li>
```

The button has no `aria-label` — the visible `<span>Resources</span>` is the accessible name. The chevron `<span>` is `aria-hidden`, so it contributes nothing. `aria-expanded` + `aria-controls` carry the disclosure semantics.

When `expand_ancestor=true` and a descendant is current, the server-rendered `data-state` is `open` instead of `closed`, and the button's `aria-expanded` is `'true'`. JS does not fight this initial state.

### 5.6 Link + disclosure item (both)

```html
<li class='menu-item' id='menu-0-item-2'
    data-state='closed'
    data-has-submenu='true'>
  <a href='/products/'>Products</a>
  <button type='button'
          aria-expanded='false'
          aria-controls='menu-0-item-2-submenu'
          aria-label='Toggle Products submenu'>
    <span aria-hidden='true' class='chevron'></span>
  </button>
  <ul role='list' id='menu-0-item-2-submenu' data-orientation='vertical'>
    <!-- items -->
  </ul>
</li>
```

Two tab stops per parent — `<a>` then `<button>`. The button has no visible text label; its `aria-label` carries the screen-reader text.

### 5.7 Mega-menu panel

```html
<li class='menu-item' id='menu-0-item-3'
    data-state='closed'
    data-has-panel='true'>
  <button type='button'
          aria-expanded='false'
          aria-controls='menu-0-item-3-panel'>
    <span>Featured</span>
    <span aria-hidden='true' class='chevron'></span>
  </button>
  <div role='region'
       id='menu-0-item-3-panel'
       aria-label='Featured'>
    <!-- arbitrary content -->
  </div>
</li>
```

The mega-only button takes its accessible name from the visible `<span>Featured</span>`, same as §5.5 — no `aria-label`.

For the link + mega-panel variant (`url` and `content` both set, no `submenu`), prepend an `<a>` to the markup above — same shape as §5.6 with the `<ul>` swapped for the `<div role='region'>`. In that variant the button has only the chevron (no visible text), so it does receive an `aria-label` via `toggle_label_format`, matching §5.6's pattern.

Active-state matching does not recurse into the panel's content. If the consumer passes a `Menu` instance as `content`, that Menu runs its own active-state at its own level.

### 5.8 Divider

```html
<li role='separator' class='menu-divider'></li>
```

### 5.9 Heading

```html
<li role='presentation' class='menu-heading'>
  <h3>Section Title</h3>
</li>
```

`heading_level` controls `<h2>..<h6>`. Defaults to 3.

**A11y trade-off:** `role='presentation'` on `<li>` removes the item from the list count some screen readers report. The `<h_>` inside still announces correctly. If list-count semantics matter for a given consumer (e.g., a small dense menu where "list of 5 items" is meaningful), render headings outside the list with separate `<ul>` blocks instead.

### 5.10 External-link affordances

For any item with `target='_blank'`:

```html
<li class='menu-item' id='menu-0-item-4'
    data-state='static'
    data-current='false'
    data-ancestor='false'>
  <a href='https://example.com'
     target='_blank'
     rel='noopener noreferrer'
     aria-label='Documentation (opens in new tab)'>Documentation</a>
</li>
```

`rel='noopener noreferrer'` is added unconditionally (defensive: prevents reverse-tabnabbing via `window.opener` and trims the Referer header). The `aria-label` augmentation tells screen-reader users the link opens in a new tab. The visible text is unchanged.

### 5.11 Menu_Drawer

**Status:** Deferred to stage 6.

```html
<button type='button'
        class='menu-drawer-toggle'
        aria-expanded='false'
        aria-controls='drawer-0'
        aria-label='Open menu'>
  <!-- toggle icon -->
</button>
<div id='drawer-0'
     class='menu-drawer'
     data-state='closed'
     data-position='inline-end'
     style='--menu-drawer-breakpoint: 60rem'
     hidden>
  <button type='button'
          class='menu-drawer-close'
          aria-label='Close menu'>
    <!-- close icon -->
  </button>
  <!-- the inner Menu, rendered here -->
</div>
```

When open: `data-state='open'`, `aria-expanded='true'`, `hidden` attribute removed.

---

## 6. Keyboard behaviour

### 6.1 Disclosure pattern (default)

| Key | Context | Behaviour |
|---|---|---|
| Tab | anywhere | Move to next focusable in DOM order |
| Shift+Tab | anywhere | Move to previous |
| Enter / Space | on disclosure button | Toggle the controlled submenu |
| Enter | on link | Follow link (browser default) |
| Escape | inside open submenu | Close nearest ancestor submenu; focus its trigger |
| Escape | outside any open submenu | No-op; do not preventDefault |
| ↓ Arrow | on disclosure button (vertical submenu) | Open submenu + focus first interactive descendant |
| ↑ Arrow | on disclosure button (vertical submenu) | Open submenu + focus last interactive descendant |
| Home / End | inside submenu | Focus first / last interactive descendant |

Arrow keys do *not* navigate between sibling items in the disclosure pattern — Tab is the navigation key. This is the disclosure norm.

### 6.2 Menubar pattern (opt-in)

**Status:** Deferred to stage 9 — `'pattern' => 'menubar'` is accepted by `Menu` but the keyboard pattern below is not yet implemented in JS.

Adds:

| Key | Context | Behaviour |
|---|---|---|
| ← Arrow | on level-1 item | Focus previous sibling |
| → Arrow | on level-1 item | Focus next sibling |
| ↓ Arrow | on level-1 item with submenu | Open submenu + focus first item |
| Tab | anywhere inside menubar | Exit the menubar (focus next focusable outside) |

Menubar is rare. Most consumers want disclosure.

### 6.3 Focus state machine on submenu close

Five close paths; each routes focus deliberately:

| Trigger | Behaviour |
|---|---|
| Escape pressed inside submenu | Close one level. Focus its disclosure trigger. |
| Tab leaves the menu tree (`focusout` where `relatedTarget` is outside) | Close all open submenus. Do not move focus — the user's Tab has already moved it. |
| Click outside the menu | Close all open submenus. Do not move focus. |
| Sibling submenu opens (single-open mode) | Close previously-open sibling. Focus the new trigger (the user just clicked it; this is a no-op in practice, but explicit). |
| Parent submenu closes while child is open | Close all descendants first. Focus the parent's trigger. |

---

## 7. JS contract

One file, enqueued via `Menu::before_first()`. Idempotent — safe to load multiple times.

### 7.1 Initialisation

On `DOMContentLoaded`, scan for `<ul>` elements carrying `[data-pattern]` (the menu root regardless of landmark wrapping). For each, attach delegated event listeners. New menus added later (HTMX, dynamic rendering) initialise via `MutationObserver` on the document body — opt-out via a `data-no-observe` attribute on the root if a consumer needs to manage initialisation themselves.

### 7.2 Attributes JS reads

| Attribute | On | Values |
|---|---|---|
| `data-pattern` | root `<ul>` | `'disclosure'`, `'menubar'` |
| `data-trigger` | root `<ul>` | space-separated subset of `click`, `hover` |
| `data-multi-open` | root `<ul>` | `'true'`, `'false'` |
| `data-orientation` | root `<ul>`, nested `<ul>`s | `'horizontal'`, `'vertical'` |
| `aria-controls` | disclosure button | submenu / panel id |
| `aria-expanded` | disclosure button | `'true'`, `'false'` (also written) |

Nested `<ul>`s do not carry `data-pattern` / `data-trigger` / `data-multi-open` — JS reads these from the nearest `[data-pattern]` ancestor.

### 7.3 Attributes JS writes

| Attribute | On | Values |
|---|---|---|
| `aria-expanded` | disclosure button | `'true'` / `'false'` on toggle |
| `data-state` | parent `<li>` (disclosure-bearing only) | `'open'` / `'closed'` |
| `data-collision` | parent `<li>` | `'start'` / `'end'` when submenu overflows viewport (set on open, recomputed on `ResizeObserver` tick). Reserved for stage 10 — attribute name reserved from stage 2, measurement implementation deferred. |

JS never writes `data-state='static'`. That value is server-rendered for items without a submenu and remains untouched at runtime.

### 7.4 Custom properties JS reads

From the menu root's computed style:

| Property | Default | Effect |
|---|---|---|
| `--menu-hover-in-delay` | `150ms` | Hover-open delay |
| `--menu-hover-out-delay` | `300ms` | Hover-close delay |

### 7.5 Hover gating

**Status:** Deferred to stage 8 — `'trigger' => ['click', 'hover']` is accepted by `Menu` and emitted as `data-trigger='click hover'`, but the JS hover listeners below are not yet attached.

When `data-trigger` contains `hover`, the hover listeners only attach if `matchMedia('(pointer: fine)').matches`. Hybrid devices reporting both fine and coarse pointer get the click handler regardless; hover is purely additive when conditions allow.

### 7.6 Single-open enforcement

When `data-multi-open='false'` (default): opening a submenu closes any sibling submenu at the same level. Closing happens before the open animation starts (no overlap).

### 7.7 Click-outside

A single document-level click listener (attached lazily on first menu open) closes all open submenus when the click target is not inside any menu tree. Removes itself when all menus close.

### 7.8 Initial active state

`expand_ancestor=true` is applied server-side — submenus on the path to current render with `data-state='open'` initially. JS does not re-compute this; it's a paint-time concern.

### 7.9 Link-click behaviour

When a user clicks an `<a>` inside an open submenu:

- **Same-page navigation** (regular link): browser handles the page transition; JS does nothing. The DOM tears down with the navigation.
- **`target='_blank'`**: a new tab opens; the current page stays put. JS does **not** auto-close the submenu — the user may want to keep browsing the menu. A subsequent click elsewhere triggers click-outside (§7.7).
- **Hash links** (`href='#section'`): no navigation; menu stays open. Same reasoning.
- **JS-handled clicks** (consumer added a listener that `preventDefault`s): treated as the hash-link case — no auto-close.

`Menu_Drawer` overrides this when `close_on_navigate=true` (default): any `<a>` click inside the drawer triggers a drawer close. The drawer's click handler runs synchronously in the bubble phase, so the close starts before the browser's navigation kicks in; regular navigation otherwise proceeds normally.

---

## 8. CSS contract

One file, enqueued via `Menu::before_first()`. Layout-only — no project visual decisions.

### 8.1 What the framework CSS owns

```css
[data-pattern]                     { /* layout reset on the root <ul> */ }
[data-orientation='horizontal']    { display: flex; flex-direction: row; }
[data-orientation='vertical']      { display: flex; flex-direction: column; }
[data-state='closed'] > ul,
[data-state='closed'] > [role='region'] { display: none; }
[data-has-submenu] > ul,
[data-has-panel] > [role='region'] { position: absolute; /* base positioning */ }
:focus-within > ul                 { /* no-JS keyboard reach */ }
@media (prefers-reduced-motion: reduce) { /* disable transitions */ }
```

Plus logical-property layout for RTL.

### 8.2 What the framework CSS does NOT own

- Colour, background, border
- Typography (`font-family`, `font-size`, `font-weight`, `line-height`)
- Specific spacing (gap, padding) beyond bland custom-property defaults
- Visual treatment of `[data-current]`, `[data-ancestor]`, `[aria-expanded='true']`, `:hover`
- Chevron icon — projects style `.chevron` themselves
- Animation / transition specifics

### 8.3 Custom properties consumers override

| Property | Default | Effect |
|---|---|---|
| `--menu-gap` | `1rem` | Gap between items |
| `--menu-padding` | `0` | Padding inside items |
| `--menu-hover-in-delay` | `150ms` | Read by JS |
| `--menu-hover-out-delay` | `300ms` | Read by JS |
| `--menu-z-index` | `100` | Stack for open submenus |
| `--menu-drawer-breakpoint` | `60rem` | Drawer hide-above width |

Consumers may add their own; nothing else is reserved.

---

## 9. Item sources

### 9.1 Hand-built array

The base case. Pass arrays of item params:

```php
new Menu([
    'aria_label' => 'Primary',
    'items' => [
        ['text' => 'Home', 'url' => '/'],
        ['text' => 'Products', 'url' => '/products/', 'submenu' => [
            ['text' => 'Widgets', 'url' => '/products/widgets/'],
            ['divider' => true],
            ['text' => 'Coming soon', 'url' => '/products/upcoming/'],
        ]],
        ['heading' => 'External', 'heading_level' => 4],
        ['text' => 'Support', 'url' => 'https://support.example.com', 'target' => '_blank'],
    ],
]);
```

### 9.2 WP nav menu via `Nav_Menu`

```php
new Menu([
    'source'     => 'primary',     // menu ID, slug, or name
    'aria_label' => 'Primary',
]);
```

Internally `Menu` calls `Nav_Menu::get_instance($source)->get_items_tree()`. The two models doing the work:

- **`Nav_Menu`** (Term, taxonomy `nav_menu`). `get_instance` accepts ID / slug / name (extract_id round-trips through `wp_get_nav_menu_object`). `get_items()` returns `Nav_Menu_Item[]` via batched `wp_get_nav_menu_items()`. `get_items_tree()` groups by `menu_item_parent` and returns nested item arrays.

- **`Nav_Menu_Item`** (Post, post type `nav_menu_item`). `prepare_wp_post()` runs `wp_setup_nav_menu_item()` on the single-item path; short-circuits on the batch path. Typed accessors over the decorated `WP_Post`: `get_text()`, `get_url()`, `get_target()`, `get_description()`, `get_object_id()`, `get_object_type()`, `get_menu_parent_id()`, `get_menu_order()`, `get_menu_classes()`. `as_menu_item_params()` is the single source of truth for the Menu_Item params shape — subclass and override to surface ACF / project-specific fields. Archive detection (rewriting `'custom'` URLs that match a CPT archive to `'post_type_archive'`) lives on `Nav_Menu_Item::detect_post_type_archive()`.

External-link affordances (`rel='noopener noreferrer'`, "opens in new tab" SR hint) are applied at render time by `Menu_Item`, not by the source — so they apply equally to hand-built items with `target='_blank'`.

### 9.3 Adapted iterable via `adapter` callable

For any iterable of arbitrary objects:

```php
new Menu([
    'aria_label' => 'Categories',
    'items'      => $terms,            // Term[] | Post[] | anything
    'adapter'    => fn ($entry): array => [
        'text'        => $entry->get_name(),
        'url'         => $entry->get_url(),
        'object_id'   => $entry->get_id(),
        'object_type' => 'taxonomy',
    ],
]);
```

The adapter is responsible for shape conformance. Adapter return values flow through the rest of the pipeline as if they'd been hand-built.

### 9.4 Mixed

`items` can mix raw arrays and pre-instantiated `Menu_Item` objects. Adapter is only applied to entries that aren't already `Menu_Item` instances.

---

## 10. i18n strategy

Lattice carries no text domain. Every user-facing default string is a `Menu`, `Menu_Item`, or `Menu_Drawer` param. Consumers wrap their override at the call site:

```php
new Menu([
    'aria_label'           => __('Primary navigation', 'my-plugin'),
    'toggle_label_format'  => __('Toggle %s submenu', 'my-plugin'),
]);

new Menu_Drawer([
    'menu'              => $menu,
    'aria_label_open'   => __('Open menu', 'my-plugin'),
    'aria_label_close'  => __('Close menu', 'my-plugin'),
]);
```

Defaults:

| Param | Default |
|---|---|
| `Menu::aria_label` | `'Menu'` |
| `Menu::toggle_label_format` | `'Toggle %s submenu'` |
| `Menu_Drawer::aria_label_open` | `'Open menu'` |
| `Menu_Drawer::aria_label_close` | `'Close menu'` |

The "opens in new tab" SR-hint format for `target='_blank'` links is also a `Menu_Item` param: `external_label_format` (default `'%s (opens in new tab)'`, `%s` receives the original label).

This keeps lattice translation-free and avoids coupling the framework to any consumer's `.po`/`.mo` files.

---

## 11. Configuration index

Flat reference of every param across all classes.

### `Menu`

```
items, source, adapter, aria_label, pattern, orientation, trigger,
multi_open, expand_ancestor, ancestor_taxonomies, match_current,
match_ancestor, landmark, toggle_label_format, id, level, item_class
```

### `Menu_Item`

```
text, url, submenu, content, divider, heading, heading_level,
is_current, is_ancestor, object_id, object_type, description,
target, level, menu_class, wp_post, external_label_format
```

Plus inherited `Component` attribute params (`classes`, `attr`, `styles`, …) targeting the `<li>`.

### `Menu_Drawer`

```
menu, aria_label_open, aria_label_close, position, breakpoint,
trap_focus, lock_scroll, close_on_navigate, id
```

---

## 12. Implementation staging

Each stage independently shippable; later stages do not block earlier ones from being used.

| # | Stage | Status | Scope |
|---|-------|--------|-------|
| 1 | Core PHP | **Done** | `Menu` + `Menu_Item` (link, disclosure, both, divider, heading shapes). Hand-built items, templates. |
| 2 | CSS + JS | **Done** | Structural CSS; JS for click toggle, Escape, click-outside, focus management. Disclosure pattern. |
| 3 | Active state | **Done** | `Menu_Active_State` resolver. `aria-current` / `data-current` / `data-ancestor` emit. |
| 4 | `Nav_Menu` + `Nav_Menu_Item` models | **Done** | `prepare_wp_post` hook on `Has_WP_Post`, batched + single-item hydration, typed accessors, archive detection, tree assembly. `source` param wires up. |
| 7 | Mega-menu (`content` slot) | **Done** | Implemented alongside stage 1 — `link_mega` / `mega` shapes, `build_panel()`, `<div role='region'>` rendering. (Built ahead of original schedule.) |
| 5 | Custom adapter | Deferred | `adapter` callable on `Menu`. Param accepted; mapping step in `params()` is a no-op stub. |
| 6 | `Menu_Drawer` | Deferred | Drawer chrome, focus trap, scroll lock, Escape, click-outside. |
| 8 | Hover trigger | Deferred | Opt-in hover open with `(pointer: fine)` gating and CSS-variable delays. |
| 9 | Menubar pattern | Deferred | Opt-in arrow-key navigation between top-level items. |
| 10 | Collision flipping | Deferred | Submenu overflow → `data-collision`. Attribute name reserved from stage 2; measurement deferred. |
