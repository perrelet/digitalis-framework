# Digitalis Framework: Scripts

How a framework script is written, loaded and wired to the page it drives.

**The server is the truth and the script is a renderer: the markup carries the configuration, the server formats what the page shows and the script paints the state it reports.**

---

## Table of Contents

- [The Shape](#the-shape)
- [Reading Order](#reading-order)
- [Loading](#loading)
- [Configuration](#configuration)
- [The DOM Contract](#the-dom-contract)
- [Without the Script](#without-the-script)
- [State](#state)
- [Requests](#requests)
- [The Server Side](#the-server-side)
- [Timers and Storage](#timers-and-storage)
- [Naming and Comments](#naming-and-comments)
- [Hygiene](#hygiene)
- [Gates](#gates)
- [Existing Scripts](#existing-scripts)

---

## The Shape

A script is one ES module holding one class per screen or component. The class is constructed on a root element and owns everything inside it. The file ends by booting every root on the page.

```js
// Tasks and response shapes: docs/ADMIN.md, Example Page.

class Example {

    #root;
    #config;
    #els = {};

    constructor (root) {

        this.#root   = root;
        this.#config = root.dataset;

        for (const el of root.querySelectorAll('[data-el]')) this.#els[el.dataset.el] = el;

        root.addEventListener('click', event => this.#click(event));

    }

    #click (event) {

        const control = event.target.closest('[data-task]');
        if (control && this.#root.contains(control)) this.#run(control.dataset.task);

    }

    async #run (task) {

        this.#paint(await this.#request(task));

    }

    async #request (task) {

        return {};

    }

    #paint (answer) {

        this.#els.status.textContent = answer.status ?? '';

    }

}

for (const root of document.querySelectorAll('[data-lattice="example"]')) new Example(root);
```

- The file opens with one line pointing at the doc that holds its server contract, when it has one. Nothing else goes in a header.
- Internals are private (`#field`, `#method`). A script exposes nothing on `window`.
- One delegated listener per event type on the root. Controls added later by the server need no rebinding. Events that belong to the page (`visibilitychange`, `scroll`, `resize`) are listened for on `document` or `window`, once per instance.
- `async`/`await` over `fetch`. No callbacks threaded through a request helper, no `XMLHttpRequest`.
- No dependencies: no jQuery, no bundler, no npm package. The file that is committed is the file that is served.

## Reading Order

A script is read far more often than it is written, and it gets the care the PHP beside it gets.

- A class reads top to bottom the way the screen works, in a fixed order: the constructor, events, tasks, requests, painting. Whoever knows one script can find the request code in any other.
- A method stays at one level of abstraction. A task reads as a sentence, `this.#paint(await this.#request('tail'))`, and the detail lives in the methods it names. A method that scrolls off the screen is split.
- Guards come first, each an early return on one line, and the work follows unindented. No `else` after a return, no nested ternary, no chain that has to be decoded.
- A method takes no boolean argument. `fail(message, true, true)` says nothing at the call site: a flag becomes two named methods or a named option.
- The visual rhythm is the PHP's: a space before a method's parentheses, a padded body, assignments aligned in a block. A PHP file and its script look written by one hand.
- Named constants at the top of the file hold every interval and cap (`const POLL_MS = 3000;`). A number the server could own arrives as a data attribute.

## Loading

Enqueue the file as a script module, with the framework version as its cache key:

```php
wp_enqueue_script_module('@lattice/example', LATTICE_URI . 'assets/js/example.js', [], LATTICE_VERSION);
```

A module is deferred, strict and scoped by the browser, so the file needs no IIFE, no `'use strict'` and no `DOMContentLoaded` listener: the document is parsed when the module runs. Core prints enqueued modules in the footer of wp-admin and of a classic theme, so a screen may enqueue from its render callback, beside its stylesheet.

The module id is `@lattice/{name}`, which is also the name another module would import it by.

## Configuration

A script reads its configuration from `data-*` attributes on its root element. The root is marked `data-lattice="{name}"`.

```php
printf(
    "<div class='lattice-example' data-lattice='example' data-url='%s' data-action='%s' data-nonce='%s' data-size='%d'>",
    esc_url(admin_url('admin-ajax.php')),
    esc_attr("example_{$this->slug}"),
    esc_attr(wp_create_nonce("example_{$this->slug}")),
    $size
);
```

- The markup is the one source: a host that renders the screen elsewhere carries the configuration with it, and two instances on one page each read their own.
- Every user-facing string the script prints arrives the same way (a `data-labels` attribute holding `wp_json_encode()` output, or text already in the markup). A script contains no copy, so translation stays on the server.
- A script module cannot receive `wp_localize_script()` data, and a framework script does not read a global. Core's `script_module_data_{id}` filter is per module, where a data attribute is per instance.
- A missing attribute or element is a declaration error and throws in the constructor, naming the root and what it lacks (`Lattice logs: data-nonce is missing on the root.`). It never surfaces as a `null` three calls later.

## The DOM Contract

| The script finds | By | Never by |
|---|---|---|
| Its root | `data-lattice="{name}"` | an id or a style class |
| An element it writes to | `data-el="{name}"` | a style class |
| A control that asks the server for something | `data-task="{task}"` | an inline handler |
| A control that changes only the view | `data-action="{name}"` | an inline handler |

Classes belong to the stylesheet and `data-*` attributes to the script, so either can be renamed without the other. Markup carries no `onclick` and no inline `<script>`.

## Without the Script

The page works before the script runs, and the script improves it.

- A control that navigates is a link, a control that changes something is a button in a form that posts and a search is a GET form: each does its job with the script absent, and the script takes over the same control.
- Controls are the elements they act as: `<button>` for an action, `aria-pressed` on a toggle, a `role='status'` element that announces what changed ("12 new lines"), never `aria-live` on the content itself, which would queue every appended line for a screen reader. A control that cannot be used is `disabled` with the reason as text beside it: a `title` on a disabled button is not reliably shown.
- A control that only the script can serve (a live toggle) is rendered `hidden` and revealed by the script. Core's `.button` sets `display`, which beats the attribute, so the screen's stylesheet carries `[hidden] { display: none !important; }` inside its root.
- The root is printed only when everything the constructor reads is printed with it. A refusal (no permission, nothing to show) prints no `data-lattice`.

## State

- A screen is in one named state at a time (`idle`, `live`, `paused`), held in one field with one setter. No scattered booleans whose combinations nobody enumerated.
- That state shows as an `is-*` class on the root, with the matching ARIA attribute and status text. The setter sets all three, and every branch that ends or changes a run goes through it. A screen that says Finished over a bar still painted running is the failure this rule exists for.
- The script holds no state the server can answer. After a request it paints the response, not its own guess at what the response means.
- Text goes in with `textContent`. HTML is inserted only when the server rendered and escaped it for that purpose (a formatted log line, a table row), through `insertAdjacentHTML`, never assembled from strings in the script.

## Requests

One private method per file sends every request and maps every status. Tasks are POSTed to `admin-ajax.php` with the action, the task and the nonce in the body:

```js
async #request (task, data = {}) {

    const body = new FormData();

    for (const [key, value] of Object.entries({ action: this.#config.action, nonce: this.#config.nonce, task, ...data })) body.append(key, value);

    const response = await fetch(this.#config.url, { method: 'POST', body, credentials: 'same-origin', signal: this.#abort.signal });
    const answer   = await response.json().catch(() => null);

    if (response.ok)             return answer;
    if (response.status === 409) return { refused: true, ...answer };

    throw Object.assign(new Error(answer?.data ?? ''), { status: response.status });

}
```

| Status | Meaning | The script |
|---|---|---|
| 200 | The task's answer | paints it |
| 409 | The server refused for a reason the user can act on | paints the refusal the body describes |
| 403 | The nonce expired or the capability is missing | shows the message and stops polling |
| anything else | A fault | shows it once, stops polling, leaves the page as it was |

A thrown request is caught in one place per screen, which reads the error's `status` and shows its message, or a label from `data-labels` when the message is empty (HTTP/2 carries no status text, and a logged-out `admin-ajax.php` answers 400 with a bare `0`). An `AbortError` is the script's own doing and is ignored there. A shared request module is written when a second script needs the same helper, not before.

## The Server Side

- One action per screen instance, `wp_ajax_{screen}_{key}`, handled by one method that switches on `task`. The nonce action has the same name.
- The handler checks the nonce, then the capability for that task, then the task name. A bad nonce or a missing capability is 403 and an unknown task 422, each through `wp_send_json_error($message, $status)`, whose `data` is the message the script shows.
- A task's answer and a 409 refusal go out through `wp_send_json()`. A response that carries markup carries it formatted and escaped by the same PHP that renders the page, so the page and the script cannot disagree.
- Task names and response shapes are a contract: they are listed in the screen's reference doc and a change to one is an UPGRADING row.

## Timers and Storage

- Poll with `setTimeout` scheduled after the previous response is painted, never `setInterval`: a slow server then slows the loop instead of stacking requests.
- A polling loop pauses on `visibilitychange` when the tab is hidden and resumes when it returns.
- Each run (a polling session, a one-off task) gets a fresh `AbortController`, since an aborted controller stays aborted; stopping the run aborts it.
- A per-viewer preference (wrap, order) lives in `localStorage` under `lattice:{name}:{preference}`, read and written inside `try`/`catch`. The page works without it. Anything the server needs to know is a request, not storage.

## Naming and Comments

The code documents itself, so the names carry the meaning.

- A method is named for what it does, verb first: `#paint()`, `#request()`, `#appendLines()`, `#pausePolling()`. A method that answers a question reads as one: `#isFollowing()`, `#hasNewLines()`.
- A verb means one thing in every script: `request` talks to the server, `paint` writes state to the DOM, `read` takes from the DOM or the configuration, `schedule` sets a timer, `remember` and `recall` use storage. A new verb is added here before it is used twice.
- A value is named for what it holds, in full words: `lastSize`, not `ls` or `data`.
- JavaScript identifiers are camelCase and classes PascalCase. Keys that cross the boundary (task names, response fields, `data-*` names) keep the server's spelling and are not remapped.
- A method that needs a comment to say what it does is renamed or split until it does not.

Comments are for what the code cannot say.

- A comment marks a genuine gotcha (why the obvious form is wrong here) or a reference the reader could not find from the code (the doc that holds a contract). Nothing else earns one: no restating the line below, no docblocks listing parameters, no history.
- A comment is one short line. If it needs a paragraph, the paragraph belongs in the screen's doc and the comment is the pointer.
- A large class may be split into sections with a short label on its own line (`// Requests`, `// Painting`).

## Hygiene

- Four spaces, LF line endings and a final newline (`.gitattributes` holds `*.js` to LF).
- `const` by default, `let` when reassigned, never `var`. Strict equality.
- The syntax gate is `node --input-type=module --check < assets/js/example.js`. There is no build step to hide a syntax error until the browser finds it.

## Gates

- `php bin/scripts.php` reads every script outside the table below, with string and template contents blanked, and fails on `var`, `setInterval`, `XMLHttpRequest`, `console.`, `document.write`, a block comment, an assignment to `innerHTML` or `outerHTML`, an assignment to a property of `window`, `globalThis` or `self` (`window.location` excepted), a loose `==` or `!=`, a commented-out statement or a CR. It fails too when the table names a file that is gone. CI runs it beside `bin/boundaries.php`, with the syntax check above.
- Every behaviour a script adds comes with a browser probe that fails on the commit before it.

## Existing Scripts

The scripts that predate this document are classic scripts in several shapes. Each is rewritten to this document when a stream next touches it, and its row leaves the table then.

| Script | Shape today |
|---|---|
| `assets/js/iterator.js` | IIFE around an object literal, `XMLHttpRequest`, `iterator_params` global, CRLF. Rewrite recorded as its own stream; its tasks, response shapes and template names are kept. |
| `assets/js/menu.js`, `menu-drawer.js` | IIFE, `var`; contract in `docs/specs/menu/SPEC.md` |
| `assets/js/counter.js` | IIFE around an object literal |
| `assets/js/fields.js`, `woo-clean.js`, `acf-ajax.class.js` | Global class (`acf-ajax` on jQuery) |
| `assets/js/debugger.js` | Custom elements, global classes |
| `_features/lattice/page-loader`, `scroll-classes` | Arrow IIFE over a localized global |
| `_features/lattice/proposals` (two files) | Classic scripts in core's style, one on the block editor's `wp` global |
| `modules/mapbox.js`, `modules/query.js` | Not yet read against this document |
