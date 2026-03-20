# Digitalis Framework Documentation

Quick navigation to all framework documentation.

---

## Quick Start

| Document | Use When |
|----------|----------|
| [../AGENTS.md](../AGENTS.md) | Starting an AI-assisted session - full framework primer |
| [CONTRIBUTING.md](./CONTRIBUTING.md) | Making commits - emoji selection guide |
| [ANTIPATTERNS.md](./ANTIPATTERNS.md) | Patterns that look right but aren't - read before writing framework code |
| [CONVENTIONS.md](./CONVENTIONS.md) | Preferred syntax where multiple valid forms exist |
| [CHEATSHEET.md](./CHEATSHEET.md) | Writing code - copy-paste patterns |
| [MODELS.md](./MODELS.md) | Model methods - Post, User, Term, Order |
| [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md) | Finding the right component/field |
| [HOOKS.md](./HOOKS.md) | WordPress hooks - actions, filters, Features |
| [ADMIN.md](./ADMIN.md) | Admin pages, tables, meta boxes, ACF |
| [UTILITIES.md](./UTILITIES.md) | Query builders, DI, caching, helpers |
| [AUTOLOADER.md](./AUTOLOADER.md) | Creating new files - naming rules |

---

## Core Documentation

### [ARCHITECTURE.md](./ARCHITECTURE.md)
System overview, directory structure, design patterns, data flow. Start here to understand the framework.

### [AUTOLOADER.md](./AUTOLOADER.md)
File naming conventions, inheritance-based load order, conditional loading, auto-instantiation.

**Key patterns:**
- `name.parent.php` - Class extends parent
- `name.abstract.php` - Abstract class
- `name.trait.php` - Trait
- `_folder/` - Skipped by autoloader
- `~folder/` - Conditional (plugin-dependent)

### [DEPENDENCY_INJECTION.md](./DEPENDENCY_INJECTION.md)
How class names in defaults resolve to model instances. Covers View injection, Route injection, Admin Table injection.

### [CLASS_RESOLUTION.md](./CLASS_RESOLUTION.md)
Automatic model class resolution - how `Post::get_instance($id)` returns the correct subclass (Project, Document, etc.).

### [MODELS.md](./MODELS.md)
Quick reference for all model methods: Post, User, Term, Order, Customer. Includes getters, setters, ACF fields, meta data, and extension patterns.

### [HOOKS.md](./HOOKS.md)
WordPress hooks system: `Has_WP_Hooks` trait, Feature and Integration classes, hook naming conventions, framework hooks reference, and common patterns.

### [ADMIN.md](./ADMIN.md)
Admin area: Admin_Page, Admin_Sub_Page, Posts_Table, Users_Table, Meta_Box, ACF field groups, ACF blocks, ACF options pages.

---

## View System

### [VIEW_SYSTEM.md](./VIEW_SYSTEM.md)
Complete View system documentation: static properties, inheritance, rendering, lifecycle hooks, validation, Components, Elements, Attributes.

**Key classes:** `View` → `Component` → `Field`

### [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md)
Reference for all 30+ built-in views:
- **Core:** Debug, Archive, Iterator_UI, Query_Filters
- **Components:** Form, Table, Link, Menu, HTMX
- **Fields:** Input, Select, Checkbox, Radio, Date_Picker, etc.

---

## Analysis Documents

Design rationale and trade-off analysis. Human reference only — not required for day-to-day coding. Actionable antipatterns from these have been extracted to [ANTIPATTERNS.md](./ANTIPATTERNS.md).

| Document | Topic |
|----------|-------|
| [analysis/CLASS_RESOLUTION_ANALYSIS.md](./analysis/CLASS_RESOLUTION_ANALYSIS.md) | Model resolution pros/cons/pitfalls |
| [analysis/VIEW_SYSTEM_ANALYSIS.md](./analysis/VIEW_SYSTEM_ANALYSIS.md) | View system design analysis |
| [analysis/QUERY_SYSTEM_ANALYSIS.md](./analysis/QUERY_SYSTEM_ANALYSIS.md) | Query_Vars, Query_Manager & Query_Profile analysis |

---

## Quick Links by Task

### Creating Models
- Method reference: [MODELS.md](./MODELS.md)
- File naming: [AUTOLOADER.md](./AUTOLOADER.md)
- Class resolution: [CLASS_RESOLUTION.md](./CLASS_RESOLUTION.md)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#models)

### Creating Views
- Full docs: [VIEW_SYSTEM.md](./VIEW_SYSTEM.md)
- Built-in views: [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#views)

### Creating Forms
- Field reference: [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md#fields)
- Form component: [BUILTIN_VIEWS.md](./BUILTIN_VIEWS.md#form)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#forms)

### Features & Hooks
- Hooks system: [HOOKS.md](./HOOKS.md)
- Feature class: [HOOKS.md](./HOOKS.md#feature-class)
- Integration class: [HOOKS.md](./HOOKS.md#integration-class)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#hooks)

### Admin & ACF
- Admin pages: [ADMIN.md](./ADMIN.md#admin-pages)
- Table columns: [ADMIN.md](./ADMIN.md#posts-table)
- Meta boxes: [ADMIN.md](./ADMIN.md#meta-boxes)
- ACF field groups: [ADMIN.md](./ADMIN.md#acf-field-groups)
- ACF options: [ADMIN.md](./ADMIN.md#acf-options-pages)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#admin)

### Query Building
- Query_Vars: [UTILITIES.md](./UTILITIES.md#query_vars)
- Deep analysis: [analysis/QUERY_SYSTEM_ANALYSIS.md](./analysis/QUERY_SYSTEM_ANALYSIS.md)
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#queries)

### WooCommerce
- Account pages: [CHEATSHEET.md](./CHEATSHEET.md#woocommerce)
- Order model: [ARCHITECTURE.md](./ARCHITECTURE.md#woocommerce-integration)

### Iterators & Batch Processing
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#iterators-batch-processing)

### Shortcodes
- Quick patterns: [CHEATSHEET.md](./CHEATSHEET.md#shortcodes)

### Database (Custom Tables)
- Schema, Migration, Table: [CHEATSHEET.md](./CHEATSHEET.md#database)

---

## File Locations

```
framework/
├── AGENTS.md              # AI agent primer (start here for LLM sessions)
├── CLAUDE.md              # Claude Code entry point → AGENTS.md
├── include/
│   ├── objects/           # Core abstracts (Model, View, Factory, Route, Shortcode, …)
│   ├── patterns/          # Design patterns (Singleton, Factory)
│   ├── traits/            # Reusable traits
│   ├── wordpress/         # WP models (Post, User, Term)
│   ├── woocommerce/       # WC models (Order, Customer)
│   ├── views/             # Built-in views
│   │   ├── components/
│   │   └── fields/
│   ├── admin/             # Admin classes
│   ├── iterators/         # Batch processing
│   ├── acf/               # ACF block + relationships
│   └── db/                # Database schema + migrations
├── templates/             # PHP templates
└── docs/
    ├── analysis/          # Human-only design rationale (not needed for coding)
    └── *.md               # All other docs
```

```
your-plugin/
├── include/
│   ├── models/        # Your domain models
│   ├── post-types/    # CPT registrations
│   ├── views/         # Custom views
│   ├── features/      # Hook-based features
│   └── woocommerce/   # WC customizations
└── templates/         # Your templates
```
