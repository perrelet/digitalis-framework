# LatticeWP

A structuring layer for WordPress plugin development.

WordPress is an event system with a database attached. It is not a framework. LatticeWP gives it the shape of one — consistent entry points, typed abstractions over core entities, and a predictable execution model.

This is not a distance layer. You still write hooks, query posts, and work with the admin. LatticeWP makes the structure of that work explicit.

---

## What it provides

**Entity models.** `Post`, `User`, `Term`, `Order` wrap WordPress objects. One instance per ID, resolved automatically to the most specific subclass. You work with typed objects, not arrays.

**A rendering system.** `View` classes separate markup from logic. Parameters are declared, validated, and optionally injected. Templates or inline methods — the rendering path is explicit either way.

**Hook registration.** `Feature` classes declare hooks through a single method. No scattered `add_action` calls across files. The hook surface of a feature is readable in one place.

**REST routes, shortcodes, ACF blocks.** Each is a class with declared properties. One file, one responsibility, auto-instantiated.

**An autoloader.** File names encode inheritance. `project.post.php` defines `Project extends Post`. Drop a file in the right directory; it loads in the correct order.

---

## Structure

```
your-plugin/
├── plugin.php
└── include/
    ├── models/         # Post / User / Term subclasses
    ├── post-types/     # CPT and taxonomy registration
    ├── views/          # View classes and templates
    ├── features/       # Hook-based behaviour
    ├── routes/         # REST endpoints
    └── admin/          # Admin pages and list tables
```

---

## Core patterns

| Class | Extends | Purpose |
|-------|---------|---------|
| `Post`, `User`, `Term` | `WP_Model` | WordPress entity wrappers |
| `Order`, `Customer` | `Post`, `User` | WooCommerce entity wrappers |
| `View` | — | Renderable component |
| `Component`, `Field` | `View` | UI components and form fields |
| `Feature` | — | Declares and registers WordPress hooks |
| `Integration` | `Singleton` | Conditional feature (checks plugin presence) |
| `Route` | `Factory` | REST API endpoint |
| `Shortcode` | `Factory` | WordPress shortcode |
| `ACF_Block` | `Factory` | Gutenberg block via ACF |
| `Layout` | `View` | Page shell (header/body/footer/modals) |
| `Page_View` | `View` | Request-specific body content |
| `Request_Resolver` | `Singleton` | Layout/Page_View resolution from request context |
| `Post_Type`, `Taxonomy` | `Singleton` | Registration classes |
| `Admin_Page` | `Factory` | Admin menu page |
| `Posts_Table`, `Users_Table` | `Screen_Table` | Admin list table columns |
| `Post_Iterator`, `User_Iterator` | `Iterator` | Batch processing with admin UI |
| `Query_Vars` | — | Composable `WP_Query` argument builder |
| `Query_Profile` | `Factory` | Modify queries at dispatch time |
| `Schema`, `Migration` | — | Custom database tables |

Auto-instantiated classes (Feature, Route, Post_Type, etc.) require no manual bootstrap — the autoloader handles it.

---

## Getting started

Require the framework and extend `App`:

```php
namespace My_Plugin;

use Digitalis\App;

require_once WP_PLUGIN_DIR . '/digitalis-co/framework/load.php';

class Plugin extends App {

    protected static $dir  = __DIR__;
    protected static $name = 'my-plugin';

    public function boot(): void {
        $this->load(__DIR__ . '/include');
    }

}
```

Everything in `include/` loads automatically from that point. File naming drives load order and instantiation — see [`docs/AUTOLOADER.md`](./docs/AUTOLOADER.md).

---

## Documentation

| File | Use when |
|------|----------|
| [`AGENTS.md`](./AGENTS.md) | Starting an AI-assisted session |
| [`docs/CHEATSHEET.md`](./docs/CHEATSHEET.md) | Writing code — copy-paste patterns |
| [`docs/ANTIPATTERNS.md`](./docs/ANTIPATTERNS.md) | Things that look right but aren't |
| [`docs/MODELS.md`](./docs/MODELS.md) | Post, User, Term, Order method reference |
| [`docs/VIEW_SYSTEM.md`](./docs/VIEW_SYSTEM.md) | View system in full |
| [`docs/HOOKS.md`](./docs/HOOKS.md) | Feature, Integration, hook patterns |
| [`docs/ADMIN.md`](./docs/ADMIN.md) | Admin pages, tables, ACF |
| [`docs/UTILITIES.md`](./docs/UTILITIES.md) | Query_Vars, DI, batch processing |
| [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) | Architecture, layout system, design patterns |
| [`docs/README.md`](./docs/README.md) | Full documentation index |
