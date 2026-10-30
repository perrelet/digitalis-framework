# Digitalis Framework: Proposals

Let an account that may not change a published record propose a change to it. A reviewer approves it, and approving writes the change through the [resources layer](./RESOURCES.md).

---

## Overview

A proposal is a draft copy of a published record, linked to it by meta. Its author edits the draft in wp-admin and submits it ("Submit for Review"). A reviewer sees what it changes against the live record, then approves it or sends it back with a note. Approving is `Resources::update()` on the live record, so it gets the resources layer's checks, its journal row and its undo.

```
include/features/lattice/proposals/
├── proposals.feature.php                Proposals: options, guards, the resources policy hooks
├── has-proposals.trait.php              Has_Proposals: the proposal meta, on every moderated model
├── proposal.class.php                   Proposal: one draft; finding, changes, markup, review, approve, reject
├── proposals-admin.feature.php          Proposals_Admin: handlers, notices, assets, the Publish box
├── proposals.posts-table.php            Proposals_Table: row actions and list badges
├── proposal-review.meta-box.php         Proposal_Review_Box: the review on the draft's edit screen
├── proposals.admin-page.php             Proposals_Page: every open proposal
├── proposal-cleanup.cron-scheduler.php  Proposal_Cleanup: deletes abandoned drafts
├── views/                               Proposal_Review, Proposals_Queue, Proposal_Status, Proposal_Notice
├── templates/
├── proposals.css, proposals.js
└── proposal-status.js                   The block editor's status panel
```

---

## Loading

Every moderated post type is a resource whose model uses `Has_Version`, `Has_Unsaved_Copies` and `Has_Proposals`. Proposals requires `lattice/resources` and `wp/stored-markup` (see [Trusted Markup](#trusted-markup)), which load with it, and ACF.

```php
public function load () {

    $this->load_feature('lattice/proposals', ['page_slug' => 'acme-proposed-changes']);

    parent::load();

}

class Offer extends \Digitalis\Post {

    use \Digitalis\Has_Version, \Digitalis\Has_Unsaved_Copies, \Digitalis\Has_Proposals;

}
```

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

## Hooks

| Hook | Arguments | When |
|------|-----------|------|
| `lattice.proposal.submitted` | `$proposal` | Submitted for review |
| `lattice.proposal.rejected` | `$proposal` | Sent back; `get_note()` is the reviewer's note |
| `lattice.proposal.approved` | `$original`, `$proposal`, `$result` | Written, before the draft is deleted |
| `lattice.proposal.cleanup_days` | `$days` | Filter: idle days before a draft is deleted |
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

- Approving writes content as the reviewer, so a reviewer without `unfiltered_html` has it filtered again. Content already filtered when its author saved it is unchanged by this. Content from an author with `unfiltered_html`, approved by a reviewer without it, loses what the reviewer can't keep, and the dry run does not show it.
- Proposals are made in wp-admin only. A write through the API cannot yet be held as a proposal.
- Long text is presented shortened; the review box links a preview of the draft when its content changed.
