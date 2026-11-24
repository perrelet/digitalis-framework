# Lattice: Admin Reference

Reference for admin pages, tables, meta boxes, and ACF integration.

---

## Table of Contents

- [Admin Pages](#admin-pages)
- [Admin Sub Pages](#admin-sub-pages)
- [Logs Page](#logs-page)
- [Iterator Screen](#iterator-screen)
- [Posts Table](#posts-table)
- [Users Table](#users-table)
- [Terms Table](#terms-table)
- [Meta Boxes](#meta-boxes)
- [ACF Field Groups](#acf-field-groups)
- [ACF Blocks](#acf-blocks)
- [ACF Options Pages](#acf-options-pages)
- [Quick Reference](#quick-reference)

---

## Admin Pages

Create top-level admin menu pages.

### Basic Admin Page

**File:** `include/admin/settings.admin-page.php`

```php
namespace Lattice;

class Settings_Page extends Admin_Page {

    protected $slug       = 'my-settings';
    protected $title      = 'My Settings';
    protected $menu_title = 'Settings';
    protected $capability = 'manage_options';
    protected $icon       = 'dashicons-admin-settings';
    protected $position   = 30;

    public function callback() {
        ?>
        <h1><?= esc_html($this->title) ?></h1>
        <p>Settings page content here.</p>
        <?php
    }
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$slug` | `string` | `'admin-menu-page'` | URL slug and menu ID |
| `$title` | `string` | `'Page Title'` | Browser/page title |
| `$menu_title` | `string` | `'Menu Title'` | Sidebar menu label |
| `$capability` | `string` | `'manage_options'` | Required capability |
| `$icon` | `string` | `'dashicons-marker'` | Dashicon or URL |
| `$position` | `int\|null` | `null` | Menu position |

### Methods

| Method | Description |
|--------|-------------|
| `callback()` | Override to render page content |
| `get_url($blog_id)` | Get admin page URL |

`Admin_Page` is a Factory cached by `$slug`, so `Admin_Page::get_instance('my-settings')` returns the registered instance. To build a page from a config, declare it: `Admin_Page::declare('my-settings', ['title' => 'Settings'])` fills the slug from the name and is found the same way.

### With View

```php
class Dashboard_Page extends Admin_Page {

    protected $slug  = 'dashboard';
    protected $title = 'Dashboard';

    public function callback() {
        echo new Dashboard_View([
            'user'  => User::current(),
            'stats' => $this->get_stats(),
        ]);
    }

    private function get_stats() {
        return [
            'projects' => count(Project::query(['posts_per_page' => -1])),
            'orders'   => count(Order::query(['posts_per_page' => -1])),
        ];
    }
}
```

### With ACF Options

```php
class Settings_Page extends Admin_Page {

    protected $slug       = 'theme-settings';
    protected $title      = 'Theme Settings';
    protected $capability = 'manage_options';

    // Switch-on, not the constructor: a constructor only configures
    public function run() {
        // Register ACF options page attached to this menu
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page([
                'page_title'  => $this->title,
                'menu_slug'   => $this->slug,
                'parent_slug' => $this->slug,  // Use same slug to integrate
                'capability'  => $this->capability,
            ]);
        }
    }

    public function callback() {
        // ACF renders the form automatically
    }
}
```

---

## Admin Sub Pages

Create submenu pages under a parent menu.

### Basic Sub Page

**File:** `include/admin/advanced-settings.admin-sub-page.php`

```php
namespace Lattice;

class Advanced_Settings extends Admin_Sub_Page {

    protected $parent     = 'my-settings';  // Parent slug
    protected $slug       = 'advanced-settings';
    protected $title      = 'Advanced Settings';
    protected $menu_title = 'Advanced';
    protected $capability = 'manage_options';
    protected $position   = 10;

    public function callback() {
        ?>
        <h1><?= esc_html($this->title) ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('advanced_settings_group');
            do_settings_sections('advanced-settings');
            submit_button();
            ?>
        </form>
        <?php
    }
}
```

### Using Parent Class Reference

```php
class Import_Page extends Admin_Sub_Page {

    protected $parent     = Settings_Page::class;  // Reference parent class
    protected $slug       = 'import';
    protected $title      = 'Import Data';
    protected $menu_title = 'Import';

    // Parent resolves to Settings_Page::$slug automatically
}
```

### Under WordPress Core Menus

```php
// Under Tools
class My_Tool extends Admin_Sub_Page {
    protected $parent = 'tools.php';
    protected $slug   = 'my-tool';
}

// Under Settings
class My_Settings extends Admin_Sub_Page {
    protected $parent = 'options-general.php';
    protected $slug   = 'my-settings';
}

// Under Users
class User_Reports extends Admin_Sub_Page {
    protected $parent = 'users.php';
    protected $slug   = 'user-reports';
}

// Under WooCommerce
class WC_Reports extends Admin_Sub_Page {
    protected $parent = 'woocommerce';
    protected $slug   = 'custom-reports';
}
```

---

## Logs Page

A specialized `Admin_Sub_Page` for viewing and clearing log files in the WP admin. Uses the `Log` service for file reading.

**Location:** `include/admin/logs-page.abstract.php`

```php
namespace Lattice;

class Plugin_Logs_Page extends Logs_Page {

    protected $parent     = 'tools.php';
    protected $slug       = 'plugin-logs';
    protected $title      = 'Plugin Logs';
    protected $menu_title = 'Logs';
    protected $capability = 'manage_options';

    protected function filter_logs (&$logs) {

        parent::filter_logs($logs); // the PHP error log

        $logs['app'] = Log::create('app.log');

        $logs[] = [
            'name'  => 'Imports',
            'slug'  => 'imports',
            'path'  => WP_CONTENT_DIR . '/uploads/imports.log',
            'theme' => ['basic'],
        ];

    }

}
```

Declare logs in `filter_logs()`: a `Log` instance under its slug, or a row. Overriding `get_logs()` skips the permission parsing. `filter_logs()` runs on a clear request before the page renders as well as on each view, so it only declares: it writes nothing.

Every declared log gets a tab. A file that does not exist yet reads "Nothing logged yet", an unreadable one says so and a row with no path reads "No file is configured". The Error Log entry the parent adds resolves PHP's `error_log` setting, then `WP_DEBUG_LOG`.

Clear posts to the page's own URL (`get_url()`), is handled on `admin_init` by `handle_clear()` and redirects back with `cleared` or `refused`, so a reload never clears twice.

### Log Definition

```php
[
    'name'        => 'Display Name',
    'slug'        => 'unique-slug',     // Used in query string
    'path'        => '/abs/path.log',   // Direct path (or use instance)
    'instance'    => Log::create('app.log'), // set for you when the row is a Log instance
    'theme'       => ['basic'],          // Syntax highlighting theme(s)
    'permissions' => [],                 // Additional cap checks
]
```

The page renders the log tail with pagination, inside a `<div class='lattice'>` scope: the log switcher is core's tab strip (`nav-tab-wrapper`, `nav-tab`, `nav-tab-active`), the actions are core buttons, and the styles come from `assets/css/logs.css` (handle `lattice-logs`, depending on `lattice-tokens`, enqueued on `admin_enqueue_scripts` for the page's own request and from `callback()` elsewhere). The dark reading surface declares a `--log-*` token group on `.digitalis-log` for retuning.

The page's script is the module `@lattice/logs` (`assets/js/logs.js`, enqueued by `enqueue_script()` beside the stylesheet; wp-admin prints script modules from WordPress 6.6). It follows [SCRIPTS.md](./SCRIPTS.md). Without it the page reads the same: Refresh is a link and Clear posts without asking, guarded by its nonce and capability.

### The Script Contract

The root is `.digitalis-logs[data-lattice='logs']`, printed only for a viewer who may read the log:

| Attribute | Holds |
|---|---|
| `data-url`, `data-action`, `data-nonce` | `admin-ajax.php`, and `lattice_logs_{slug}` as both the action and the nonce action |
| `data-log` | The log's slug |
| `data-size`, `data-key` | The offset just past the last complete line on page 1, and a hash of the 64 bytes before it |
| `data-bpp`, `data-plain` | The page size and the plain flag the page was rendered with |
| `data-poll` | Milliseconds between two reads while live (`protected $tail_interval`, seconds, default 3) |
| `data-labels` | JSON from `get_labels()`: `confirmClear`, `live`, `paused`, `newLine`, `newLines`, `fault`, `copied`, `copyFailed` |

| Element | Is |
|---|---|
| `data-el='entries'` | The entries; the script appends to it |
| `data-el='partial'` | A last line with no newline yet; replaced on every answer |
| `data-el='bottom'` | The scroll target on boot (unless the URL has a hash) and while following |
| `data-el='live'` | The Live toggle's wrapper, `hidden` until the script; the toggle is `data-action='live'`, `disabled` with a reason off page 1 |
| `data-el='status'` | `role='status'`: the state's label, then "n new lines" |
| `data-el='pill'` | `data-action='follow'`: counts lines that arrived while the reader was scrolled up, and jumps down |
| `data-el='clear'` | The Clear form, which the script confirms |
| `data-el='lines'` | The entries and the partial together: what Copy reads and Newest first scrolls to |
| `data-el='view'` | The wrapper of Wrap (`data-action='wrap'`), Newest first (`newest`) and Copy page (`copy`), `hidden` until the script |
| `data-el='resize'` | The page size form's Apply button, which the script hides: the select (`data-action='resize'`) then submits on change |
| `data-task='tail'` | On Refresh, page 1 only: one request, no reload |

States: `idle`, `live`, `paused` (the tab is hidden), as `is-*` on the root and `aria-pressed` on the toggle.

Two tasks. `download` is posted by the Download form itself and answers the file as an attachment (`text/plain`, `nosniff`), under the view permission. `tail` is POSTed by the script with `log`, `size`, `key`, `bpp` and `plain`. Guards answer through `wp_send_json_error()`: 403 for the nonce, 404 for a log that is not declared or has no readable file, 403 for the view permission, 422 for an unknown task.

| Answer | When | Fields |
|---|---|---|
| Appended | The bytes before `size` still hash to `key` | `size`, `key`, `html` (the complete appended lines as entries), `count` (lines), `partial` (the unterminated remainder's markup, or `''`) |
| Reset | The file is shorter than `size`, the bytes before it changed (a clear, a rotation, a rewrite) or more than a page was appended | `reset: true`, `size`, `key`, `html` (page 1 afresh), `partial` |

Lines that arrive later for an entry already on the page (a trace written in a second write) render as a "continued" entry, and a repeat of the page's last entry starts a new fold, until the next reload. `filter_logs()` runs on every poll of every open tab, so it only declares.

### Reading Controls

Above the log: a page size select (50 KB, 200 KB, 1 MB and the current size when it is another; any positive `?bpp=` still pages), a Plain link (`?plain=1`), Download and, with the script, Wrap, Newest first and Copy page. Wrap and Newest first are remembered per browser under `lattice:logs:wrap` and `lattice:logs:newest`, and show as `is-unwrapped` and `is-newest` on the root. Newest first reverses the paint with `flex-direction: column-reverse`: the document stays in file order, so the tail still appends and Copy still reads oldest to newest. The pagination says "Page n of m".

### Search

Search is rendered by the server and needs no script. The form writes `?q=`: a case-insensitive substring, or a pattern when the term is written `/between slashes/` (`/…/i` for case-insensitive; an invalid pattern is a notice; both forms match whole UTF-8 characters, and a line that is not UTF-8 is matched by the byte). The level chips write `?level=` (`error`, `warning`, `notice`, `deprecated`), alone or with a term. A results view replaces the page: the file is scanned from its head, grouped and folded as the page is, and each matching entry prints with the term in `<mark class='x-match'>` and a "Show in the log" link.

The scan stops at `protected $search_max = 500` matches or `protected $search_budget = 2` seconds and says how far it got. A results view carries no pagination, no Live and no script root.

"Show in the log" links to `?at={byte offset}#at`. The page that holds the offset is resolved when the link is followed, so it stays right as the log grows; the entry is marked `is-target` and opened.

### Entries and Themes

The page reads a log as entries. A line that opens with a time starts an entry, and the lines after it (a stack trace, the SQL of a database error, `print_r` output) belong to it: the head shows, the rest sits in a native `<details>` ("14 more lines"). A log with no stamped line renders one entry per line. Consecutive entries that differ only in their stamp fold to one with `×n` and the last stamp. An entry whose head matches a level pattern carries `data-level` (`error`, `warning`, `notice`, `deprecated`, `strict`), which colours its edge. Each entry's `id` is `b{byte offset}`. A page that opens partway through an entry shows the remainder as "continued from the previous page"; pages are cut on lines.

A row's `theme` names the rule sets to merge, in order; a row without one takes `['basic']`, and a `Log` instance that exports variables takes `['basic', 'php']`. `get_syntax_rules()` answers, per theme:

```php
public function get_syntax_rules () {

    $rules = parent::get_syntax_rules();

    $rules['queue'] = [
        'entry'  => ["^job \d+ at \d\d:\d\d"],            // patterns that start an entry
        'levels' => ['error' => ["\bFAILED\b"]],            // level => patterns, tested on the head
        'tokens' => ['job-id' => "\bjob \d+\b"],           // name => pattern, wrapped in class x-{name}
    ];

    return $rules;

}
```

Themes merge per key: `entry` and each level's patterns add up, and a token rule replaces an earlier one of the same name. Patterns are written for the raw line (`->`, not `-&gt;`) and delimited by `/`, so escape a slash. Tokens are matched in one pass: the leftmost match wins, then the earlier rule, and a token never sits inside another (a path inside a quoted string is part of the string). A token rule holds non-capturing groups only and must match at least one character; a rule that breaks this, or does not compile, is a strict violation naming the theme and the rule, and is skipped in production. Lines over 8 KB print without tokens. `?plain=1` prints any page without tokens, keeping entries and levels.

The parsing lives in `Log_Format` (`parse()` for entries, `tokenize()` for one line), which a tail, an export or a test can use without the page.

---

## Iterator Screen

An `Iterator` adds its own page under Tools (`$parent_menu_slug`) and prints one controller there through `render_controller()`, which a host may also call on a page of its own; several controllers share a page. The controller is the `Iterator_UI` view inside a `<div class='lattice'>` scope, styled by `assets/css/iterator.css` (handle `lattice-iterator`). [API_REFERENCE.md](./API_REFERENCE.md) lists the class; this section is the contract between the markup, the script and `Iterator::ajax()`.

The script is the module `@lattice/iterator` (`assets/js/iterator.js`), enqueued by `enqueue_script()` from `render_controller()`. It follows [SCRIPTS.md](./SCRIPTS.md). A foreground run is a chain of requests, so every control needs it: each `button[data-task]` is printed `disabled` and the script enables them. Without JavaScript a `<noscript>` line beside the buttons says so; on WordPress before 6.6, where wp-admin prints no script modules, `enqueue_script()` answers false and the same line names the version. The page then reads as a report of the run.

### The Script Contract

The root is `.digitalis-iterator[data-lattice='iterator']`:

| Attribute | Holds |
|---|---|
| `data-url`, `data-action`, `data-nonce` | `admin-ajax.php`, and `iterator_{key}` as both the action and the nonce action |
| `data-state` | The state the page was rendered in (`Iterator::get_run_state()`) |
| `data-busy` | `Iterator::BUSY_STATES`, space separated: the states that have a driver |
| `data-index`, `data-total` | The run's position and its total; `data-total` is empty for an unknown total |
| `data-labels` | JSON from `get_ui_labels()`: one label per state, then `stalled`, `alreadyComplete`, `noItems`, `unknownTotal`, `unknownProgress`, `confirmReset`, `confirmDismiss`, `confirmStopBackground`, `faultMessage`, `needsScript`, `needsWordPress` |

| Element | Is |
|---|---|
| `button[data-task]` | `start`, `once`, `background`, `stop`, `reset`, `retry`, `dismiss` |
| `data-el='options'` | The run options' fieldset, disabled in a busy state; `batch_size` and `dry_run` are its inputs |
| `data-el='status'` | `role='status'`: the state's label, or Stalled |
| `data-el='message'` | `role='alert'`: a refusal's or a fault's message; empty otherwise, and then it takes no space |
| `data-el='index'`, `total`, `percent`, `track`, `bar` | The progress figures; `track` is the `role='progressbar'` |
| `data-el='time'`, `eta` | The elapsed clock and the estimate, as text from the server |
| `data-el='count-processed'`, `count-skipped`, `count-failed` | The three counts |
| `data-el='failed'`, `failed-count`, `failed-list` | The Failed panel (`is-empty` with none), its count and its list |
| `data-el='log'` | The batch log; the script prepends rows |

The root carries `is-{state}` for exactly one state, `is-busy` and `aria-busy` beside a busy one, and two facts beside the state: `is-stalled` (the label reads Stalled) and `is-indeterminate` (the total is unknown).

| State | Named by | When | Busy |
|---|---|---|---|
| `queued`, `background`, `stopping` | the server | A background run is open: not started, running, asked to stop | yes |
| `running` | the server | A page holds the run's lock | yes |
| `halted`, `complete` | the server | The record's status, with no driver | no |
| `stopped` | the server | A running record nobody drives | no |
| `ready` | the server | An idle record | no |
| `starting`, `retrying`, `working` | the script | A chain's first request, a retry chain, and a Reset, Dismiss, Run in background or background Stop in flight | yes |
| `stopping` | the script | Stop pressed on a chain this page drives; the batch in flight still lands | yes |
| `locked` | the server, in a refusal | A 409 that names another page as the driver | no |
| `fault` | the script | A request failed | no |

Tasks are POSTed with `action`, `nonce`, `task` and, while the page holds the lock, `token`. `iterate` also carries `batch_size` (omitted when empty) and `dry_run` (`1` or `0`). The guards answer through `wp_send_json_error()`: 403 for the nonce and for the capability, 422 for a missing or unknown task. A refusal is a 409 whose body carries `message`, `run_by` and the fields below.

`ajax()` adds to every answer, 200 and 409, what the script paints, read after the task has run:

| Field | On | Holds |
|---|---|---|
| `state`, `stalled` | every answer | `get_run_state()` and `is_stalled()`; a refusal that names another page answers `locked`, one that names a background run answers the job's state |
| `index`, `nothing` | `total` | The position, and `empty` (a total of zero), `complete` (the index has reached the total) or null |
| `time`, `eta` | `iterate` | `format_elapsed()` of the run's server time, and `format_eta()` of `get_eta()`; `eta` is `''` on a retry tick, on a stalled answer and when the state is not `running` |
| `log_html` | `iterate` | The batch's rows, top to bottom: the per-id lines under `$print_results`, the batch's errors and log lines newest first (`Iterator_Log_Lines`). The per-id rows are shown, never stored |
| `failed_html` | `iterate`, `retry`, `dismiss` | The Failed panel's list items (`Iterator_Failed_List`) |

The tasks' own fields are those `total`, `iterate()`, `stop()`, `background()`, `reset()`, `stop_cron()`, `retry()` and `dismiss()` return, unchanged: a job, the CLI and a direct caller get them without the fields above.

| Task | The script |
|---|---|
| `start`, `once` | `total` takes the lock and answers the `token`; then one `iterate` per batch until the answer's `state` is not `running`, the run stalls, Stop is pressed or, for `once`, one batch has run. A chain the server did not end (it releases a complete or halted run itself) ends with `stop`, whose answer names where the run rests |
| `retry` | `retry` takes the lock; `iterate` while `retry_remaining`; then `stop` |
| `stop` | On a chain this page drives: the state becomes `stopping` and the chain ends after its batch. On a background run: `stop_cron` after a confirm, then the page loads again. On another page's run: the page loads again |
| `reset`, `background` | The request, then the page loads again with a GET (never a reload: a CSV controller is the response to its upload) |
| `dismiss` | The request; the Failed panel, the counts and the state are painted from the answer |

A 409 ends the task: the script drops its token without a `stop` (the lock is another driver's), paints the answer's `state` and shows its `message`. Any other failure hands the lock back with `stop`, sets `fault` and shows the server's message or `faultMessage`.

---

## Posts Table

Customize the admin posts list table with columns and filters.

### Basic Posts Table

**File:** `include/admin/projects-table.posts-table.php`

```php
namespace Lattice;

class Projects_Table extends Posts_Table {

    protected $post_type = 'project';

    public function columns(&$columns) {
        // Insert after title
        $this->insert_column(['account' => 'Account'], 'title');
        $this->insert_column(['status' => 'Status'], 'account');

        // Remove date column
        $this->remove_column('date');

        // Append at end
        $this->append_column(['priority' => 'Priority']);
    }

    public function sortable(&$columns) {
        $columns['account']  = 'account';
        $columns['priority'] = 'priority';
    }

    // Column renderers: column_{name}
    // Receives model instance via DI
    public function column_account(Project $project) {
        if ($account = $project->get_account()) {
            $url = $account->get_edit_url();
            echo "<a href='{$url}'>{$account->get_name()}</a>";
        } else {
            echo '—';
        }
    }

    public function column_status(Project $project) {
        $status = $project->get_field('project_status');
        echo "<span class='status-badge status-{$status}'>{$status}</span>";
    }

    public function column_priority(Project $project) {
        echo esc_html($project->get_field('priority') ?: 'Normal');
    }

    // Sort handlers: sort_column_{name}
    public function sort_column_account(Query_Vars $qv) {
        $qv->set_var('meta_key', 'project_account');
        $qv->set_var('orderby', 'meta_value_num');
    }

    public function sort_column_priority(Query_Vars $qv) {
        $qv->set_var('meta_key', 'priority');
        $qv->set_var('orderby', 'meta_value');
    }
}
```

### Column Methods

| Method | Description |
|--------|-------------|
| `columns(&$columns)` | Modify columns array |
| `sortable(&$columns)` | Define sortable columns |
| `column_{name}($model)` | Render column content (DI-enabled) |
| `sort_column_{name}($qv)` | Handle sorting for column |

### Column Helpers

```php
// Insert after a specific column
$this->insert_column(['new_col' => 'Label'], 'title');

// Insert before a specific column
$this->insert_column(['new_col' => 'Label'], 'title', false);

// Insert at position (0-indexed)
$this->insert_column(['new_col' => 'Label'], 2);

// Append to end
$this->append_column(['new_col' => 'Label']);
$this->append_column('new_col', 'Label');  // Alternative

// Prepend at start
$this->prepend_column(['new_col' => 'Label']);

// Remove column
$this->remove_column('date');
$this->remove_column('comments');
```

### Row Actions

Override `row_actions()` to add or modify the inline action links on each row. The post model is injected via DI.

```php
class Projects_Table extends Posts_Table {

    protected $post_type = 'project';

    public function row_actions (&$actions, Project $project) {
        $actions['archive'] = sprintf(
            '<a href="%s">Archive</a>',
            esc_url(add_query_arg([
                'action'     => 'archive_project',
                'project_id' => $project->get_id(),
                '_wpnonce'   => wp_create_nonce('archive_project_' . $project->get_id()),
            ], admin_url('admin-post.php')))
        );

        // Optionally remove an existing action
        unset($actions['trash']);
    }
}
```

`$actions` is passed by reference, so you can add, modify, or remove entries in place. The `row_actions_wrap()` method is hooked to `post_row_actions` (or `page_row_actions` for the `page` post type) automatically.

### Table Filters

Add dropdown filters above the table.

```php
class Projects_Table extends Posts_Table {

    protected $post_type = 'project';
    protected $filters = [
        'project_category' => 'taxonomy',           // Taxonomy dropdown
        'project_status'   => 'acf',                // ACF field dropdown
        'project_account'  => [                      // ACF with options
            'type'             => 'acf',
            'null_label'       => 'All Accounts',
            'null_label_prefix' => 'Filter by ',
        ],
    ];

    // Or via method
    public function filters() {
        return [
            'priority' => [
                'type'           => 'meta',
                'args'           => [
                    'field' => new \Lattice\Field\Select([
                        'options'     => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
                        'placeholder' => 'Priority',
                    ]),
                ],
                'query_callback' => [$this, 'filter_by_priority'],
            ],
        ];
    }

    public function filter_by_priority(Query_Vars $qv, $filter) {
        if ($filter['args']['value']) {
            $qv->add_meta_query([
                'key'     => $filter['args']['meta_key'],
                'value'   => $filter['args']['value'],
                'compare' => '=',
            ]);
        }
        return $qv;
    }
}
```

### Filter Types

| Type | Description |
|------|-------------|
| `taxonomy` | WordPress taxonomy dropdown |
| `acf` | ACF field-based filter (uses field choices) |
| `meta` | Custom meta field filter |
| `field` | Generic field filter |

A `meta` or `field` filter's `args` take `'field' => new X([...])`, an `Input` when absent. The table names the field after the filter (also the GET parameter), prints it unwrapped and, on a `meta` filter, sets its `value` from the request; strict throws for a `'field'` that is not a View. A `meta` filter's default `query_callback` queries the meta key `args['meta_key']`, defaulting to the filter's name; any `query_callback` reads the request value from `$filter['args']['value']`.

### Filter Options

```php
'field_name' => [
    'type'              => 'acf',           // taxonomy, acf, meta, field
    'name'              => 'field_name',    // Defaults to key
    'null_label'        => 'All Items',     // Empty option label
    'null_label_prefix' => 'Filter by ',    // Prefix for null label
    'null_value'        => '',              // Value for empty option
    'select_nice'       => false,           // Use Select_Nice component
    'value_callback'    => null,            // Transform selected value
    'query_callback'    => null,            // Custom query modifier
    'compare'           => '=',             // Meta compare operator
]
```

---

## Users Table

Customize the admin users list table.

### Basic Users Table

**File:** `include/admin/accounts-table.users-table.php`

```php
namespace Lattice;

class Accounts_Table extends Users_Table {

    public function columns(&$columns) {
        $this->insert_column(['company' => 'Company'], 'email');
        $this->insert_column(['projects' => 'Projects'], 'company');
    }

    public function column_company($output, User $user) {
        return esc_html($user->get_meta('company_name') ?: '—');
    }

    public function column_projects($output, User $user) {
        $count = count(Project::query([
            'meta_key'   => 'project_account',
            'meta_value' => $user->get_id(),
        ]));
        return "<span class='count'>{$count}</span>";
    }
}
```

### Row Actions

Same as Posts Table. Override `row_actions()` — the user model is injected via DI. Hooked to `user_row_actions`.

```php
class Accounts_Table extends Users_Table {

    public function row_actions (&$actions, Account $user) {
        $actions['impersonate'] = sprintf(
            '<a href="%s">Login as user</a>',
            esc_url(add_query_arg(['user_id' => $user->get_id()], admin_url('admin-post.php')))
        );
    }
}
```

### Users Table Filters

```php
class Accounts_Table extends Users_Table {

    protected $filters = [
        'account_status' => 'acf',
        'user_taxonomy'  => 'taxonomy',
    ];

    public function filters() {
        return [
            'has_projects' => [
                'type'           => 'field',
                'args'           => [
                    'field' => new \Lattice\Field\Select([
                        'options' => ['' => 'All', '1' => 'Has Projects', '0' => 'No Projects'],
                    ]),
                ],
                'query_callback' => [$this, 'filter_has_projects'],
            ],
        ];
    }

    public function filter_has_projects(Query_Vars $qv) {
        $value = $_GET['has_projects'] ?? '';
        if ($value === '1') {
            $qv->add_meta_query([
                'key'     => 'project_count',
                'value'   => 0,
                'compare' => '>',
            ]);
        } elseif ($value === '0') {
            $qv->add_meta_query([
                'key'     => 'project_count',
                'value'   => 0,
                'compare' => '=',
            ]);
        }
    }
}
```

---

## Terms Table

Customize taxonomy term list tables.

**File:** `include/admin/categories-table.terms-table.php`

```php
namespace Lattice;

class Categories_Table extends Terms_Table {

    protected $slug = 'category';  // Taxonomy slug

    public function columns(&$columns) {
        $this->insert_column(['icon' => 'Icon'], 'name');
    }

    public function column_icon($output, $column, $term_id) {
        $icon = get_field('category_icon', 'term_' . $term_id);
        return $icon ? "<i class='icon-{$icon}'></i>" : '—';
    }
}
```

---

## Meta Boxes

Add custom meta boxes to post edit screens.

### Basic Meta Box

**File:** `include/admin/project-details.meta-box.php`

```php
namespace Lattice;

class Project_Details_Box extends Meta_Box {

    protected $id       = 'project-details';
    protected $title    = 'Project Details';
    protected $screen   = 'project';           // Post type
    protected $context  = 'side';              // normal, side, advanced
    protected $priority = 'high';              // high, core, default, low

    public function render($post, $args) {
        $project = Project::get_instance($post->ID);
        ?>
        <p><strong>Account:</strong> <?= esc_html($project->get_account()?->get_name() ?: '—') ?></p>
        <p><strong>Status:</strong> <?= esc_html($project->get_field('project_status')) ?></p>
        <p><strong>Created:</strong> <?= esc_html($project->get_date('F j, Y')) ?></p>
        <?php
    }
}
```

### Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$id` | `string` | `'digitalis-metabox'` | Unique ID |
| `$title` | `string` | `'Lattice Metabox'` | Box title |
| `$screen` | `string\|array\|null` | `null` | Post type(s) or screen |
| `$context` | `string` | `'advanced'` | Position: normal, side, advanced |
| `$priority` | `string` | `'default'` | Priority: high, core, default, low |

### Conditional Meta Box

```php
class Premium_Box extends Meta_Box {

    protected $id     = 'premium-options';
    protected $title  = 'Premium Options';
    protected $screen = 'product';

    public function condition() {
        global $post;
        if (!$post) return false;

        // Only show for premium products
        $product = Product::get_instance($post->ID);
        return $product && $product->is_premium();
    }

    public function render($post, $args) {
        // ...
    }
}
```

### Meta Box with View

```php
class Stats_Box extends Meta_Box {

    protected $id     = 'project-stats';
    protected $title  = 'Statistics';
    protected $screen = 'project';
    protected $view   = Project_Stats_View::class;

    // View receives ($post, $args) as params
}
```

### Multiple Screens

```php
class Notes_Box extends Meta_Box {

    protected $id     = 'internal-notes';
    protected $title  = 'Internal Notes';
    protected $screen = ['project', 'document', 'invoice'];

    public function render($post, $args) {
        $notes = get_post_meta($post->ID, '_internal_notes', true);
        ?>
        <textarea name="internal_notes" style="width:100%"><?= esc_textarea($notes) ?></textarea>
        <?php
    }
}
```

---

## ACF Field Groups

Register ACF field groups programmatically.

### Field Group Feature

**File:** `include/acf/project-fields.feature.php`

```php
namespace Lattice;

class Project_Fields extends Feature {

    public function run() {
        add_action('acf/include_fields', [$this, 'register_fields']);
    }

    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key'      => 'group_project_details',
            'title'    => 'Project Details',
            'fields'   => $this->get_fields(),
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'project',
                    ],
                ],
            ],
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    protected function get_fields() {
        return [
            [
                'key'   => 'field_project_account',
                'label' => 'Account',
                'name'  => 'project_account',
                'type'  => 'post_object',
                'post_type' => ['account'],
                'return_format' => 'id',
            ],
            [
                'key'   => 'field_project_status',
                'label' => 'Status',
                'name'  => 'project_status',
                'type'  => 'select',
                'choices' => [
                    'draft'    => 'Draft',
                    'active'   => 'Active',
                    'complete' => 'Complete',
                ],
                'default_value' => 'draft',
            ],
            [
                'key'   => 'field_project_deadline',
                'label' => 'Deadline',
                'name'  => 'project_deadline',
                'type'  => 'date_picker',
                'return_format' => 'Y-m-d',
            ],
        ];
    }
}
```

### Loading from App

Create a centralized place to load all ACF configurations.

**File:** `include/acf/acf-config.feature.php`

```php
namespace Lattice;

class ACF_Config extends Feature {

    public function run() {
        add_action('acf/include_fields', [$this, 'include_fields']);
    }

    public function include_fields() {
        if (!function_exists('acf_add_local_field_group')) return;

        // Load all field groups
        $this->register_project_fields();
        $this->register_account_fields();
        $this->register_settings_fields();
    }

    protected function register_project_fields() {
        acf_add_local_field_group([
            'key'      => 'group_project',
            'title'    => 'Project Details',
            'fields'   => [
                $this->field_post_object('project_account', 'Account', ['account']),
                $this->field_select('project_status', 'Status', [
                    'draft'  => 'Draft',
                    'active' => 'Active',
                ]),
                $this->field_date('project_deadline', 'Deadline'),
            ],
            'location' => $this->location_post_type('project'),
        ]);
    }

    protected function register_account_fields() {
        acf_add_local_field_group([
            'key'      => 'group_account',
            'title'    => 'Account Details',
            'fields'   => [
                $this->field_text('company_name', 'Company Name'),
                $this->field_email('billing_email', 'Billing Email'),
                $this->field_image('company_logo', 'Logo'),
            ],
            'location' => $this->location_user_role('account'),
        ]);
    }

    protected function register_settings_fields() {
        acf_add_local_field_group([
            'key'      => 'group_settings',
            'title'    => 'Site Settings',
            'fields'   => [
                $this->field_text('company_name', 'Company Name'),
                $this->field_textarea('company_address', 'Address'),
            ],
            'location' => $this->location_options_page('theme-settings'),
        ]);
    }

    // Field helpers

    protected function field_text($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'text',
        ], $args);
    }

    protected function field_textarea($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'textarea',
            'rows'  => 4,
        ], $args);
    }

    protected function field_email($name, $label, $args = []) {
        return array_merge([
            'key'   => "field_{$name}",
            'label' => $label,
            'name'  => $name,
            'type'  => 'email',
        ], $args);
    }

    protected function field_select($name, $label, $choices, $args = []) {
        return array_merge([
            'key'     => "field_{$name}",
            'label'   => $label,
            'name'    => $name,
            'type'    => 'select',
            'choices' => $choices,
        ], $args);
    }

    protected function field_date($name, $label, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'date_picker',
            'return_format' => 'Y-m-d',
        ], $args);
    }

    protected function field_image($name, $label, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'image',
            'return_format' => 'id',
        ], $args);
    }

    protected function field_post_object($name, $label, $post_types, $args = []) {
        return array_merge([
            'key'           => "field_{$name}",
            'label'         => $label,
            'name'          => $name,
            'type'          => 'post_object',
            'post_type'     => $post_types,
            'return_format' => 'id',
        ], $args);
    }

    protected function field_repeater($name, $label, $sub_fields, $args = []) {
        return array_merge([
            'key'        => "field_{$name}",
            'label'      => $label,
            'name'       => $name,
            'type'       => 'repeater',
            'layout'     => 'table',
            'sub_fields' => $sub_fields,
        ], $args);
    }

    // Location helpers

    protected function location_post_type($post_type) {
        return [[['param' => 'post_type', 'operator' => '==', 'value' => $post_type]]];
    }

    protected function location_user_role($role) {
        return [[['param' => 'user_role', 'operator' => '==', 'value' => $role]]];
    }

    protected function location_options_page($page_slug) {
        return [[['param' => 'options_page', 'operator' => '==', 'value' => $page_slug]]];
    }

    protected function location_block($block_name) {
        return [[['param' => 'block', 'operator' => '==', 'value' => "acf/{$block_name}"]]];
    }
}
```

---

## ACF Blocks

> **Non-obvious:** All configuring properties (`$slug`, `$view`, `$block`, `$defaults`, `$fields`) must be **non-static** instance properties — same reason as `Route`: the framework reads them via `$this->$property`.

Create Gutenberg blocks with ACF fields.

### Basic Block

**File:** `include/blocks/testimonial.acf-block.php`

```php
namespace Lattice;

class Testimonial_Block extends ACF_Block {

    protected $slug = 'testimonial';
    protected $view = Testimonial_View::class;

    protected $block = [
        'title'       => 'Testimonial',
        'description' => 'Display a customer testimonial',
        'category'    => 'common',
        'icon'        => 'format-quote',
        'keywords'    => ['quote', 'review', 'testimonial'],
    ];

    protected $fields = [
        'quote' => [
            'label' => 'Quote',
            'type'  => 'textarea',
            'rows'  => 3,
        ],
        'author' => [
            'label' => 'Author Name',
            'type'  => 'text',
        ],
        'company' => [
            'label' => 'Company',
            'type'  => 'text',
        ],
        'image' => [
            'label'         => 'Photo',
            'type'          => 'image',
            'return_format' => 'id',
        ],
    ];
}
```

### Block with Custom Render

```php
class CTA_Block extends ACF_Block {

    protected $slug = 'cta';

    protected $block = [
        'title'    => 'Call to Action',
        'category' => 'common',
        'icon'     => 'megaphone',
    ];

    protected $fields = [
        'heading'     => 'Heading',
        'description' => ['label' => 'Description', 'type' => 'textarea'],
        'button_text' => 'Button Text',
        'button_url'  => ['label' => 'Button URL', 'type' => 'url'],
    ];

    public function view($params = []) {
        return "
            <div class='cta-block'>
                <h2>{$params['heading']}</h2>
                <p>{$params['description']}</p>
                <a href='{$params['button_url']}' class='btn'>{$params['button_text']}</a>
            </div>
        ";
    }
}
```

### Block with Computed Params

`render()` collects each field into `$params` (keyed by field name), then calls
`params(&$params)` before handing them to the view. Override `params()`
rather than `render()` whenever the block needs a value the editor didn't type —
a model resolved from something other than the picker, a derived label, a param
the view expects under a different name.

```php
class Latest_Post_Block extends ACF_Block {

    protected $view   = Post_Card::class;
    protected $fields = [
        'source' => ['type' => 'button_group', 'choices' => ['pick' => 'Choose', 'latest' => 'Most recent']],
        'post'   => ['type' => 'post_object', 'return_format' => 'id'],
    ];

    public function params (&$params) {

        if (($params['source'] ?? '') !== 'latest') return;

        $params['post'] = Post::query(['posts_per_page' => 1])[0] ?? 0;

    }
}
```

**Never assign `null` to a param whose view default is a model class string.**
DI reads `null` as "resolve from context" (`get_global_id()`), so a block that
resolved nothing would render whatever post the page is currently showing. Use
`0` — it fails `validate_id()`, the param lands `null` after injection, and
`pre_validate()` suppresses the view cleanly.

### Block Properties

| Property | Description |
|----------|-------------|
| `$slug` | Block identifier (acf/slug) |
| `$view` | View class name (a concrete `View`, not an instance); unset or `false` for the inline `view()` |
| `$block` | Block registration args |
| `$fields` | ACF field definitions |
| `$defaults` | Default values for view params |

### Block Override Points

| Method | When it runs | Use for |
|--------|--------------|---------|
| `get_fields()` | Field registration | Fields whose config is computed (dynamic `choices`) |
| `params(&$params)` | After field collection, before render | Computed / resolved params |
| `view($params)` | Render, when `$view` is unset | Inline markup |
| `render(...)` | Whole render | Last resort: you own `preview_empty_placeholder()` / `preview_neutralize_links()` |

---

## ACF Options Pages

Create settings pages with ACF fields.

### Standalone Options Page

**File:** `include/acf/theme-options.feature.php`

```php
namespace Lattice;

class Theme_Options extends Feature {

    public function run() {
        if (!function_exists('acf_add_options_page')) return;

        acf_add_options_page([
            'page_title' => 'Theme Settings',
            'menu_title' => 'Theme Settings',
            'menu_slug'  => 'theme-settings',
            'capability' => 'manage_options',
            'position'   => 30,
            'icon_url'   => 'dashicons-admin-customizer',
        ]);

        acf_add_options_sub_page([
            'page_title'  => 'Header Settings',
            'menu_title'  => 'Header',
            'parent_slug' => 'theme-settings',
        ]);

        acf_add_options_sub_page([
            'page_title'  => 'Footer Settings',
            'menu_title'  => 'Footer',
            'parent_slug' => 'theme-settings',
        ]);

        add_action('acf/include_fields', [$this, 'register_fields']);
    }

    public function register_fields() {
        // Header fields
        acf_add_local_field_group([
            'key'      => 'group_header_options',
            'title'    => 'Header Options',
            'fields'   => [
                ['key' => 'field_logo', 'label' => 'Logo', 'name' => 'site_logo', 'type' => 'image'],
                ['key' => 'field_phone', 'label' => 'Phone', 'name' => 'header_phone', 'type' => 'text'],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-header']]],
        ]);

        // Footer fields
        acf_add_local_field_group([
            'key'      => 'group_footer_options',
            'title'    => 'Footer Options',
            'fields'   => [
                ['key' => 'field_copyright', 'label' => 'Copyright', 'name' => 'footer_copyright', 'type' => 'text'],
                ['key' => 'field_social', 'label' => 'Social Links', 'name' => 'social_links', 'type' => 'repeater',
                    'sub_fields' => [
                        ['key' => 'field_platform', 'label' => 'Platform', 'name' => 'platform', 'type' => 'text'],
                        ['key' => 'field_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                    ],
                ],
            ],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-footer']]],
        ]);
    }
}
```

### Reading Options

```php
// Get option field
$logo = get_field('site_logo', 'option');
$phone = get_field('header_phone', 'option');

// In a View
class Header_View extends View {

    protected static $defaults = [
        'logo'  => null,
        'phone' => null,
    ];

    public function params(&$p) {
        $p['logo']  = $p['logo'] ?: get_field('site_logo', 'option');
        $p['phone'] = $p['phone'] ?: get_field('header_phone', 'option');
    }
}
```

An options page exposed as a resource ([RESOURCES.md](./RESOURCES.md#option-pages)) cannot show these global reads the values of a dry run. Code that should see what a change would do reads through the page's model, `$page->get_field('site_logo')`, which an unsaved copy answers.

---

## Quick Reference

### File Naming

| Pattern | Class Type |
|---------|------------|
| `name.admin-page.php` | Admin_Page |
| `name.admin-sub-page.php` | Admin_Sub_Page |
| `name.posts-table.php` | Posts_Table |
| `name.users-table.php` | Users_Table |
| `name.terms-table.php` | Terms_Table |
| `name.meta-box.php` | Meta_Box |
| `name.acf-block.php` | ACF_Block |

> Admin_Page instances are cached by `$slug`; Meta_Box instances by `$id`. Both support `::get_instance()` lookup.

### Admin Page Properties

```php
protected $slug       = 'page-slug';
protected $title      = 'Page Title';
protected $menu_title = 'Menu Title';
protected $capability = 'manage_options';
protected $icon       = 'dashicons-admin-generic';
protected $position   = 30;
```

### Posts Table Column Methods

```php
public function columns(&$columns) { }           // Modify columns
public function sortable(&$columns) { }          // Mark sortable
public function column_{name}($model) { }        // Render cell
public function sort_column_{name}($qv) { }      // Sort handler
```

### Meta Box Properties

```php
protected $id       = 'box-id';
protected $title    = 'Box Title';
protected $screen   = 'post_type';  // or ['type1', 'type2']
protected $context  = 'normal';     // normal, side, advanced
protected $priority = 'default';    // high, core, default, low
```

### ACF Location Patterns

```php
// Post type
[['param' => 'post_type', 'operator' => '==', 'value' => 'project']]

// User role
[['param' => 'user_role', 'operator' => '==', 'value' => 'editor']]

// Options page
[['param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings']]

// Block
[['param' => 'block', 'operator' => '==', 'value' => 'acf/my-block']]

// Taxonomy term
[['param' => 'taxonomy', 'operator' => '==', 'value' => 'category']]
```
