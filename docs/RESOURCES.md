# Digitalis Framework: Resources

Read and change models through one layer, the way wp-admin does: REST (`lattice/v1`) and WP-CLI (`wp lattice`) over one service.

---

## Table of Contents

- [Overview](#overview)
- [Loading](#loading)
- [Declaring a Resource](#declaring-a-resource)
- [Fields](#fields)
- [Writing](#writing)
- [Actions, Views and Consequences](#actions-views-and-consequences)
- [Option Pages](#option-pages)
- [The Version](#the-version)
- [The Journal and Undo](#the-journal-and-undo)
- [Transports](#transports)
- [Refusals](#refusals)
- [Policy](#policy)
- [Limits](#limits)

---

## Overview

A resource exposes one model. Every write goes through the model's own save steps, so nothing writes raw meta, and every change through any side gets:

- **a dry run** that answers the diff and its consequences without writing anything;
- **a version** that refuses a write based on a stale read;
- **a journal row** with the values before and after, and an undo;
- **a why**, required through REST and the CLI.

Actions (a model's buttons) are the same code whether wp-admin, REST or the CLI calls them.

```
include/features/lattice/resources/
├── resources.feature.php             Lattice_Resources: hooks, routes, CLI, the Changes screen
├── resources.service.php             Resources: guide, list, get, create, update, act, view, undo
├── model-resource.abstract.php       Model_Resource: subclass one per model
├── resource-field.class.php          Resource_Field: one field, described and checked
├── resource-change.class.php         Resource_Change: a write in progress
├── options-page-resource.abstract.php
├── options-page.model.php
├── options-page-record.class.php
├── journal/
└── routes/
```

`Has_Version` (`include/wordpress/`) and `Has_Unsaved_Copies` (`include/acf/`) stay in core: they are model traits.

---

## Loading

Load the feature before your own classes, since your resources extend `Model_Resource`:

```php
public function load () {

    $this->load_feature('lattice/resources');

    parent::load();

}
```

The feature creates its journal table, schedules a daily prune and registers its routes, CLI commands and the Changes screen (Tools → Changes).

---

## Declaring a Resource

The model uses both traits; the resource sits beside it as `offer.model-resource.php`.

```php
class Offer extends \Digitalis\Post {

    use \Digitalis\Has_Version, \Digitalis\Has_Unsaved_Copies;

    public function forget_derived () : void { $this->flush(); }

}

class Offer_Resource extends \Digitalis\Model_Resource {

    protected static $name        = 'offer';
    protected static $model_class = Offer::class;
    protected static $label       = 'Offer';

    const ACTION_ONLY = ['active'];

    public function get_computed () : array {

        return ['stages.stage_key' => null, 'link_key' => fn ($offer) => $offer->get_link_key()];

    }

    public function get_lock ($offer) : ?string {

        return $offer->is_active() ? 'manage_options' : null;

    }

    public function before_write ($offer) : mixed { return $offer->get_date_snapshot(); }

    public function after_write ($offer, mixed $before, string $why) : void { $offer->finish_save($before, $why); }

}
```

`forget_derived()` clears whatever the model remembers from its fields: a dry run's copy and a write both call it.

**Model code must read fields through `$this->get_field()`.** A dry run's unsaved copy answers `get_field()` from the values not yet written; a global `get_field()`, `get_post_meta()` or a model fetched again by id reads the saved record, and the consequences will not see the change.

---

## Fields

| Kind | Declared by | Read and written |
|------|-------------|------------------|
| ACF | The model's field groups | `get_field()` / `update_field()` |
| Model | `get_model_fields()`: the `POST_FIELDS` a resource opts into | `get_{name}()` / `set_{name}()` then `save()`, or the methods or closures `'get'` and `'set'` give |
| Computed | `get_computed()`: `'dotted.path' => null` inside ACF, `'name' => fn ($model)` outside it | Read only |

`POST_FIELDS` picks from `title`, `status`, `content`, `excerpt`, `featured_image` and `terms` (default: title and status). Others are added in `get_model_fields()`:

```php
const POST_FIELDS = ['title', 'status', 'content', 'terms'];

public function get_model_fields () : array {

    return parent::get_model_fields() + ['subtitle' => ['type' => 'text', 'label' => 'Subtitle']];

}
```

A model field's setter must only stage: a dry run calls it on the unsaved copy.

- **Content** is saved as the acting account, so WordPress filters its markup (kses) exactly as wp-admin would for that account.
- **Terms** are `taxonomy => term ids`; a taxonomy left out keeps its terms. A write that changes a term may write the fields of the groups that term brings in.

- **Groups.** The guide lists the post type's groups and those that depend on one of its terms, marked `applies_when` with ACF's rules. A record reads and writes the groups its own edit screen shows; a field it does not show is refused.
- **References.** A post, term, user or file field takes an id; `0` means none.
- **Flexible rows** are `layout` and the layout's fields by name, plus `disabled: true` and `label` (ACF's renamed row) where set, read in any request. A row sent without them is enabled and unnamed, since rows have no identity to keep a flag by. A layout's own sub-fields named `layout`, `disabled` or `label` are not reachable.
- **Checks.** ACF validates each value (`acf_validate_value()`: lengths, min and max, rows, a site's filters). The layer adds what ACF does not check on the server: choices, that a referenced post, term, user or file exists, a date's stored format, a repeater's computed row keys. A field its conditions hide is never required.
- **Action-only** (`ACTION_ONLY`, dotted paths): changed only by an action.
- **Locked** (`get_locked_fields($model)`, `name => why`): refused in the record's present state; other fields in the same write go through. `get_lock($model)` is the record-wide form: a capability updating needs.
- **Secrets** (`SECRET`, dotted paths, and every ACF `password` field): written, never read back. Reads, diffs and journal rows carry `[set 1a2b3c4d]`, a hash keyed with the site's salt, so a change still shows; a mask sent back is the value it stands for. Undo refuses them.

---

## Writing

An update runs in this order:

1. Refusals: the record, the account (`edit_post` and `get_lock()`), the version, the values, the why, locked fields.
2. The dry run: an unsaved copy with the new values, its diff and consequences, and their fingerprint. `dry_run` stops here.
3. `confirm`, when sent, must match the fingerprint, so what was reviewed is what is done.
4. The version is claimed; `before_write()`; ACF fields, then model fields and `save()`; `after_write()`; the version is finished.
5. One journal row.

The service writes ACF fields directly, so `acf/save_post` does not fire: `after_write()` runs what the model's `acf/save_post` handlers would. A write that changes nothing answers `changed: false` and raises nothing.

---

## Actions, Views and Consequences

```php
public function get_actions () : array {

    return [
        'activate'   => ['label' => 'Make Active', 'capability' => 'manage_options', 'when' => fn ($o) => $o->is_active() ? 'It is Active.' : '', 'sets' => ['active' => 1], 'confirm' => true, 'undo' => 'deactivate'],
        'deactivate' => ['label' => 'Make not Active', 'capability' => 'manage_options', 'sets' => ['active' => 0], 'undo' => 'activate'],
        'copy'       => ['label' => 'Copy', 'changes_record' => false],
        'revoke'     => ['label' => 'Revoke a link', 'input' => ['email' => 'email']],
    ];

}

public function act_activate ($offer, array $input, Resource_Change $change) { $offer->activate($change->why); return $offer; }
```

| Key | Meaning |
|-----|---------|
| `capability` | Needed beyond updating the record |
| `when` | `fn ($model)` answering why it cannot run now, or `''` |
| `input` | `name => 'string' \| 'email' \| 'int'` |
| `sets` | Fields the unsaved copy gets, so the dry run shows the action's effect |
| `confirm` | Requires the dry run's fingerprint |
| `changes_record` | `false` for one that makes a new record (`copy`) and needs no version |
| `undo` | The action that reverses it |

`act_{name}()` returns the model, a new record, or a `WP_Error`. An error that changed nothing keeps its own code.

Views (`get_views()`) are named reads: `'prices' => fn ($offer) => …`. Consequences (`get_consequences(Resource_Change $change)`) compare `$change->before` (the saved record, null on create) with `$change->after` (the unsaved copy) and answer `[['kind', 'label', 'items']]`.

### Presenting a change

The API answers flat paths (`stages.1.ends`). For people, `$change->present()` (a dry run) and `$resource->present_changes($before, $after, $model)` (a journal row) answer lines of `label`, `before` and `after`: ACF's labels (`Stages › Row 2 › Date and time`), rows added, removed or changed one by one, titles, term and user names and file names for what ids point at, choice labels, and counts once a list is long. The Changes screen uses it.

---

## Option Pages

One ACF options page is one record, its slug its id:

```php
class Hub_Settings_Resource extends \Digitalis\Options_Page_Resource {

    protected static $name = 'hub_settings';
    protected static $page = 'somm-hub';

}
```

Fields come from the groups on that page; the capability is the page's; create is refused. The version is kept in an option. Writes run ACF's per-field filters (`acf/pre_update_value`), not `acf/save_post`: a page whose save does more (sends mail, say) opts into it in `after_write()`.

---

## The Version

`Has_Version` keeps a count in meta (`_lattice_version`; override `get_version_key()` to keep an existing one). Every save raises it twice: once as it starts, once as it finishes, so anyone who read mid-write is stale too. A restored revision raises it once more, after its fields come back. `raise_version()` is a single step for raises outside a save. The count is only ever compared, never counted.

Relationship sync (`ACF\Bidirectional_Relationship`) writes a partner's meta without saving it, so the feature raises the partner's version itself.

wp-admin's classic editor and options pages carry the version in a hidden field (`lattice_version`) and claim it, so a save from a page opened before another change is refused before anything is written, naming the change from the journal ("Jamie, through the API, 30 Oct 14:02"). The editor claims at `admin_action_editpost`, after checking the nonce and `edit_post` itself, since WordPress checks them only afterwards; an options page at `acf/save_post` 5. Preview compares the version and never raises it: it saves one's own draft while the form stays open. Autosave neither claims nor raises. Copies never inherit it (`lattice.post.duplicate.exclude_meta`).

---

## The Journal and Undo

`{prefix}lattice_journal`: one row per change from any side, with who, when, `via` (`rest`, `cli`, `admin`, `other`), the why, the values before and after, and the versions. wp-admin's saves of a resource's records are journaled by hooks; the service's own writes stand aside from those hooks. Rows are kept two years (`lattice.journal.keep_days`).

A restored revision is one row, `restore`. WordPress saves the post's columns, then its `wp_restore_post_revision` listeners bring the fields back (its own, ACF's, a site's); the feature journals the save as it would any, then completes that row at priority 100, once the fields are in (`Journal::amend()`, used only for a row written earlier in the same request). Rows are otherwise never changed.

Undo is a write of its own:

- an update or restore row writes back the fields it changed, while each still holds that row's after (any save raises the version, so fields are compared, not versions);
- an action row runs its `undo` action, while the record is at that row's version;
- create, an action without `undo`, and secrets are refused.

---

## Transports

| REST (`lattice/v1`) | CLI | Service |
|---------------------|-----|---------|
| `GET guide` | `wp lattice guide` | `guide()` |
| `GET journal` | `wp lattice journal` | `journal()` |
| `POST journal/{id}/undo` | `wp lattice undo <row>` | `undo()` |
| `GET {resource}` | `wp lattice <resource> list` | `list()` |
| `POST {resource}` | `wp lattice <resource> create` | `create_record()` |
| `GET {resource}/{id}` | `wp lattice <resource> get <id>` | `get()` |
| `PATCH {resource}/{id}` | `wp lattice <resource> update <id>` | `update()` |
| `POST {resource}/{id}/{action}` | `wp lattice <resource> <action> <id>` | `act()` |
| `GET {resource}/{id}/{view}` | `wp lattice <resource> view <id> <view>` | `view()` |

`version`, `why`, `dry_run` and `confirm` travel with the values (CLI: `--version`, `--why`, `--dry-run`, `--confirm`, values in `--input=<file|->`). The CLI needs `--user`. `guide`, `journal`, `undo` and `dev` are not resource names.

---

## Refusals

`code`, `message`, `data`, with the HTTP status:

| Code | Status | Means |
|------|--------|-------|
| `invalid` | 400 | `data.problems`; nothing changed |
| `forbidden` | 403 | `data.needs` names the capability |
| `not_found` | 404 | No such resource, record, action, view or row |
| `stale` | 409 | `data.version` is now; or a `confirm` no longer matches |
| `locked` | 409 | `data.reason`; locked fields in `data.fields` |
| `not_saved` | 500 | Part was written: read it again |

---

## Policy

WordPress's capabilities decide: the post type's for reading, creating and updating (`read_post` and `edit_post` against the record), publishing needs `publish_posts`, and each action its own. `lattice.resources.allow` (`$allowed, $user, $resource, $operation`) can only refuse.

Another feature has its say over single records:

- `lattice.resources.refuse` (`$reason, $resource, $model, $operation`): a non-empty reason refuses that record as `locked`. `$operation` is `read`, `update` or an action's name.
- `lattice.resources.query` (`$args, $resource`): the list's query, to leave records out.
- `lattice.resources.journal` (`$journal, $resource, $model`): `false` keeps a record's changes out of the journal.

---

## Limits

- A flexible field inside a repeater, group or another flexible field is read through its parent, which drops disabled rows outside wp-admin, so a field holding one is locked to the layer's writes.
- An editor save claimed and then stopped before WordPress writes (another plugin's `wp_die` on `admin_action_editpost`) leaves the version raised once: that form is refused next time and its page needs reloading.
- A restore's row is written as `update` and completed as `restore` milliseconds later, in the same request: only a journal read at that moment sees it half done.
- Undo never touches action-only fields, so undoing a restore leaves anything the restore's listeners switched by action as they left it (an Email it switched off stays off).
- Only the classic editor and options pages refuse a stale save. The block editor, Quick Edit and Bulk Edit raise the version, so the layer refuses whatever read before them, but are not themselves refused.
- An option page's dry run cannot see code that reads its options through the global `get_field($name, 'option')`.
- Consequences are written by each resource: a resource that puts a secret's value in one leaks it there.
- Content is filtered as the acting account. For an account without `unfiltered_html`, WordPress's kses turns `&` into `&amp;` inside ACF block data, as it does in wp-admin: an approval by such an account carries that into the live content. WordPress also re-serialises block attributes (`\"` to `\u0022`), so compare content with what WordPress stored, not the string sent.
- Long text is presented shortened; an edit beyond what is shown reads "Changed". `lattice.resources.present` (`$lines, $resource, $before, $after, $model`) lets another feature add to a presented change, a preview link say.
