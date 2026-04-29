# Spec: Layout System

**Status:** Draft
**Date:** 2026-04-08

---

## Objective

Establish a clean, minimal architecture for running Lattice as the primary rendering system within WordPress.

- WordPress = **content + request context provider**
- Lattice = **application + rendering layer**
- Theme = **thin adapter** (required by WordPress, contains no logic)

---

## Rendering Flow

```
WordPress Request
    |
Theme (single entry point)
    |
App::render() (per active App)
    |
Request_Resolver
    |-- resolve_layout() → Layout subclass (or default)
    |-- resolve_page()   → Page_View instance
    |
Layout (View, resolved)
    |-- Header (default)
    |-- Page_View (resolved body)
    |-- Footer (default)
    |-- Modals (default)
    |
HTML Response
```

---

## Theme

Minimal valid WordPress theme. No templates, markup, or logic.

### `functions.php`

```php
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
});

add_filter('template_include', function ($template) {
    return get_template_directory() . '/index.php';
}, 99);
```

### `index.php`

```php
<?php

Digitalis\App::render();
```

---

## App

`App` extends `Factory` (changed from `Singleton`). Each plugin defines its own App subclass. All Apps share a single cache group (`protected static $cache_group = self::class`) so the base `Digitalis\App` can discover all active plugin Apps via the Factory instance registry.

> **Breaking change:** Existing plugin App subclasses extend `Singleton` via `App`. The change to `Factory` alters `get_instance()` behaviour (accepts `$data`, uses `$cache_property`). Existing subclasses should be reviewed to ensure compatibility.

### `render()`

Static method on the base `App`. Lets each registered App participate via `render_app()`, then resolves and renders the page.

```php
// Base Digitalis\App
class App extends Factory {

    protected static $caclattice/docs/specs/LAYOUT_SYSTEM.mdhe_group = self::class;

    public static function render () {
        $apps = static::get_apps();
        if (!$apps) return;

        foreach ($apps as $app) {
            $app->render_app();
        }

        $resolver     = Request_Resolver::get_instance();
        $layout_class = $resolver->resolve_layout() ?? Layout::class;
        $page         = $resolver->resolve_page();
        $overrides    = $page ? $page::get_layout_overrides() : [];

        echo new $layout_class(['body' => $page] + $overrides);
    }

    public function render_app () {
        // Override in plugin App subclasses to participate in rendering
        // (e.g. enqueue assets, register data, set up context)
    }

    public static function get_apps () {
        return static::get_group_instances();
    }
}
```

### Theme call

`Digitalis\App::render()` is called from the theme. The base class coordinates — no plugin-specific knowledge needed.

### Multi-plugin

Multiple Lattice plugins can be active simultaneously. All register into the shared `App` cache group during auto-instantiation. The rendering system discovers them via `App::get_apps()`. Each App's `render_app()` is called before page resolution, allowing all plugins to set up context. Page_Views from all plugins participate in resolution — specificity determines the winner.

---

## Resolvable

A trait used by both `Layout` and `Page_View`. Declares the static routing properties used by `Request_Resolver` and provides auto-specificity calculation.

```php
trait Resolvable {

    protected static $context   = null;
    protected static $post_type = null;
    protected static $taxonomy  = null;
    protected static $term      = null;
    protected static $priority  = null;

    public static function get_context ()   { return static::$context;   }
    public static function get_post_type () { return static::$post_type; }
    public static function get_taxonomy ()  { return static::$taxonomy;  }
    public static function get_term ()      { return static::$term;      }
    public static function get_priority ()  { return static::$priority;  }

    public static $context_weights = [
        'archive'    => 10,
        'single'     => 20,
        'home'       => 20,
        'author'     => 30,
        'taxonomy'   => 30,
        'page'       => 30,
        'search'     => 30,
        'front_page' => 40,
        '404'        => 40,
    ];

    public static function get_specificity ($request_contexts = []) {

        if (!is_null(static::$priority)) return static::$priority;

        $s = 0;

        if (static::$context) {
            $matched = array_intersect((array) static::$context, $request_contexts);
            $s += $matched ? max(array_map(fn ($c) => static::$context_weights[$c] ?? 5, $matched)) : 0;
        }

        if (static::$post_type) $s += 10;
        if (static::$taxonomy)  $s += 10;
        if (static::$term)      $s += 10;

        return $s;

    }

}
```

- `$priority = null` (default) — specificity is auto-calculated from context weight + filters
- `$priority = <int>` — absolute override, bypasses auto-specificity
- All properties accept strings or arrays: `$context = ['home', 'archive']`, `$taxonomy = ['category', 'post_tag']`
- Specificity uses the best *matched* context weight, not all declared contexts

---

## Layout

A `View` using the `Resolvable` trait. Owns the page shell. Resolved by `Request_Resolver::resolve_layout()` using the same context/specificity/condition pattern as Page_View. The base `Layout` class serves as the default (no context/post_type = specificity 0); subclasses override for different shell structures.

Layout inherits routing properties via `Resolvable` (`$context`, `$post_type`, `$taxonomy`, `$term`, `$priority`) and declares shell defaults. Shell parts are View class strings — `params()` instantiates any class string params before render.

```php
class Layout extends View {

    use Resolvable;

    protected static $defaults = [
        'header' => Header::class,
        'body'   => null,
        'footer' => Footer::class,
        'modals' => Modals::class,
    ];

    public function params (&$p) {
        foreach ($p as &$value) {
            if (is_string($value) && class_exists($value)) {
                $value = new $value();
            }
        }
        parent::params($p);
    }

    public function view (): void { ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head><?php wp_head(); ?></head>
        <body <?php body_class(); ?>>
            <?php if ($this['header']) echo $this['header']; ?>
            <?php if ($this['body'])   echo $this['body']; ?>
            <?php if ($this['footer']) echo $this['footer']; ?>
            <?php if ($this['modals']) echo $this['modals']; ?>
            <?php wp_footer(); ?>
        </body>
        </html>
    <?php }
}
```

### Shell part resolution

Shell parts are class strings in `$defaults`. `params()` instantiates them before render. Page_View `$layout` overrides can swap or suppress:

| Value | Result |
|-------|--------|
| `Header::class` (default) | Instantiated in `params()`, rendered via `__toString()` |
| `Alt_Header::class` (swap) | Instantiated in `params()` |
| `false` (suppress) | Falsy, skipped |

The `body` param is a Page_View instance from the resolver — rendered via `__toString()`.

### Multiple layouts

Layout subclasses declare context/condition like Page_Views. The resolver picks the best match by specificity:

```php
// dashboard-layout.layout.php
class Dashboard_Layout extends Layout {
    protected static $context  = 'page';

    protected static $defaults = [
        'sidebar' => Sidebar::class,
        'body'    => null,
    ];

    public function condition (): bool {
        return current_user_can('manage_options');
    }
}
```

If no Layout subclass matches, the base `Layout` is used as the default.

---

## Page_View

A `View`. Owns the body content. Declares its request context via static properties. Only Page_Views that need to override shell parts declare `$layout`.

### Autoloader suffix

`.page-view.php`

### Base class

```php
abstract class Page_View extends View {

    use Resolvable;

    protected static $layout = [];

    public static function get_layout_overrides () {
        return static::$layout;
    }
}
```

### Usage

```php
// product-page.page-view.php
class Product_Page extends Page_View {
    protected static $context   = 'single';
    protected static $post_type = 'product';

    public function view(): void {
        // render product page body
    }
}
```

```php
// not-found.page-view.php
class Not_Found extends Page_View {
    protected static $context = '404';

    public function view (): void {
        // render 404 page
    }
}
```

```php
// fullscreen-page.page-view.php
class Fullscreen_Page extends Page_View {
    protected static $context = 'page';

    protected static $layout = [
        'header' => false,
        'footer' => false,
    ];

    public function condition (): bool {
        // narrow condition checked after context/post_type pre-filter
        return get_field('layout', get_the_ID()) === 'fullscreen';
    }

    public function view (): void {
        // render fullscreen content
    }
}
```

### Specificity & Priority

Resolution order is determined by `Resolvable::get_specificity($request_contexts)`:

| Component | Value |
|-----------|-------|
| Context weight (from `$context_weights`) | 10–40 depending on matched context |
| `$post_type` set | +10 |
| `$taxonomy` set | +10 |
| `$term` set | +10 |

Context weights: `archive` 10, `single`/`home` 20, `author`/`taxonomy`/`page`/`search` 30, `front_page`/`404` 40. When a view declares multiple contexts (e.g. `['home', 'archive']`), specificity uses the weight of the best *matched* context on the current request.

`$priority` defaults to `null` (auto-specificity). Setting `$priority` to any integer overrides auto-specificity entirely, acting as an absolute value. Use it for tiebreaking or deliberate overrides (e.g. condition-narrowed views that share the same config as another candidate).

---

## Request_Resolver

A `Singleton`. Single routing authority for both Layout and Page_View resolution. Discovers candidates via `View::get_loaded_views()`, pre-filters by static context/post_type/taxonomy/term, sorts by specificity, instantiates and returns first match.

Two public methods delegate to a shared `resolve()`:

- **`resolve_layout()`** — returns a Layout subclass name (string), or `null` for the default `Layout`
- **`resolve_page()`** — returns a Page_View instance, or `null`

```php
class Request_Resolver extends Singleton {

    public function resolve_layout () {
        return $this->resolve(Layout::class, true);
    }

    public function resolve_page () {
        return $this->resolve(Page_View::class);
    }

    protected function resolve (string $base_class, bool $return_class = false) {
        $contexts   = $this->get_current_context();
        $queried    = get_queried_object();
        $is_tax     = is_tax() || is_category() || is_tag();
        $taxonomy   = $is_tax && $queried ? $queried->taxonomy : null;
        $term       = $is_tax && $queried ? $queried->slug     : null;
        $post_types = $is_tax && $taxonomy
            ? get_taxonomy($taxonomy)->object_type
            : [(string) get_post_type(get_queried_object_id())];

        $candidates = array_filter(
            View::get_loaded_views(),
            fn ($class) => is_subclass_of($class, $base_class) && ($class !== $base_class)
                && (!$class::get_context()   || array_intersect((array) $class::get_context(), $contexts))
                && (!$class::get_post_type() || array_intersect((array) $class::get_post_type(), $post_types))
                && (!$class::get_taxonomy()  || in_array($taxonomy, (array) $class::get_taxonomy()))
                && (!$class::get_term()      || in_array($term, (array) $class::get_term()))
        );

        usort($candidates, fn ($a, $b) => $b::get_specificity($contexts) <=> $a::get_specificity($contexts));

        foreach ($candidates as $class) {
            $view = new $class();
            if ($view->condition()) return $return_class ? $class : $view;
        }

        return null;
    }

    protected function get_current_context () {
        $contexts = [];

        $is_tax = is_tax() || is_category() || is_tag();

        if (is_404())        $contexts[] = '404';
        if (is_search())     $contexts[] = 'search';
        if (is_front_page()) $contexts[] = 'front_page';
        if (is_home())       $contexts[] = 'home';
        if (is_page())       $contexts[] = 'page';
        if (is_singular())   $contexts[] = 'single';
        if ($is_tax)         $contexts[] = 'taxonomy';
        if (is_author())     $contexts[] = 'author';
        if (is_archive())    $contexts[] = 'archive';

        return $contexts;
    }
}
```

### Resolution flow

1. `View::get_loaded_views()` returns all loaded View class names (populated via `static_init()` during autoload)
2. `get_current_context()` returns all matching WordPress contexts as an array (e.g. `['front_page', 'home', 'archive']`)
3. Filter to target subclass (Layout or Page_View) matching current contexts, post type, taxonomy, and term
4. Sort by `get_specificity($contexts)` descending — uses best matched context weight
5. Instantiate each candidate, call `condition()` — first truthy match wins
6. `resolve_layout()`: no match → `null` (App falls back to base `Layout`)
7. `resolve_page()`: no match → `null` (Layout renders with null body — fallback content)

### Base `condition()`

Both Layout and Page_View inherit `condition()` from View, which returns `true` by default — context, post_type, taxonomy, and term are already matched by the static pre-filter. Subclasses override for narrow matching (custom fields, capabilities, etc.).

### Previews

`is_preview()` is not a separate context. Preview requests pass through `is_singular()` → `'single'`, so they resolve to the same Page_View as the live page. This is intentional — previews should reflect live rendering.

### Taxonomy archive post_type resolution

On taxonomy archives, `get_post_type()` is unreliable (the queried object is a term, not a post). The resolver uses `get_taxonomy($taxonomy)->object_type` to determine the post types associated with the taxonomy. This allows a Page_View with `$post_type = 'post'` to match `/category/fiction/` as a fallback.

---

## Content boundary

- **Gutenberg** owns post *content* — accessed via the relevant `Post` model's `get_content()` method (canonical accessor)
- **Lattice** owns everything around it
- ACF Blocks map to Views via `ACF_Block`
- ACF Flexible Content and `ACF_Block` can share the same underlying View classes, with options narrowed per context

---

## Styling & Assets

- Lattice owns all CSS/SCSS, design tokens, component styles
- Lattice enqueues assets via WordPress hooks
- Theme does not own styles
- Single compiled bundle to start; per-component loading is a future optimisation

---

## Error Handling

- Exceptions bubble to `App::render()`
- Layout and Resolver do not catch exceptions
- App renders a fallback Layout on failure

---

## Scope

Frontend rendering only. Admin rendering may be built at a later date. REST requests are handled via the `Route` class.

---

## Extensibility

No new mechanisms. Existing Lattice patterns handle everything:

- **Override a Page_View** — add a higher-priority `.page-view.php` in the project
- **Override Layout** — add a higher-priority Layout subclass with context/condition matching
- **Override Resolver** — subclass via normal Singleton resolution
- **Override Header/Footer** — subclass the default Views

---

## Non-Goals

- No page builder
- No layout logic in WordPress editor
- No theme templates — WordPress page templates survive as metadata only; Lattice does not match against them at the framework level
- No duplication between theme and Lattice
