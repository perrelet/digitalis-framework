# Digitalis Framework: Proposals

Let an account that may not change a published record propose a change to it. A reviewer approves it, and approving writes the change through the [resources layer](./RESOURCES.md).

---

## Overview

There are two kinds of proposal for now. One is below: a draft copy of a record, edited in wp-admin. The other is a [change set](./CHANGE_SETS.md) offered for review, which can name several records and is made through the API: see [Sets](#sets). They stand side by side in the queue until drafts become a set's workspace.

A draft proposal is a draft copy of a published record, linked to it by meta. Its author edits the draft in wp-admin and submits it ("Submit for Review"). A reviewer sees what it changes against the live record, then approves it or sends it back with a note. Approving is `Resources::update()` on the live record, so it gets the resources layer's checks, its journal row and its undo.

```
include/_features/lattice/proposals/
├── proposals.feature.php                Proposals: options, guards, the resources policy hooks
├── has-proposals.trait.php              Has_Proposals: the proposal meta, on every moderated model
├── takes-proposals.interface.php        Takes_Proposals: what Has_Proposals and Has_Version give a moderated model
├── proposal.class.php                   Proposal: one draft; finding, changes, markup, review, approve, reject
├── proposal-set-resource.class.php      Proposal_Set_Resource: the review workflow on a change set
├── proposals-admin.feature.php          Proposals_Admin: handlers, notices, assets, the Publish box
├── proposals.posts-table.php            Proposals_Table: row actions and list badges
├── proposal-review.meta-box.php         Proposal_Review_Box: the review on the draft's edit screen
├── proposals.admin-page.php             Proposals_Page: every open proposal
├── proposal-cleanup.cron-scheduler.php  Proposal_Cleanup: deletes abandoned drafts
├── views/                               Proposal_Review, Proposal_Set_Review, Proposals_Queue, Proposal_Status, Proposal_Notice
├── templates/
├── proposals.css, proposals.js
└── proposal-status.js                   The block editor's status panel
```

---

## Loading

Every moderated post type is a resource whose model uses `Has_Version`, `Has_Unsaved_Copies` and `Has_Proposals`. Proposals requires `lattice/resources` and `lattice/change-sets`, which load with it, and ACF. A site with no moderated type takes sets alone. `Stored_Markup` (see [Trusted Markup](#trusted-markup)) loads on `init` only where a model uses `Has_Proposals`, so its hooks exist from then on.

```php
public function load () {

    $this->load_feature('lattice/proposals', ['page_slug' => 'acme-proposed-changes']);

    parent::load();

}

class Offer extends \Digitalis\Post implements \Digitalis\Takes_Proposals {

    use \Digitalis\Has_Version, \Digitalis\Has_Unsaved_Copies, \Digitalis\Has_Proposals;

}
```

The trait is what makes a type moderated; `Takes_Proposals` names the contract the traits fulfil, and strict mode throws for a model that uses `Has_Proposals` without implementing it.

Load it in `load()`, which runs in every context: the publish guard must see REST saves, and the cleanup cron must exist under cron. The wp-admin parts load on `init` in wp-admin, once resources have registered; `Proposals::inst('proposals')->load_admin()` loads them from the CLI.

| Option | Default | Means |
|--------|---------|-------|
| `page_slug` | `lattice-proposals` | The queue's admin page |
| `post_types` | `null`: every resource whose model has `Has_Proposals` | The types that take proposals; only those count |
| `admins_approve` | `true` | `manage_options` accounts get `approve_proposals` |

---

## Capabilities

- `propose_changes`: starts a proposal from a published record's row actions.
- `approve_proposals`: sees every proposal, approves and sends back. Approving also needs `edit_post` on the live record, as any resources update does.
- `delete_post` on the draft: discards it (a reviewer) or withdraws it (its author).

A site's roles grant the first two.

---

## The Lifecycle

| Step | What happens |
|------|--------------|
| Propose | `Proposal::open($original, $author)`: `duplicate()` with the link in its `meta_input`, so the draft's first save is already a proposal's. The live record's version is stored. |
| Edit | An ordinary draft. Proposals answers `lattice.relationship.sync` with `false` for it, so partners never link to it. Publishing, scheduling or making it private keeps its status. |
| Submit | Draft to pending. The last note is cleared; `lattice.proposal.submitted` fires. |
| Send back | `reject($note)`: back to draft with the note (which may be empty); `lattice.proposal.rejected` fires. |
| Approve | `approve($fingerprint)`: refused unless submitted, its record published, the draft the record points at and a fingerprint sent (`refuse_approval()` says why first). Then the update, `lattice.proposal.approved`, and the draft is deleted. |
| Discard | The draft goes to the Trash; the live record can take a new proposal. |

One record has one open proposal (`get_open_proposal()`). Only that draft clears it. A draft restored from the Trash, or submitted, takes it back when no other proposal is open. Deleting the record deletes its draft. Drafts idle past `lattice.proposal.cleanup_days` (90) are deleted daily; submitted ones wait.

A draft is a different post, so ACF field groups located by which record this is (`post`, `page`, `page_type` and `page_parent` rules, such as the front page or a page's children) match the live record instead: Proposals answers `acf/location/match_rule/type={param}` for a draft by matching the rule against the record it proposes for, ignoring the parent the editor posts. Every other rule (post type, template, terms) matches the draft, so a proposed term still brings its fields.

A stale proposal stays stale: its version is the record's when it was made. Discard it and propose again on the current version.

### What approving writes

`get_changes()`: the draft's fields (`read($draft, false)`) that differ from the live record's, without `status` and computed fields. Which fields exist is the resource's choice: a type whose excerpt is generated leaves `excerpt` out of `POST_FIELDS`, and proposals never copies it.

`get_values()` is what approving writes: the changes less `get_held_back()` (`name => why`). Action-only fields are held back, since only their action changes them, and so are fields the `exclude` markup policy keeps.

The update carries two checks:

- **The version stored when the proposal was made.** If the live record changed since, approving is refused as `stale` (`is_stale()` says so first).
- **The fingerprint of the dry run on the review screen.** If what approving would do changed since the reviewer saw it, it is refused.

Any other refusal (a locked field, a value that fails its check, a field the record does not show) shows on the review screen before anyone approves, from `review()`, the update's dry run.

### Reading a proposal

`present()` answers the resources presenter's lines (labels, rows, names) without the dry run, so a proposal's author sees them too. `review()` needs the right to update the live record.

---

## Sets

Proposals registers `Proposal_Set_Resource` under `change_set`, adding the review workflow to the [set's own actions](./CHANGE_SETS.md). A set is made and its steps held as there, then:

```text
POST change_set/12/submit_proposal    { version, why }
GET  change_set/12/review
POST change_set/12/approve_proposal   { seen: { version, steps }, why }
POST change_set/12/reject_proposal    { note, version, why }
```

| Action | Who | From | Does |
|---|---|---|---|
| `submit_proposal` | Its author, with `propose_changes` | `draft`, or `applied_in_part` when not with a reviewer | Offers it, clearing the note and reason. Needs a purpose and steps. A set partly undone cannot go on, so it refuses one |
| `approve_proposal` | `approve_proposals` | `submitted`, `awaiting_review` or `applied_in_part`, when offered | Walks it as the reviewer, every step needing the reviewer's own capability. A set a scoped password wrote is approved only while that password may still propose its steps, never by that password, and only by a reviewer who may read every step. It stops as [applying](./CHANGE_SETS.md#applying) does, and approving again walks on |
| `reject_proposal` | `approve_proposals` | As approving | Hands it back with an optional note: `draft`, or `applied_in_part` when something landed. No longer offered, so its author may rebase, undo, withdraw, submit it again or apply it if they could make every step |
| `rebase_proposal` | Its author | A draft, offered, `awaiting_review` or `applied_in_part` | `rebase_set`, taking a submitted set too. With nothing applied it returns to draft even with nothing to move, which is how an author takes a set back. With a step applied it stays offered |

**The review** is a read, so it takes no lock and writes nothing. It answers the set's version, `steps` by position and `records`, each record's positions. An account with `approve_proposals` gets each step's preview, fingerprint, version, hash and conflicts; send `version` and `steps` back as `seen`. Anyone else who may read the set, which means its author, gets what can be said without the right to write: for an update, `compare_expected()` and `check_update()`; for anything else, `checked: false`. A step marked `reached` depends on the earlier steps in `after`, so it is not compared against what is live until they land. A conflict marked `masking` is a whole mask against per-cell masks, which reads as a conflict though nobody moved the value.

wp-admin's queue shows a reviewer what was offered and their own, and anyone else all their own, a set handed back reading "Sent back". `get_edit_url()` is its review screen, `?page=lattice-proposals&set=12`, which offers submitting, approving, sending back, rebasing, withdrawing, undoing and settling a step where the account may. Holding steps and an author's own `apply_set` are the API's. Approving is not offered while a step would be refused, since that would hand the set back with nothing to tell its author: send it back instead. A closed set leaves the queue, so a reviewer reaches it from the redirect or Tools → Changes.

`lattice.proposal.cleanup_days` answers `lattice.change_set.cleanup_days` too.

---

## Hooks

| Hook | Arguments | When |
|------|-----------|------|
| `lattice.proposal.submitted` | `$proposal` | Submitted for review |
| `lattice.proposal.rejected` | `$proposal` | Sent back; `get_note()` is the reviewer's note |
| `lattice.proposal.approved` | `$original`, `$proposal`, `$result` | Written, before the draft is deleted |
| `lattice.proposal.cleanup_days` | `$days` | Filter: idle days before a draft is deleted or a set withdrawn |
| `lattice.change_set.submitted` | `$set` | A set was offered for review |
| `lattice.change_set.rejected` | `$set` | A set was handed back; `get_note()` is the reviewer's note |
| `lattice.change_set.approved` | `$set`, `$result` | Approving landed every step and closed the set |
| `lattice.proposal.markup` | `$policy`, `$original`, `$user_id`, `$markup` | Filter: `warn`, `refuse` or `exclude` when an account would take markup off a record (see [Trusted Markup](#trusted-markup)) |

Mail hangs from these in the site; proposals sends none.

A model reaches its proposal state through `Has_Proposals`: on a draft, `get_proposal()`, `is_proposal()` and `get_proposed_for_id()`; on the live record, `get_open_proposal()`. `Proposal::find($draft_id)` and `Proposal::find_model($post_id)` start from an id.

Proposals answers the resources layer's policy hooks, so a draft is not a record of its own there: `lattice.resources.query` leaves drafts out of lists, `lattice.resources.refuse` refuses reading and writing one through the API, and `lattice.resources.journal` keeps its saves out of the journal. It adds its meta keys to `lattice.post.duplicate.exclude_meta`.

---

## Stored

`Proposal::META`. On the draft: `_proposal_for` (the live record), `_proposal_author`, `_proposal_created`, `_proposal_base_version`, `_proposal_note`, `_proposal_saved_by`. On the live record: `_has_proposal`. A draft without `_proposal_base_version` reads as stale.

---

## Trusted Markup

A proposal lets an account edit content another account wrote. WordPress filters the title, excerpt and content (kses) as whoever saves them, and ACF's wp-admin form filters field values the same way (`wp_kses_post_deep()`). `wp/stored-markup` keeps a column or ACF value that a save sends back unchanged, so a field the author leaves alone keeps a trusted account's markup (an `iframe`, an `onclick`, a script). A field they edit is filtered whole as their account, so its markup is gone from the draft, and approving carries that loss to the live record.

Proposals counts the elements and attributes the author's account can't keep, in those columns, in ACF values and in block attributes' strings:

- `Proposal::find_markup_at_risk($original, $user_id)`: what that account can't keep. Whoever edits the draft is warned before they save.
- `get_markup_removed()`: what approving would take off the live record. The review box says so before anyone approves.

`lattice.proposal.markup` chooses what a record at risk does:

| Policy | Means |
|--------|-------|
| `warn` (default) | Both warnings; approving applies the change as it stands |
| `refuse` | No account that can't keep the markup proposes on that record, or joins an open proposal on it (asked with `find_markup_at_risk()`) |
| `exclude` | Approving holds back the fields it would take markup off, so the live markup stays and those edits are not applied (asked of the last saver, with what approving would remove). A proposal with nothing else is refused rather than approved empty |

`get_saved_by()` names the account that last saved the draft, and so vouches for what it holds; 0 until someone saves it, since the insert is the live record's copy. Sending back keeps it.

What kses only re-encodes (`&` to `&amp;` inside block data, block attributes re-serialised) is not counted.

---

## Limits

- Approving writes as the reviewer, so a reviewer without `unfiltered_html` has values filtered again. Values already filtered when their author saved them are unchanged by this. A trusted author's new markup, approved by a reviewer without it, loses what the reviewer can't keep: for ACF values the review's dry run lists it under `removed`; for the post's columns the dry run does not show it.
- A draft proposal is made in wp-admin only; through the API, a proposal is a [set](#sets).
- Long text is presented shortened; the review box links a preview of the draft when its content changed.
