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
