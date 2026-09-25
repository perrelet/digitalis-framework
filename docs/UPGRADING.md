# Upgrading

What a consumer plugin meets when it moves onto a new framework version. Each section is a checklist: run the site with `WP_DEBUG` (or `LATTICE_STRICT`) on, read every `Strict_Violation` as an instruction, fix, reload. `define('LATTICE_STRICT', false)` turns the checks off while you work through them; do not ship that.

## v1: the first strict boot

Strict mode (`LATTICE_STRICT`, default `WP_DEBUG`) throws `Strict_Violation` at documented mistakes. Boot audits run during the autoload walk and list every problem in one exception; render checks throw at the offending call. Messages read `Class (dir/file.php): problem. Fix.`

### View lifecycle

| Violation | Mechanical fix |
|---|---|
| `params()` overrides `X::params()` without calling it | Add `parent::params($p)`: last if the parent reads params you set, first if you use what it builds. If the parent's work is unwanted, extend its parent instead. |
| `static_init()` never calls `parent::static_init()` | Add `parent::static_init()` as the first line of the override the message names |
| `$required` lists a key whose default is `''`, `0`, `[]` or another non-null scalar | Default the key to `null`, or drop it from `$required` and test emptiness in `condition()` |
| Static `$context` / `$post_type` / `$taxonomy` / `$term` / `$priority` on a view that is not a `Layout` or `Page_View` | Extend `Page_View`, or rename the property |
| `__construct()` never calls `parent::__construct()` | Add `parent::__construct($params)` as the first line |
| Output emitted during `pre_validate()` / `params()` / `validate()` | Move the markup to `view()`, a template or `before()` / `after()`; render sub-views to a string with `(string) new Sub_View([...])` |
| Output buffer left open, or closed without being opened, during that phase | Balance `ob_start()` inside `params()` |

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| courses | 2 | `Landing_Page_Buttons` and `Search_Item` override `params()` without the parent call |
| study-hub | 1 | `Price_Boxes` skips `Plan_View::params()` |
| d-pace | 13 | `$required` keys defaulting to `''` (one to `'#'`) across ten views |
| eventropy, mycelium, somm | 0 | |

The `Search_Item` and `Price_Boxes` fixes change rendered output because the parent's `params()` starts to apply. Check the page, and extend the grandparent instead if the parent's work is not wanted.

### Routes

| Violation | Mechanical fix |
|---|---|
| `$method` or `$methods` property | `$definition = ['methods' => 'POST']` |
| `$version` property | Put it in `$namespace`: `'my-plugin/v1'` |
| `permission_callback()` method | Rename it `permission(WP_REST_Request $request)` |
| `get_params()`, `get_rest_args()` or `register_api_routes()` (the removed `Deprecated_Route` API) | Move the argument map to `protected $args`, or override public `get_args()` when computed; `$definition` for the rest of `register_rest_route`'s arguments |
| `$rest_args` or `$html_prefix` property | Delete `$rest_args` (see above). HTML is served at `wp-html/` once the app loads the `lattice/html-rest-api` feature; `$format = 'html'` then points `get_url()` there |
| `$this->get_param()` (throws at the call) | Read `$request->get_param()` from the request handed to `permission()` and the handler |

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| study-hub | 2 routes | `Plan_Page_Route` and `Subscribe_Route` define `get_params()`, so their required arguments were never registered; `Plan_Page_Route` also carries `$rest_args` and `$html_prefix` and needs the html feature loaded |
| courses | 1 | `Products_Route` declares a dead `$rest_args = []` |
| d-pace, eventropy, mycelium, somm | 0 | |

Notices (`WP_DEBUG`, `_doing_it_wrong`, nothing throws):

| Notice | Fix |
|---|---|
| A non-GET request served through the default methods `['GET', 'POST']` | Declare `$definition['methods']`; 1.0 makes the default `GET` only |
| A non-GET request served with the base `permission()` | Override `permission()`; anonymous state-changing routes also set `$require_nonce = true` |

Zero notices on measured traffic: the five POST callers (mycelium banners, org moderation, registration, autofill and its upload) all target routes that declare `POST` and override `permission()`, and mycelium's abstract base already sets `$require_nonce = true` for all its routes. One runtime-dependent case the static count cannot see: ACF's ajax form posts to the current URL, so a mycelium edit route served as the top-level page receives that POST through the default methods (a notice today, a 404 under a GET-only default).

### Queries

| Violation | Mechanical fix |
|---|---|
| A concrete `Query_Profile` subclass is declared but never registered (checked at `wp_loaded`) | Put it in a directory the app walks, or call `Its_Class::get_instance()` at boot |
| `execute()` on a `WP_Query` whose stamp already carries `applied` (a second `execute()`, or `query_vars` copied from an executed query) | `$qv->remove('digitalis')` after copying the vars, or build a fresh query with `make_query()` |
| `_profiles` / `_suppress` set on the main query | Gate ambient and baseline profiles in `condition()`; select or suppress on programmatic queries through `execute()` |
| `find_meta_query()` / `find_tax_query()` (throw at the call) | `find_meta_query_path()` then `get_meta_block($path)`, or `upsert_meta_query()` |

Behaviour change behind the second check: the `applied` stamp is now persisted, so a re-executed or stamp-copied query skips profiles instead of re-applying them and duplicating `meta_query` / `tax_query` blocks. Any `posts_clauses` mod a profile registered is not re-registered on the skipped run.

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| mycelium | 1 | `Result::query()` copies the executed main query's vars, stamp included, on archive pages. Without `remove('digitalis')` the `Stories_Profile` star-first ordering (a `posts_clauses` mod) silently disappears on story archives, so the consumer change must ship with the framework bump |
| eventropy | 1 | `Event::query()` copies the executed main query's vars the same way; its profile is vars-only, so only the strict throw is visible |
| courses, d-pace, somm, study-hub | 0 | |

### Models

| Violation | Mechanical fix |
|---|---|
| Two unrelated classes validate the same id at equal specificity (thrown at resolution) | Narrow one `validate_id()` or add a distinguishing static. A subclass already beats its ancestor and a consumer model beats the framework's, so `class Page extends \Digitalis\Post` alongside `Lattice\Page`, or `class Event extends \Eventropy\Event`, resolves without change |
| `validate_id()` re-enters resolution (calls `get_instance()` on its own family) | Cheap checks only in `validate_id()`; resolve models after validation |
| `static_init()` override never calls `parent::static_init()` (checked at boot) | Call `parent::static_init()` first |
| `$post->get_type()` (throws at the call) | `get_post_type()` |
| `save()` on an existing post inside `wp_after_insert_post` (also nested inserts, scheduled publishes, auto-drafts, REST saves) | `wp_update_post()` with the one field, or `update_meta()`; create new posts freely |

Behaviour change behind the first check: resolution is now deterministic at equal specificity (descendant over ancestor, consumer over framework) instead of last-registered; the live consumer pairs measured all keep today's result.

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| mycelium | 2 | `Mycelium\User` ties with the vendored `Eventropy\User` on every base `User` resolution (`get_author()`, `User::inst()`); make it `extends \Eventropy\User`. `Org` after_insert saves the inserted post (`save(['post_status' => 'pending_review'])`); use `wp_update_post()` with the one field |
| eventropy (and somm through it) | 1 | `Room` and `Ticket` order items both validate a room line item (`Ticket` has no narrowing, `Order_Item` has no specificity), so room items resolve as `Ticket` today; give `Ticket::validate_id()` an exclusion for rooms |
| courses, d-pace, study-hub | 0 | |

### Fields

The model accessors (`get_field()`, `update_field()`, `get_field_rows()` and the rest of `Has_Fields`) reach ACF through a registered `Field_Provider` instead of calling it directly. `ACF_Field_Provider` registers itself when the sweep walks it and answers while ACF is active. Without ACF, or before its plugin file has loaded, reads return `null` and writes return `null` instead of a fatal.

| Violation | Mechanical fix |
|---|---|
| `use Has_ACF_Fields` (fatal: the trait is gone) | `use Has_Fields`; Post, User, Term, Comment and Options already have it |
| `get_acf_id()` called (undefined method) | `get_field_id()`; the per-family bodies are gone, the provider derives the id from `get_wp_meta_type()` and `get_meta_id()` |
| `get_acf_id()` overridden (checked at boot: nothing calls it now, so its guard is silently dead) | Rename it `get_field_id()` and return `parent::get_field_id()` where it built the id |
| `delete_field($selector, $value)` or `delete_sub_field($selector, $value)` | Drop the second argument: ACF's functions take a selector and an id, so the old form deleted on post id `$value` |
| Fields stored outside ACF | Implement `Field_Provider` and `Custom_Fields::register(new My_Provider)` |

Measured on the live consumers before release: 0 violations (no consumer names any of these).

Run `wp lattice classmap` after deploying: the map is valid only for the exact file set it was written from, and files moved under `include/`.

### WooCommerce

The nine framework WooCommerce classes (`Customer`, `Order`, `Order_Item`, `Order_Status`, `Product_Type`, `Woo_Account_Page`, `Woocommerce_Theme`, `Woocommerce_Clean_Theme` and the `Is_Woo_Customer` trait) are declared when `woocommerce_loaded` fires, or at once if it already has; they used to be required on every site. Without WooCommerce they do not exist.

| Violation | Mechanical fix |
|---|---|
| A reference to one of the nine that PHP resolves at boot outside a `~woocommerce/` directory (the `Digitalis\` alias now covers traits too, so `use \Digitalis\Is_Woo_Customer` resolves once the trait is declared): `extends`, a trait `use`, or a static call in a plain `Integration`'s `run()` (fatal `Class "Digitalis\X" not found` at `plugins_loaded`, on every request type including wp-admin, on a site without WooCommerce) | Move the file under `~woocommerce/`, or make the integration a `Plugin_Integration` with `$plugin = 'woocommerce'` |

Deactivating WooCommerce on a site with such references now takes the site down until they move; `wp plugin activate woocommerce --skip-plugins=<your-plugin>` is the escape. The two gates use different signals: the framework listens for `woocommerce_loaded`, a consumer's `~woocommerce/` checks the active-plugin list for a `woocommerce/` directory, so WooCommerce loaded from `mu-plugins/` or a renamed directory satisfies only the first.

Measured on the live consumers before release: no site runs without WooCommerce, so 0 violations today. The references that would fatal on a WooCommerce-less deployment:

| Plugin | Files | What |
|---|---|---|
| courses | 5 | `front.theme.php` extends `Woocommerce_Clean_Theme`; `order.order.php`, `order-item.model.php` extend `Order`, `Order_Item`; `user.user.php` uses `Is_Woo_Customer`; `integrations/woocommerce.integration.php` calls `Woo_Account_Page::hide_page()` from a plain `Integration` |
| somm | 3 | `front.class.php` extends `Woocommerce_Clean_Theme`; `dancer.user.php` uses `Is_Woo_Customer`; `integrations/woocommerce.integration.php` calls `Woo_Account_Page::hide_page()` |
| eventropy | 1 | `user.user.php` uses `Is_Woo_Customer` |
| d-pace, mycelium, study-hub | 0 | |

`ACF_Block` moved from `include/views/` to `include/integrations/acf/`; the class and namespace are unchanged. Run `wp lattice classmap` after deploying.

### Layout

| Violation | Mechanical fix |
|---|---|
| `Header`, `Footer`, `Modals` or a subclass renders while a `Page_View` is on the render stack (thrown at `print()`) | Remove it from the page view; a page that needs its own shell part declares `protected static $layout = ['header' => My_Header::class]` (or `footer`, `modals`), honoured through `App::render()` |

Measured on the live consumers before release: 0 across the six. d-pace's `Site_Header` and `Site_Footer` extend the project `Component`, so they are outside the check; `protected static $shell = true` on each opts them in without touching their template path or defaults.

A throw mid-render now also unwinds any output buffer the render opened, so a `Strict_Violation` inside a nested `(string)` cast no longer leaks the parent's buffer.

### Rendering

| Violation | Mechanical fix |
|---|---|
| An element tag that is not a bare element name (empty, or containing anything but letters, digits, `:` and `-`); thrown at `set_tag()` | Pass the tag name only; wrap markup in `content` |
| An attribute name containing whitespace, quotes, `/`, `=`, `<` or `>`; thrown when the attributes render | Fix the key; a list entry such as `['required']` is a boolean attribute |

Byte-level changes with no violation: option `value`, `id`, `for` and `name` positions in the field templates are now attribute-escaped (only values containing `& < > " '` differ); Table `data-label` holds the header text with tags stripped and attribute-escaped instead of a JSON fragment (`\/` and `\"` no longer appear); the inline scripts of `Date_Picker`, `Date_Range` and `Select_Nice` JSON-encode the element id (`getElementById("x")`) and `Select_Nice` registers `nice_selects["x_nice"]` keyed from `name` rather than the deprecated `key` (d-pace's `data-js-var` values change from `_nice` to `{name}_nice`, which its filter chips script needs; the `str_replace(null)` deprecation goes with it). A param named `path`, `template` or `return` now reaches a template as a variable.

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| courses, d-pace, eventropy, mycelium, somm, study-hub | 0 | No non-element tags, no unrenderable attribute names, no `path` / `template` / `return` params on template views |

### Editors

The page-builder integration is gone, `Editor_Manager` with it. The one thing consumers asked of it was whether the request is inside a builder's editing screen; that question belongs to the plugin that runs the builder. Paste this into your own namespace and call it instead:

```php
class Builder_State {

    // Oxygen defines SHOW_CT_BUILDER for both builder windows and OXYGEN_IFRAME only for the content iframe.
    // Bricks exposes bricks_is_builder(), bricks_is_builder_iframe() and bricks_is_builder_main().

    public static function is_backend (): bool {
        return defined('SHOW_CT_BUILDER') || (function_exists('bricks_is_builder') && bricks_is_builder());
    }

    public static function is_backend_content (): bool {
        return (defined('SHOW_CT_BUILDER') && defined('OXYGEN_IFRAME')) || (function_exists('bricks_is_builder_iframe') && bricks_is_builder_iframe());
    }

    public static function is_backend_ui (): bool {
        return (defined('SHOW_CT_BUILDER') && !defined('OXYGEN_IFRAME')) || (function_exists('bricks_is_builder_main') && bricks_is_builder_main());
    }

}
```

| Violation | Mechanical fix |
|---|---|
| Any `Editor_Manager`, `Design_System`, `Editor` or `Bricks_Element` reference; fatal at the call | Replace the predicate calls with your own `Builder_State`; delete the rest, the classes are gone |
| `View::$editors`, `$controls`, `get_editors()`, `get_controls()`, `supports_editor()` and the `lattice.view.editors` / `lattice.view.controls` filters | Delete the declarations (they were inert) |
| An `App::load_bricks_elements()` override or a `_bricks-elements/` directory | Register Bricks elements from your own `init` hook; the framework method called a `get_file_names()` that never existed |

Measured on the live consumers before release:

| Plugin | Violations | What |
|---|---|---|
| study-hub | 3 | `front.singleton.php` calls `is_backend_ui()` and, behind a secret query parameter, dumps `generate_elements()`; `hub.app.php` constructs the manager; `feature-list.view.php` declares inert `$editors` and `$controls` |
| somm | 2 | `front.class.php` and `hub-members.view.php` call the predicate |
| courses | 1 | `front.theme.php` calls `is_backend_ui()` |
| mycelium | 1 | `front.theme.php` calls `is_backend()` |
| d-pace, eventropy | 0 | |

Run `wp lattice classmap` after deploying: the map lists the deleted files until it is regenerated (ignored with a notice in production, ignored outright under `WP_DEBUG`).
