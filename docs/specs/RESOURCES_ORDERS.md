# Spec: WooCommerce orders as resources

**Status:** Ready
**Date:** 2026-11-10

Lets a site declare a resource over WooCommerce orders (`shop_order`) stored in HPOS with sync off, with the guarantees a post resource has: versions, the journal, undo, trash, dry runs, change sets and the policy filters. First consumer: `Order_Resource` in digitalis-co ([PROJECT_CHANNEL.md](../../../docs/PROJECT_CHANNEL.md), step 5).

---

## Approach

Orders arrive as `Order_Model_Resource`, a subclass of `Model_Resource` as `Options_Page_Resource` is, plus a feature that drives the journal from WooCommerce's hooks. The core stays shared.

- The post assumptions are narrow: `get_post_type()` is read 11 times in `resources.service.php` and 8 in `model-resource.abstract.php`, `run_move()` is post-only, and change sets find a resource by post type. Fields, checks, the dry run, fingerprints, refusals, policy, routes, the CLI and journal rows do not care where a record lives; undo does only where the table below says.
- Branching inside `Model_Resource` would split every storage method in two (an HPOS order never touches a post) and run every post resource through code rewritten for orders. A subclass that is wrong breaks only orders.
- Users and terms become further subclasses on the same seam.

**What carries over, verified on digitalis.ca:** ACF 6.8 decodes an order id to `woo_order` when its post is a `shop_order_placehold`, finds the order field group by `post_type == shop_order`, and reads and writes the orders meta table. An ACF write to an order is itself an order save. `WC_Order::get_status()` answers without `wc-` and answers `trash`.

**Capabilities** map through the order's placeholder post only with sync off. For the placeholder's author, `edit_shop_order` maps to `edit_shop_orders`, `delete_shop_order` to `delete_shop_orders` and `read_shop_order` to `read`. For anyone else, edit maps to `edit_others_shop_orders` and delete to `delete_others_shop_orders` (with the `_published` or `_private` variants by status); read of a non-public status falls back to the edit caps. An agent that creates an order becomes its author, so an order resource's perimeter rests on `lattice.resources.refuse`, never on the capability.

**Scope:** `shop_order` only. Refunds (`order_refund`) and subscriptions (`subscription`) fire their own object hooks and delete hooks, so a resource over either is a later port.

---

## Where it is proven

On digitalis.ca, which already runs what the port meets: WooCommerce 11.1 with HPOS on and sync off, ACF Pro 6.8, Subscriptions, the order cache and Redis. A copy elsewhere would drift from it.

- **Project is the only resource on this server.** lattice-dev runs framework v1, which has no resources layer, and change sets, proposals and options page resources are not loaded on digitalis.ca. Their paths are linted and read, not run. The regression is a script that drives a scratch project through every operation and compares its output and journal rows before and after a change.
- **Framework edits are live when saved**, since the plugin runs from its submodule. Lint before each save; smoke the whole site after each step.
- **Scratch orders** are drafts with no customer and no subscription, force-deleted after. Their journal rows carry a marker in `why` so they can be found and removed.

---

## Phase 1: the seam in the core

Every existing resource answers each new question as it behaves today. The base default is today's behaviour for its `get_post_type()`: a post answer when it has one, and the options page, change set and proposal set answer (no trash, nothing to clear) when it is `''`.

| Seam | Replaces | Post answer | Order answer (phase 3) |
|------|----------|-------------|------------------------|
| `has_trash()` | the guard in `move_to()`, `is_trashed()`, create's undo, the readability of a gone record's rows, the `no_post_type` step flag | yes | yes |
| `move($model, $target)`, answering whether it landed, and `has_moved($model, $target)` | `run_move()`'s `wp_trash_post()`, `wp_untrash_post()`, the `wp_untrash_post_status` filter, the `landed` check through `get_post_status()` and the `TRASH_META` restore when a move does not land | as today | `delete(false)` and `untrash()` |
| `get_previous_status($model)` | `TRASH_META` read with `get_post_meta()`, and the `draft` fallback | `_wp_trash_meta_status`, else `draft` | WooCommerce's, without `wc-`; `pending` when it is missing or not a valid status, as `untrash_order()` tests |
| `get_required_status()` | `check_required()` keyed on `publish` | `publish` | the resource's, `null` for none |
| `clear_record_cache($id)` | `clean_post_cache()` in `refresh()`, set walks, undo and `run_move()` | post cache | the model's `clear_cache()` |
| `get_acf_screen()` | the `post_type` screen in `get_fields_for()` and `get_type_groups()` | its post type | `shop_order` |
| `owns_record($id)`, through `Resources::get_resource_for_record($id)` asking each resource | `get_resource_for_post_type(get_post_type($id))` in `mark_broken` | by post type | by `OrderUtil::get_order_type()` |
| `forget_set_key($model)` | the post-only key removal in `change-set-resource.class.php` | `delete_post_meta` | direct SQL, so the journal hooks never see it |

The publish checks (`refuse_publish()`, and untrash to `publish`, `future` or `private`) stay capability checks on `get_capability('publish')`. An order never takes those statuses. The reference check in `resource-field.class.php` (a `page_link` limited by post type) moves to phase 3, which knows the order types.

Also in the core:

- **`run_move()` marks the write with `writing_key()`**, not the bare id, so the order hooks see the layer is writing.
- **`find_by_set_key()`** keeps its post-type guard in the base; the order subclass overrides it in phase 3.
- **A field's `validate` receives the record and whether it is restoring**: `fn ($value, $path, $model, $restoring)`. `Resource_Field::check()` already takes `$restoring` and gains `$model`; `take_values()` and the undo path pass the record (null on create). Items are checkable only against their order, and undo restores an item whose id no longer exists. Two-argument closures keep working.
- **kses for model fields, by declaration.** The direct write path filters ACF values only (`filter_values()`, skipped for a user with `unfiltered_html`); model fields are left to WordPress's column save. A model field declaring `'kses' => true` has every string leaf filtered in `take_values()` and the undo path, before the checks and the dry run, and reports what it stripped in `removed` as the ACF path does. Opt-in keeps existing resources unchanged.
- **Change sets:** `mark_broken` also hooks `woocommerce_before_delete_order` and resolves through `get_resource_for_record()`.
- **The guide** reports the order type where a post resource reports `post_type`.
- **Left post-gated, harmless for orders:** `get_term_rules()`, `filter_columns()` and `raise_partner_version()`. A relationship write to an order is an ACF write and so an order save, which phase 4 journals.

**Acceptance:** the scratch project's run (create, update, stale, dry runs, trash, untrash, an outside write, undo of each, delete) answers and journals identically before and after, apart from ids and times. A field with a four-argument `validate` sees the record and `$restoring`; a two-argument one still works. As a user without `unfiltered_html`, a model field with `'kses' => true` has a `<script>` stripped at any depth and reported, and a plain model field is untouched. Done 2026-11-10.

---

## Phase 2: the order model's contract

Following the existing split (ACF on the base, versioning on the site's model, as `Post` and `Project` have it):

- **`Digitalis\Order` uses `Has_ACF_Fields`.** `get_acf_id()` is the order id; ACF does the decoding.
- **The site's order model uses `Has_Order_Version` and `Has_Unsaved_Copies`.** `Has_ACF_Fields` and `Has_Unsaved_Copies` both define `get_field()`, and the copy's `parent::get_field()` must reach the ACF one. `Has_Order_Version` wraps `Has_Version` and overrides its storage: a trait's methods override inherited ones, so overrides on the base `Order` would lose to a subclass's `use Has_Version`. It is required in `load.php`, which lists the framework's files by hand.
- **`clear_cache()`** removes the order from `OrderCache` and calls `OrdersTableDataStore::clear_cached_data()`. The order cache is what serves `wc_get_order()`; the datastore cache is off on digitalis.ca, where `clear_cached_data()` alone does nothing.
- **The version gets its own storage seam in `Has_Version`**: `read_version()`, `swap_version($seen, $next)` and `drop_version($seen)`, defaulting to today's meta calls. `Has_Order_Version` overrides them with direct SQL on `OrderUtil::get_table_for_order_meta()`, judged by exactly one row affected, and calls `clear_cache()` after each. Every other meta key keeps going to the `WC_Order` through `__call`, so the existing `$order->get_meta()` calls keep their unsaved values and `$context`.
- **Both ends are atomic.** The first claim (posts use a unique `add_meta`; the orders meta table has no unique key on order and key) inserts under `GET_LOCK('lattice_version_<id>', 0)` after checking again inside the lock, and fails if the lock is not acquired. `drop_version($seen)` deletes only where the value is still `$seen`, so `restore_version()` cannot wipe a claim that landed after its read.
- **`get_version()`** is the lattice version on the model. `WC_Order::get_version()` (the WooCommerce version that created the order) stays reachable as `Order::get_woo_version()`.
- **The unsaved copy wraps a freshly loaded `WC_Order`.** `WC_Abstract_Order::__clone()` is empty, so a cloned order shares its meta and item objects with the record and a dry run's staging would land on it.
- **`stage_model_values()` reloads first** (phase 3, in the resource). `commit()` writes each ACF field (each its own order save) before staging, so the model's `WC_Order` still holds the old meta; reloading hands status and save listeners the new values.
- **Staging:** a `WC_Order` stages its props and meta until `save()`. What a site adds outside them (an order's items) stages on the model and `save()` applies it after the props.
- **`Order::save()` reports failure.** `WC_Order::save()` catches every `Exception`, logs it, adds an "Error saving order" note and answers the id; `woocommerce_after_order_object_save` fires inside the same `try` after the write. While the layer writes an order (phase 3), the resource counts `woocommerce_before_order_object_save` and `woocommerce_after_order_object_save` for its id, which covers ACF's own saves inside `commit()`. A before without its after is a failed save, and `save()` answers a `WP_Error`.
- **`reload()`** refetches past the order cache. `WC_Data::delete()` zeroes the object's id even when it only trashes, so a trash is followed by a reload.
- **The version and set key stay with their order:** excluded from WooCommerce Subscriptions' copies (`wc_subscriptions_object_data`) as from post duplicates.

**Acceptance:** four processes claim the same version on one order at once with the object cache on: one wins; the same holds for an order with no version yet. A `restore_version()` that a rival's claim overtook leaves the rival's version, on both its swap and its delete path. A clone shares no meta with its original. A save made to fail answers a `WP_Error`. The Project regression is unchanged. Done 2026-11-10. The ACF save failure and the dry run are proven in phase 3, which gives them a resource to run through.

---

## Phase 3: `Order_Model_Resource`

`include/features/lattice/resources/order-model-resource.abstract.php`. Depends on phases 1 and 2.

| Method | Answer |
|--------|--------|
| condition | registers only when HPOS is authoritative and sync is off; elsewhere the resource does not exist |
| `get_post_type()` | `''`, so the post hooks never claim an order |
| `get_order_type()` | `shop_order` |
| `find($id)` | the order when `OrderUtil::get_order_type($id)` is `shop_order` and its post is a `shop_order_placehold`. A site migrated with sync off keeps full `shop_order` posts, for which ACF would read postmeta |
| `owns_record($id)` | as `find()` |
| `find_by_set_key()` | a lookup on the orders meta table |
| `query($args)` | `WC_Order_Query` pinned to `shop_order`: `status` to `wc-` statuses, `search` to `s`, `page` and `per_page` to `paged` and `limit`, newest modified first. An empty `status` lists every status but `trash`, `checkout-draft` and `auto-draft`. `lattice.resources.query` then receives `WC_Order_Query` arguments, so a policy filter branches on the resource |
| `get_model_fields()` | `status`, read by `get_status()`, staged by `set_status()`, its choices `wc_get_order_statuses()` without `wc-` and without `checkout-draft`. `move_to()` stages `['status' => $target]` and undo reads `after.status`. Sites add theirs |
| `make_model()` / `insert_model()` | an unsaved order in `CREATE_STATUS` (`pending` unless the resource says), saved with its meta. `Order::save()` gives a new model its id, as `Post::save()` does |
| `move($model, $target)` | `$order->delete(false)` then a reload; `$order->untrash()`. Untrash saves and replays the status hooks with only emails suppressed, so a site's status listeners rerun on the undo of a trash |
| `get_capability()` | the generic `read_post`, `edit_post` and `delete_post` already map through WooCommerce's helper; `edit_shop_orders` to list, create and publish |
| `get_edit_url()` | `OrderUtil::get_order_admin_edit_url()` |
| `summarise()` | title, status and `modified` |
| `get_post_fields()` | none |
| `before_write()` / `after_write()` | count the order's before and after saves at `PHP_INT_MIN`, so a listener that throws ahead of the counter still counts; an imbalance throws and the row is marked partial. Subclasses call parent |
| `stage_model_values()` | reloads first, except on an unsaved copy |

A `page_link` limited to `shop_order` matches an order: the reference check reads an HPOS placeholder's order type.

**Scoped credentials** ([CREDENTIALS.md](../CREDENTIALS.md)) reach orders through `get_conditions()` and `get_facts()`, which the base answers for posts only. An order's conditions are `draft` (its `DRAFT_STATUSES`, `pending` unless the resource says) and each status by name, a trashed order judged by the status it had; `own` is the placeholder post's author, whoever made the order. So a key with `writes` on `records: draft` makes and edits drafts, trashes and restores them, and cannot move one out of draft or touch any other order. Proven with such a key set as the request's credential.

Orders take part in change sets and proposal sets as any resource does, through `get_proposable()`. Draft proposals do not apply: `Has_Proposals` is an opt-in trait whose methods take a `Post`.

**Acceptance:** a minimal order resource in digitalis-co (the start of `Order_Resource`) lists, reads, creates, updates, trashes and untrashes through CLI and REST, each write journaled, each with a working undo. A dry run leaves the order, its items and its cache untouched. A change set with two order steps applies, and deleting one of its orders marks it broken.

Done 2026-11-10 with `Digitalis_Co\Order_Resource` (`erp-draft`), all but the change set: change sets are not loaded on digitalis.ca, so that path is read, not run. The CLI and in-process REST each ran create, read, list (by status and the Trash), update, stale, invalid status, trash, update in the Trash, untrash and undo of each, one row per write. A dry run of an update left the order row, its meta, its items and its cached object identical; a dry create made no order. An ACF save made to throw answered `not_saved` with a partial row. The Project regression is unchanged.

---

## Phase 4: the journal feature for orders

`lattice/resources-orders`: `$requires = ['lattice/resources']`, `instance_condition()` true only when HPOS is authoritative and sync is off. Depends on phase 3.

The version follows the post feature. An outside write raises it once per save and never claims, so a raise that loses a race has still moved the version, which is all it is for. Only the editor's form claims, and its claim finishes when the request settles. Every row carries the version the order holds when it settles.

| Hook | Does |
|------|------|
| `load-woocommerce_page_wc-orders`, priority 5 | On an `edit_order` POST: claims the editor's version or refuses as stale (`wp_die`), and takes "before". It runs ahead of `handle_order_update()`, so a refused save has written nothing, taxonomies included |
| `woocommerce_order_actions_start` | Prints the version field on the order screen |
| `woocommerce_before_order_object_save` | Takes "before" for a write outside the layer, unless this request already has |
| `woocommerce_after_order_object_save` | Raises the version unless claimed. A new order (no id at its before save) is a create from here, whatever its items' saves took first |
| `woocommerce_before_order_item_object_save`, `woocommerce_before_delete_order_item` | Take "before" for item edits, finding the order by `$item->get_order_id()` and by `wc_get_order_id_by_order_item_id()` respectively |
| `woocommerce_before_trash_order`, `woocommerce_trash_order` | Take "before" and raise the version, when the order was not already in the Trash: an HPOS trash is a direct update, not a save |
| `woocommerce_untrash_order` | Fires before the restore: takes "before"; the untrash's own save raises |
| `woocommerce_before_delete_order` | Writes the `deleted` row; fires on a forced delete only |
| `action_scheduler_after_execute`, `shutdown` | Settle every order touched since the last settle: finish a claimed version, raise one changed only through its items (adding an item by AJAX with no unsaved item edits saves no order), re-read "after" and write its row with the settled version. The action is `create`, `trash` or `untrash` from the statuses, else `update`; an unchanged "after" writes none. `action_scheduler_before_execute` settles first, so what came before an action is not the action's |

How it holds together:

- **State is keyed by order id and lives until the next settle.** An edit-screen save is several sequential order saves (the items box at 10, order data at 40, `handle_metadata_changes()` after, then whatever status listeners save from `status_transition()`), and each ACF field nests its own save inside them. So "before" is taken once at first touch, each save only raises, and the settle reads "after" once and writes the row. Settling per Action Scheduler action keeps a batch of renewals from merging into one row.
- **"Before" and "after" come from a fresh load**: a clone of the model with the order cache cleared, never the object being saved, which already holds the new values, and never the shared model, which site code may still hold mid-save.
- **The hooks stand aside while `Resources::is_writing()` holds** (any id during a create, `name:id` otherwise). A layer write with N ACF fields is N+1 order saves, each on its own `WC_Order`; HPOS saves only the meta that changed, which is why the model's own instance is safe to save last.
- **The hooks never throw.** A throw inside a save becomes an order note, and in the before hook it cancels the write silently. Each is wrapped; the only refusal is the stale `wp_die` on the edit screen.
- **`via`** is `cron` under Action Scheduler or WP-Cron (tested first, since async runners use admin-ajax), `admin` in wp-admin and `other` elsewhere.
- **Outside creates:** Store API checkout and the new-order screen make drafts (`checkout-draft`, `auto-draft`); like a post's `auto-draft`, they are journaled from their first real status and their deletion writes no row. Classic checkout creates `pending`, journaled as a create.

**Acceptance**, in wp-admin: a field edit, an items-box save, an item added, an item removed, a status change, a trash and an untrash each leave exactly one row with the right action and a working undo. A forced delete leaves one `deleted` row and refuses undo. Two tabs on one order: the second save is refused as stale and changes nothing. A layer write leaves exactly one row, its own. A status change through WooCommerce's REST API writes one `other` row; two orders changed by Action Scheduler in one batch write one `cron` row each. AJAX item edits carry no version, so the stale check covers the main form only.

Done 2026-11-10 on digitalis.ca, in one wp-cli process with each step settled as a request would be, on scratch orders: an ACF edit, a status change, three saves in one settle, a trash and an untrash each wrote one `other` row; the layer's undo of the outside untrash worked; a layer create and update wrote only their own rows; two orders in one Action Scheduler batch wrote one `cron` row each; an outside create wrote a `create` row; a checkout draft made and deleted wrote none; a forced delete wrote `deleted`, whose undo is refused. Adding and removing an item raise the version but write no row while the resource reads no items; the consumer's `items` field makes them rows. The editor's claim, emulated by POSTing its fields to the page hook and exiting as the redirect does: the first tab's save is one row (versions 2 to 4), the second tab is refused as stale with nothing written. Then over HTTP with an admin session, on a scratch order: the real edit form opened in two tabs, the first saved as one `admin` row (versions 2 to 4), the second refused with a 409 and nothing written; a fee added and the items box saved by AJAX raised the version with no row; a status change through WooCommerce's REST API wrote one `other` row. Order and Project regressions unchanged.

---

## Phase 5: the credential in the journal

`lattice/credentials` already stores the password's UUID on every row, scoped or not (`credential`, migration v3). What remains is its name: a `credential_name` column (migration v4, gated on `Journal_Schema::is_current()`) from `WP_Application_Passwords::get_user_application_password()`, stored as it was when the row was written, so a row still names a password after a rename or a revoke. `describe_change()`, the journal route, `wp lattice journal` and the Changes page show it. Null outside REST. Independent of the other phases.

**Acceptance:** two passwords on one user write through REST; their rows name each, and still do after one is renamed and the other revoked. A CLI write's row has neither.

Done 2026-11-10 on digitalis.ca. The name comes from the password WordPress authenticated (`application_password_did_authenticate`), noted beside its UUID by `Credentials::note_current()` and read by `get_name()`. Two scratch passwords on one account each wrote a scratch order through the layer's REST route, in processes of their own with the authentication hook fired as WordPress fires it: each row named its password in the route, `wp lattice journal`, the Changes page and the stale refusal ("jamie with the password P5 beta, through the API"), and still did after one was renamed and the other revoked. A CLI write's row had neither.

---

## Phase 6: documentation

A section in [RESOURCES.md](../RESOURCES.md) (declaring an order resource, the seam questions, the hooks and their limits) and one line in AGENTS.md.

---

## For the consumer

What digitalis-co's `Order_Resource` needs beyond the existing `Order` and `Order_Item` methods:

- `items` as a model field with `'kses' => true`: `set_items()` stages, `save()` applies through `write_items()` after saving the props, since `write_items()` calls `hydrate_instance()` and would drop props staged on the old instance. Problems from `write_items()` become a `WP_Error`. `read_items()` answers the staged array on a copy so consequences read it.
- `validate` through `get_item_problems()`, which gains `$restoring` and treats an unknown id as new when restoring.
- `get_proposable()` keeps ORDER_DOCUMENT's `confirm` on trash.

---

## Decisions

Settled with Jamie on 2026-11-10.

1. **HPOS with sync off first**, refused elsewhere by the resource's condition and the feature's. Other consumers may need legacy storage, so it stays reachable: the version seam already goes through `OrderUtil::get_table_for_order_meta()`, and a legacy port must also ignore the post hooks a legacy trash fires. Compatibility mode (sync on) is the hard case: a sync can replay a write nobody made, and WooCommerce stops mapping order capabilities.
2. **`get_version()` is the lattice version on the order model**, with `get_woo_version()` for WooCommerce's.
3. **A row per order per settle, amended**, rather than a row per save. A settle is the request, or one Action Scheduler action. An outside process that writes one order twice before a settle gets one merged row; layer writes journal themselves.
4. **Orders join change sets and proposal sets** through `get_proposable()` like any resource. Draft proposals are post-only and stay so.
5. **`shop_order` only.** Refunds and subscriptions fire their own object and delete hooks; each is a later port.

## Guardrails

- Existing resources behave identically after phase 1; that is its acceptance.
- Each phase is one PR. Order: 1, 2, 3, 4, 6; phase 5 any time. Phase 2's dry-run acceptance is proven in phase 3.
