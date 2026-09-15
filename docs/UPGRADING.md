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
