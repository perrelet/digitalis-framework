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

**The handler answers** `redirect($url)` (an address `wp_validate_redirect()` allows), markup or a `View` (drawn in place of the form), or a `WP_Error`; one naming fields in `data.params` comes back under them. A public form sets no `$require_nonce`: a page cached for everyone cannot carry one that works.

## The form

```php
<?= new Form([
    'route'  => Signup_Route::class,
    'id'     => 'signup',
    'fields' => [ … ],
]) ?>
```

`route` and a stable `id` are all a form needs. It then posts to its own page, carries its target, prints a nonce for a signed-in viewer, takes its fields' browser attributes (`required`, `type="email"`, `maxlength`, `min`, `max`, never `pattern`) and display conditions (from `when`) from the route, and draws its result: the values as sent, each message beneath its field (`aria-invalid`, `aria-describedby`), an announced summary, focus on the first field at fault. Where htmx is enqueued it also posts through htmx to the same page and swaps itself in place. A second copy on a page takes its own `id`.

`Field_Group` takes `field_params` (name => params) for any field by name; `Field` takes `error`.

## The post

The form handler sets the post aside before WordPress reads the request (so a field named `name` or `s` never becomes a query), gives it back to a REST request, and at `template_redirect` hands it to the route with `rest_do_request()`, only if the posted path matches a `Form_Route`. A signed-in visitor without a valid `wp_rest` nonce is dispatched as a visitor. An accepted `redirect()` is a 303, or `HX-Redirect` for htmx.

## Limits

Form-encoded posts; no files; scalars and lists of scalars (a nested object's problem lands on its top field); one form per route; the page must draw the form for a result to show. A handler runs inside the page request: it answers rather than prints, and `REST_REQUEST` is not defined there.
