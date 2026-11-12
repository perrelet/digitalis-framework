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
| `costly` | Runs only after every other rule, the permission and the nonce; needs a `validate_callback` (or a feature that supplies one through `lattice.forms.rule`), else the route cannot serve (below), whatever its `when` |

**What it does with them, for every client:** the permission first (a refusal names no field); then whether it can serve at all (below); then every rule in one pass, so a refusal names every field at fault (`rest_invalid_param`, 400, `data.params` field => message); then the nonce and the costly rules; then the handler. What it advertises (OPTIONS) shows its arguments as not `required`: the check lives in the route.

**The handler answers** one of: `redirect($url)`, to an address `wp_validate_redirect()` allows; markup or a `View`, drawn in place of the form; a `WP_Error`, which comes back under the fields its `data.params` names. A public form sets no `$require_nonce`: a page cached for everyone cannot carry one that works.

## When a route cannot serve

`get_unavailable_reason(WP_REST_Request $request)` says why a route cannot serve anyone with these URL params now, or `''`. It reads the URL params and the site's state, never the posted body or the user: drawing and posting must agree, and a signed-in visitor with a stale nonce is posted as a visitor. Whether this visitor may is `permission()`'s. The default is a costly rule nothing can check; a route adds its own reasons, starting from the parent's.

- **Drawn:** with a reason, a `Form` on that route draws nothing for a visitor (`(string)` answers `''`), and the reason alone, in `<p class='form-unavailable'>`, for whoever can `edit_posts`. An answer held for its id is drawn whatever the reason. A page printing something around a form draws the form first and prints its part only when the form drew something.
- **Posted:** asked right after the permission, before any rule, so nobody corrects a field only to be refused: 503 `lattice_form_unavailable`, "This form isn't available.", the reason never shown. Held by the form handler, it is drawn as that message alone, with nothing to send again.
- **Safe:** a reason that throws counts as one, logged with the exception, so a broken reason costs the form, never the page; a reason is logged once an hour per route.
- **Cached pages:** a page cached while a route could not serve keeps no form until the cache clears. Fixing the cause purges nothing.

## The form

```php
<?= new Form([
    'route'  => Signup_Route::class,
    'id'     => 'signup',
    'fields' => [ … ],
]) ?>
```

`route` and a stable `id` are all a form needs. It then posts to its own page, carries its target, prints a nonce for a signed-in viewer, takes its fields' browser attributes (`required`, `type="email"`, `maxlength`, `min`, `max`, never `pattern`) and display conditions (from `when`) from the route and draws its result: the values as sent, each message beneath its field (`aria-invalid`, `aria-describedby`), an announced summary, focus on the first field at fault. Where htmx is enqueued it also posts through htmx to the same page and swaps itself in place. A second copy on a page takes its own `id`. A route with a pattern takes its values as `route_params`. To judge several forms, a route overrides `get_rules()`, starting from the parent's, and reads its URL params there: drawn or posted, they are the pattern's captures from the encoded path, as strings. A posted value outranks a URL param of the same name, in `get_param()` and in the rules: read a param with `get_url_params()`, and give it no rule, or a visitor's post can change it.

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
new Field\Human_Check(['name' => 'human', 'widget' => ['theme' => 'dark']])
```

Declared `costly`, the form is unavailable rather than open on a site that has not loaded the feature, and the feature leaves the rule unchecked without a provider or keys, so the form is not drawn there either. The field takes the argument's name, and the action from the rule. Its `widget` holds the provider's own display options (Turnstile's `theme`, `size`, `language`), which add to the provider's params and never replace them. The rule runs last, after the permission and the nonce, and checks the token with the provider: the action and this site's hostname must match (`lattice.human_check.hostnames` widens the hosts), and a token passes once. A refused token shows under the field; a provider that cannot answer, or wrong keys, make the post unavailable (503), logged and remembered for 30 seconds, while the form is still drawn, since that passes. A submit waits for the token while a challenge shows, and goes without one only when the provider's script fails.

| Filter | What |
|---|---|
| `lattice.human_check.keys` | `['site_key' => …, 'secret' => …]`, given the provider's class |
| `lattice.human_check.provider` | The provider's class, given the action (default `Turnstile_Provider`) |
| `lattice.human_check.hostnames` | The hosts a token may come from (default the site's) |
| `lattice.human_check.timeout` | Seconds to wait for the provider (3) |
| `lattice.human_check.widget` | Display options for every widget, given the action and the provider's class; a field's own `widget` wins |

A provider is a `Human_Check_Provider`: its script and adapter (`render`, `reset`, `remove` in `LatticeHumanCheck.register()`), its widget's params (pinning whatever its flow needs, such as Turnstile's `execution`, and mapping any display option that belongs in its script's address, such as another provider's language), its token field, `verify()` answering `passed`, `failed` or `unavailable` and the hosts a Content-Security-Policy must allow (Turnstile: `challenges.cloudflare.com`). Widgets render explicitly, so another plugin's own scan for its provider's class never draws them twice. After rotating keys or switching provider, purge cached pages. Without a persistent object cache, a spent token can pass again within the provider's first few seconds.

## Limits

Form-encoded posts; no files; scalars and lists of scalars (a nested object's problem lands on its top field); one set of fields per route address; the page must draw the form for a result to show. A handler runs inside the page request: it answers rather than prints, and `REST_REQUEST` is not defined there.
