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

### Analysis

PHPStan runs at level 0 in CI (`.github/workflows/phpstan.yml`), one directory at a time locally (`vendor/bin/phpstan analyse include/<dir>` after `composer install`; the whole tree needs more memory than a small box has). The first clean run fixed things a consumer would have met on its first `v1` request:

| Was | Now |
|---|---|
| `Menu` with a `source` and `Field_Group` (so every `Query_Filters`) fataled on `v1` since the namespace-per-family commit: `Nav_Menu` and `Hook` were resolved inside `Lattice\Component` | Both import their class; the fixtures in `~/lattice-v1-tests` render them |
| `Term::get_by_name()` always returned null (it passed an undefined variable) | Works; the `get_by_*` helpers no longer pass a third argument `get_by()` ignored |
| `Table::add_rows()` dropped per-row classes and attributes (no row index) | Indexed |
| `Field\Hidden_Group` (a dead field view; its only caller is the retired mesla) | Removed |
| `Admin_Table::get_acf_field()` read a `$post_type` only `Posts_Table` declares | `Posts_Table` owns the post lookup; the base resolves field keys only |

`bin/variance.php` reads every consumer override of every framework method and property (traits flattened, so `Term::get_url` overridden through `Has_WP_Term` is attributed) and reports what PHP would refuse at class-declaration time if the framework tree changed under it. Its `FLOOR` list, the child return types the parent lacks (23 across the six consumers: `Feature::get_hooks(): array` x15, `View::condition(): bool` x6, d-pace's `Product_Category::get_url(): string` and `get_count(): int`), is what constrains the 1.0 return types; the migration list for a method gaining one is that method's full override list in `--matrix`, including the overriders tagged `via` a consumer parent, which PHP checks against that parent and never against the framework. Pass dependent consumers together: an unresolved namespaced parent is reported as a fatal, never skipped.

The parameters of `include/core` carry native types (`Factory`, `Singleton`, `Creational`, `App`, `Call`, `Hook`, `List_Utility`, `Transients` and the `Autoloader`, `Dependency_Injection`, `Resolvable` and `Is_Stashable` traits). Every call site in the six consumers was judged against them before they were written and none changes behaviour; untyped overrides stay legal. What a new caller will meet: `null` or an array where a scalar is declared, and `''` where an `int` is, is a `TypeError` instead of a silent coercion (`load_feature(null)`, `Hook::filter(null, …)`, `stash('')`), and `get_list(1)` labels the empty option `1`. One standing consumer bug surfaced on the way: `load_feature($file, true)` (courses.app.php:32, somm.app.php:127) has never created the feature, because `Factory::create()` returns on any scalar; pass `[]` or nothing. The bug pass fixed what the slice found: `autoload_multiple()` (the map form of `autoload()`) returns each object once and accepts a never-assigned by-reference variable, where it merged the walk's return back into the list it had already appended to and threw on `null`; `List_Utility::get_list()` keeps integer keys (it assembled with `array_merge()`, which renumbers them; every consumer list is string-keyed, so nothing visible changes; one edge: the null option sits at `$null_key`, `0` by default, so a list with its own `0` entry must set a `$null_key` of its own to carry both); `Factory::create($data, $arg)` forwards `$arg` to the constructor (an off-by-one dropped it; no caller passed one); and `~dir/` directories are matched against the plugins WordPress is loading on the request instead of a `get_plugins()` scan of the plugin directory on every request (see AUTOLOADER.md for what counts as active).

The parameters of `include/models` carry native types too (`Model`, `WP_Model`, `Post`, `User`, `Term`, `Comment`, `Options`, `Attachment`, `Nav_Menu`, `Nav_Menu_Item`, `Revision` and the `Has_WP_*`, `Has_Fields` and `Inherits_Props` traits, so every `Feature`, `Integration` and `View` meets `Has_WP_Hooks` and `Inherits_Props` typed). They were judged the same way and no call site in the six consumers changes behaviour. A parameter forwarded to WordPress takes the union WordPress accepts: `string|array $size` (a size name or `[w, h]`), `string|int|array $terms` (`remove_terms(5)` still removes term 5 by id), `string|array` hook names as `Hook::name()` flattens them, `?int $priority`. The `$data` and `$id` of resolution (`get_instance`, `create`, `extract_id`, `validate_id`, `build_instance`) are `mixed` by design; setters, `Field_Provider`, the `&$query` out-parameters and `Term::get_by*` values stay untyped. What a new caller will meet: a query string where WordPress's `wp_parse_args()` accepted one (`Term::query('hide_empty=0')`, `get_avatar_url('size=16')`) is a `TypeError`, pass an array; `null` into `has_term()`, `set_terms()`, `add_terms()`, `Post::get_by_slug()` or `get_field_rows()` throws instead of matching any term, clearing the terms, or missing; a callable array as a `get_hooks()` value (`'hook' => [$this, 'm']`) throws at boot where it was silently skipped, use the method name. One WordPress trap the wrappers pass through: `set_terms(5)` on a flat taxonomy creates a tag named "5"; pass `[5]`. The bug pass fixed what the slice found: `get_instance($id)` for a post, user or comment that no longer exists returns `null`, as its `?static` signature promised, where `Post` fataled in `set_wp_post(false)` and `User` and `Comment` wrapped a missing object (the one behaviour change: a consumer that did something with that wrapper now gets `null`; `validate_id()` checks the object exists before anything else, so a subclass's post-type, role or status checks hit the cache); `get_comment_count_text()` returns the text (it called the site-wide `get_comment_count()`); `Attachment::get_attachment_taxonomies()` returns the taxonomies (it called `get_taxonomies()` with a post); `get_wp_user()` on a model whose lookup failed returns `false` without a warning; `get_image_src([w, h])` caches per size instead of sharing one key across every array size; `get_instances()` of a `WP_Error` or `false` gives `[]`.

The parameters of `include/views` carry native types as well: `View`, `Component`, `Element`, `Attributes`, `Archive` and its two subclasses, `Field` and every shipped field, the components, `Menu_Active_State`, `Shortcode` and `Debug`. Every framework `params()` is `params(array &$p)`; the 212 consumer overrides written `params(&$p)` stay legal (an untyped parameter is wider) and that remains the convention for overrides. `View::render()` and `__construct()` take `mixed $params` because the framework casts the value `(array)`: a string is kept as one list entry, never parsed as a query string. What a new caller will meet: `null` where a param key is declared (`get_param(null)`, `isset($view[null])`, `unset($view[null])`; a plain read `$view[null]` still returns null through `__get()`), a non-bool second argument to `render()`, a non-array `$config` or `$options`, a string into `Table::add_rows()` or a `paginate_args` `type` other than `array` are `TypeError`s where they were coerced or ignored. Two template facts surfaced and are fixed in the bug pass: `templates/digitalis/components/menu-item.php` now prints a nested submenu from the `has_submenu` flag `Menu_Item` computes, so it renders whether or not the `Lattice\Menu` compat alias was loaded earlier in the request, and radio and checkbox-button inputs carry `type` once. In the same pass `Attributes` sanitises `href`, `src`, `action`, `formaction` and `poster`: the computed sanitised value was being discarded, so nothing was ever applied; now `esc_url_raw()` runs as the audit intended, and a value it rejects (`javascript:`, `data:`, a tab or control character inside the scheme) drops the whole attribute rather than printing `href=''`. What esc_url_raw changes: a scheme-less relative such as `team/` gains `http://`, `{braces}` are stripped, `[]` and spaces are percent-encoded, a scheme is lowercased; `/path/`, `#`, `?page=2`, `//cdn/x.js`, `mailto:`, `tel:`, a colon inside a query and a `%post_id%` placeholder inside an absolute URL are unchanged; `esc_attr()` still encodes the output; `srcset` and `xlink:href` are not in the list. A string `class` is stored as a list (so `get_class()` returns an array for `'class' => 'a b'` and `add_class()` / `has_class()` work on it, through the same whitespace split), `merge_param()` on an unset or empty key creates the list instead of throwing, and the radio, checkbox-button and checkbox-group templates print a once-attribute (the `data-field-condition` JSON) on the first input only, where options after the first carried it twice.

The parameters of `include/routing` carry native types: `Route`'s 23 (`Request_Resolver` and `REST_URL_Builder` were typed when they were written; `View_Route` declares no method). The four consumer overrides (three `render_view($view, $params)`, one `get_url($query_params = [], $nonce = null, $format = null)`, all untyped) stay legal and forward into the typed parent. `get_url()` changes nothing a caller can see: it forwarded into the already-typed `REST_URL_Builder::for_route(Route, array, bool, string)`, so a query string, `null`, or an array as `$nonce` or `$format` threw the same `TypeError` before. What a new caller will meet, each a `TypeError` where the value was coerced or ignored: a string `$query_params` into `add_query_params()` (`add_query_arg()`'s string form made the URL the value and used `REQUEST_URI`); `null`, an array or a non-Stringable object into `nonce_url()` or `render_view()`'s `$view` (a `View` instance coerces through `__toString()` to its markup and fails in `call_user_func()` as it did before); a non-array `$params` into `render_view()`; anything but a `WP_REST_Request` or `null` into `collect_nonce_candidates()` (it was ignored); `null` or an array as `find_valid_nonce()`'s action (`string|int`, what `wp_verify_nonce()` accepts); `null` as `request_inject()`'s method (it was a 500 `WP_Error`); a non-array `$handler` or a non-request into `maybe_set_wp_query_vars()`, reachable by a hand call only. A string or int as `$nonce` / `$format` still coerces: there is no `strict_types`. `respond()` and the filter callback's `$response` stay `mixed`. The follow-up pass fixed what the slice left: `Route::callback_wrap()` answers the `view-error` `WP_Error` when `get_view()` returns a `View` instance instead of a class name, naming the class (`is_subclass_of()` accepts objects, so the instance passed the guard, coerced to its markup and died in `call_user_func()` with a `TypeError`); the message for a string is unchanged, and `render_view()` called directly still coerces as above.

The parameters of `include/query` carry native types: `Query_Manager`, `Query_Profile`, `Query_Vars` and the forwarders of the soon-removed `Digitalis_Query` (its `__construct()` and `query()` first parameters mirror `WP_Query`'s untyped ones, since a child cannot narrow them). The 17 consumer `apply($query_vars, $wp_query, &$mods)` and 10 `condition($wp_query)` overrides are untyped and stay legal; the three WordPress hook callbacks take what core passes (`WP_Query`, `array $clauses`, `array $posts`). What a new caller will meet, each a `TypeError` where the value was parsed, coerced or ignored: a query string, `null` or an object into `new Query_Vars()`, `set_vars()`, `overwrite()` or `merge()`, directly or through `Digitalis_Query` (`wp_parse_args()` parsed the string; the guards returned on the rest), which includes a `Post_Type::get_query_vars()` or `get_admin_query_vars()` override that forgets to `return` (its `null` reached `merge()` through `main_query()` and was ignored; every live override returns an array); a non-array clause into `add_meta_query()`, `add_tax_query()` or an upsert's block (it was appended as-is and broke `WP_Meta_Query` later), which includes an admin-table `'acf'` `query_callback` that returns nothing; a non-array path into `get_meta_block()` / `get_tax_block()`; `null` as a key through `$qv[] = $v`, `isset($qv[null])` or `unset($qv[null])` (the bare magic methods forwarded it into `set()`, `has()` and `remove()`, which keyed on `''`; a read `$qv[null]` is still `null`, and a bool or float key still coerces); anything but a `WP_Query` into a profile or manager helper or `is_multiple()`; `null` into `compare_post_type()`, which takes `string|array|false` (what `Post::$post_type` and the profiles pass). A bool or int coerces as before: there is no `strict_types`.

The parameters of `include/registration` carry native types: `Post_Type`, `Post_Status`, `Taxonomy`, `User_Taxonomy` and `User_Role` (40 parameters in 28 methods), which closes the pass: every family under `include/` is typed. The consumer overrides of `get_args`, `get_rewrite`, `get_supports`, `get_labels`, `filter_args` and `main_query` are untyped and stay legal; they forward into the typed parent, and the documented override form stays untyped. `get_rewrite()` and `get_supports()` take `array|false`, because `register_post_type()` accepts `false` for both and the `lattice.post_type.{class}.rewrite` / `.supports` filters (HOOKS.md) may return it. The hook callbacks take what WordPress passes: `WP_Query` on `pre_get_posts`; `(array, array, array|object, bool)` on `wp_insert_post_data`, since `wp_insert_post()` hands its first argument to the filter unchanged and an object stays an object; `(int, WP_Post, bool, ?WP_Post)` on `wp_after_insert_post`; `(int, WP_Post)` on `after_delete_post`; `(bool, string)` on `disable_months_dropdown`; `?string` on `parent_file`, which core applies on an unset global on about.php, tools.php and twelve more pages; `?string` on the `manage_{slug}_custom_column` content, since a `User_Taxonomy` subclass `column()` hooked first at the same priority may return nothing. What a new caller will meet: a non-array into `get_args()`, `get_labels()`, `filter_args()`, `filter_caps()`, `register_query_vars()` or `taxonomy_columns()`, or anything but an array or `false` into `get_rewrite()` / `get_supports()`, is a `TypeError` where `register_post_type()`, `register_taxonomy()` or `add_role()` would have failed; a non-`WP_Query` into `main_query()`, `admin_query()`, `admin_controller()` or their wraps, where a method call on it failed; a non-string support name, method name or column content; and a filter at a lower priority feeding the wrong type into a callback (`disable_months_dropdown`, `query_vars`, `wp_insert_post_data`), the same contract the routing and query slices took. The follow-up pass fixed what the slice left: `Post_Type::build_filters()` declares `value_callback` and `query_callback` (both `false`) on every filter's `args` before the type branch, where only the `'acf'` branch declared them, so `admin_controller()` no longer logs two "Undefined array key" warnings per filtered admin list request (somm's `edit.php?post_type=event&tax[path]=N` met them; `wp_dropdown_categories()` ignores the two keys, so the dropdown and the `tax_query` are unchanged); and `User_Taxonomy::taxonomy_column()` returns the column content untouched for a term id `get_term()` cannot resolve, where it read `->count` on `null`.

The injector no longer sends builtin type names (`string`, `array`, ...), `self` or `parent` through the autoloaders, and a parameter with an intersection type is left alone instead of throwing `Call to undefined method ReflectionIntersectionType::getName()`. What is injected is unchanged: a class with `get_instance()`, as the parameter's type or the first member of a union. A value that is already an instance of the parameter's type is now kept instead of being re-resolved through `get_instance()`, so a callback typed `WP_Post`, `WP_Term` or `WP_Comment` receives the object it was handed; an object of another class is still cast to an id (the wrong post, with a warning), and the absent value is still `false`, so prefer the Lattice model (see DEPENDENCY_INJECTION.md).

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

Run `wp lattice classmap` after deploying: the map lists the deleted files until it is regenerated (a notice in production; under `WP_DEBUG` the map is not read at all unless `LATTICE_CLASSMAP` is defined, and then a stale one throws).
