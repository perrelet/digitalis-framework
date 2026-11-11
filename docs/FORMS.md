# Digitalis Framework: Forms that check themselves

A form posts to the page it is on; its route checks it the form's way and the page draws the result. Works on every site, with htmx as an enhancement.

```php
public function load () {

    $this->load_feature('lattice/forms');

    parent::load();

}
```

---

## The route

A route that judges a form extends `Form_Route` (shipped by `lattice/forms`, as `Job` is by `lattice/jobs`). Its `$args`, in WordPress's REST schema, are the rules:

```php
class Signup_Route extends \Digitalis\Form_Route {

    protected $namespace  = 'my-plugin/v1';
    protected $route      = 'signup';
    protected $definition = ['methods' => 'POST'];

    protected $args = [
        'email'    => ['type' => 'string', 'format' => 'email', 'required' => true],
        'topics'   => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['news', 'events']], 'required' => true, 'messages' => ['missing' => 'Please choose at least one.']],
        'workshop' => ['type' => 'string', 'required' => true, 'when' => ['source', '=', 'stay-connected']],
    ];

    public function callback (WP_REST_Request $request) {

        // … the work …
        return $this->redirect(home_url('/thank-you/'));

    }

}
```

| Rule key | Meaning |
|---|---|
| WordPress's schema | `type`, `format`, `enum`, `minLength`, `maxLength`, `pattern`, `minimum`, `maximum`, `items`, `validate_callback`, `sanitize_callback`, `default` |
| `required` | Empty is missing: `''`, an empty list or absent, judged before any default |
| `when` | `[name, operator, value]`: asked for only while it holds; otherwise not checked, and the handler gets it as if not sent. Operators `=`, `!=` (as strings; a list matches when any item does), `<`, `<=`, `>`, `>=` (as numbers) |
| `messages` | `code => text`, for `missing` or any of WordPress's codes |
| `costly` | Runs only after every other rule, the permission and the nonce; needs a `validate_callback` (or a feature that supplies one through `lattice.forms.rule`), else the form is unavailable (503) |

**What it does with them, for every client:** the permission first (a refusal names no field); then every rule in one pass, so a refusal names every field at fault (`rest_invalid_param`, 400, `data.params` field => message); then the nonce and the costly rules; then the handler. What it advertises (OPTIONS) shows its arguments as not `required`: the check lives in the route.

**The handler answers** one of: `redirect($url)`, to an address `wp_validate_redirect()` allows; markup or a `View`, drawn in place of the form; a `WP_Error`, which comes back under the fields its `data.params` names. A public form sets no `$require_nonce`: a page cached for everyone cannot carry one that works.

## The form

```php
<?= new Form([
    'route'  => Signup_Route::class,
    'id'     => 'signup',
    'fields' => [ … ],
]) ?>
```

`route` and a stable `id` are all a form needs. It then posts to its own page, carries its target, prints a nonce for a signed-in viewer, takes its fields' browser attributes (`required`, `type="email"`, `maxlength`, `min`, `max`, never `pattern`) and display conditions (from `when`) from the route and draws its result: the values as sent, each message beneath its field (`aria-invalid`, `aria-describedby`), an announced summary, focus on the first field at fault. Where htmx is enqueued it also posts through htmx to the same page and swaps itself in place. A second copy on a page takes its own `id`. A route with a pattern takes its values as `route_params`. To judge several forms, a route overrides `get_rules()`, starting from the parent's, and reads its URL params there: drawn or posted, they are the pattern's captures from the encoded path, as strings.

`Field_Group` takes `field_params` (name => params) for any field by name; `Field` takes `error`.

## The post

The form handler sets the post aside before WordPress reads the request (so a field named `name` or `s` never becomes a query), gives it back to a REST request and at `template_redirect` hands it to the route with `rest_do_request()`, only if the posted path matches a `Form_Route`. A signed-in visitor without a valid `wp_rest` nonce is dispatched as a visitor. An accepted `redirect()` is a 303, or `HX-Redirect` for htmx.

## Proving a person

`load_feature('lattice/human-check')` (it loads `lattice/forms`) adds one rule and one field:

```php
protected $args = [
    'email' => ['type' => 'string', 'format' => 'email', 'required' => true],
    'human' => ['human_check' => 'signup', 'costly' => true],
];
```

```php
new Field\Human_Check(['name' => 'human'])
```

Declared `costly`, the form is unavailable rather than open on a site that has not loaded the feature. The field takes the argument's name, and the action from the rule. The rule runs last, after the permission and the nonce, and checks the token with the provider: the action and this site's hostname must match (`lattice.human_check.hostnames` widens the hosts), and a token passes once. A refused token shows under the field; a provider that cannot answer, or wrong or missing keys, make the form unavailable (503), logged and remembered for 30 seconds. A submit waits for the token while a challenge shows, and goes without one only when the provider's script fails.

| Filter | What |
|---|---|
| `lattice.human_check.keys` | `['site_key' => …, 'secret' => …]`, given the provider's class |
| `lattice.human_check.provider` | The provider's class, given the action (default `Turnstile_Provider`) |
| `lattice.human_check.hostnames` | The hosts a token may come from (default the site's) |
| `lattice.human_check.timeout` | Seconds to wait for the provider (3) |

A provider is a `Human_Check_Provider`: its script and adapter (`render`, `reset`, `remove` in `LatticeHumanCheck.register()`), its widget's params, its token field, `verify()` answering `passed`, `failed` or `unavailable` and the hosts a Content-Security-Policy must allow (Turnstile: `challenges.cloudflare.com`). Widgets render explicitly, so another plugin's own scan for its provider's class never draws them twice. After rotating keys or switching provider, purge cached pages. Without a persistent object cache, a spent token can pass again within the provider's first few seconds.

## Limits

Form-encoded posts; no files; scalars and lists of scalars (a nested object's problem lands on its top field); one set of fields per route address; the page must draw the form for a result to show. A handler runs inside the page request: it answers rather than prints, and `REST_REQUEST` is not defined there.
