# Digitalis Framework: Change Sets

A change set is a held list of the calls an account could make to the [resources layer](./RESOURCES.md): update this record, run this action, create that one, trash another. Holding one changes nothing on those records. Applying it replays the calls through the layer as the account applying, each with its own checks, preview, `confirm`, journal row and undo. Nothing claims a set is atomic: it stops at the first step that fails or no longer matches what was seen, and what landed stays.

Load it with `$this->load_feature('lattice/change-sets')`, which brings `lattice/resources`. A site that loads it lets an account apply its own sets; proposing one for someone else is [Proposals](./PROPOSALS.md)'. Its tables are made where it loads and nothing drops them. If they or the journal are not current, no resource registers, so its routes answer `not_found`.

---

## A set's life

A set is a resource, `change_set`, over rows in `{prefix}lattice_change_sets` and `{prefix}lattice_change_steps`, so it answers at `lattice/v1/change_set` and `wp lattice change_set`.

```text
POST change_set                       { purpose, why }
POST change_set/12/set_steps          { steps: [...], version, why }
POST change_set/12/apply_set          { dry_run: true }           answers version and steps
POST change_set/12/apply_set          { seen: <that answer>, why }
```

| Who | May |
|---|---|
| `propose_changes` or `edit_posts` | Make a set and list their own |
| Its author | Read it, change its `purpose` and steps while it is a draft, apply it, rebase it, withdraw it |
| `approve_proposals` | Read and list every set |
| Its author or `approve_proposals` | Undo what it applied, settle a step |

Its author is its account and, where a scoped password wrote it, that password: another password on the account, or the account in wp-admin, is not ([CREDENTIALS.md](./CREDENTIALS.md)).

`steps`, `state` and `author_id` are readers: writing one answers `invalid`.

A step is `{op, resource, id, action, input, values, expected, wait}`, where `op` is `create`, `update`, `act`, `trash` or `untrash`. `set_steps` replaces the whole list. A step sent with its `step_id` is that step; a read sent back changes nothing. A resource takes only what it declares in `PROPOSABLE` (updates and creates by default), and an action that writes through the service cannot be a step. Each value is filtered as the author when held, and what filtering removed stays with the step. An account that may not write a record cannot hold a value for one of its secrets, since a mask would let it test guesses.

An update's `expected` holds the value each field started from, read from the live record if not sent. A field is applied when the live record still holds its start, skipped when it already holds the proposed value and a conflict otherwise. Secrets are masked wherever a set is read, held raw only in `values` for applying, and replaced by their masks when it closes.

## Applying

`apply_set` is the author's, while `submitted_at` is null and the set is neither closed nor `broken`. Its dry run answers each step's preview, `fingerprint`, `version` and `step_hash` and the set's `version`. Send those back as `seen`. A step that follows an earlier step on its record, or declares `wait: true`, is previewed when reached: the walk applies what comes before it and stops, and looking again and applying goes on from there. A set whose applied rows have been undone cannot go on, only be withdrawn.

| Outcome | Nothing applied | Something applied |
|---|---|---|
| `applied` | `applied` | `applied` |
| `awaiting_look` | `awaiting_review` if submitted, otherwise `draft` | `awaiting_review` if submitted, otherwise `applied_in_part` |
| `refused` | `draft` | `applied_in_part` |
| `failed` | `draft` | `applied_in_part` |
| `unknown` | `unknown` | `unknown` |

Each with the reason in `reason`. Nothing overwrites `broken`, which a delete of a record it names writes without waiting for the lock. Going back to `draft` clears `submitted_at`, and so does a reviewer's rejection in any state.

**One request at a time.** Every write to a set takes `GET_LOCK` without waiting, so a second answers `locked`; a dry run takes none. Under the lock it settles what a dead request left, then reads state and markers again and refuses on what it finds, since its own refusals ran unlocked and a walk that died raised no version.

**A step whose outcome is unknown is never applied again by itself.** A create that landed whole settles itself by its key and its unmarked row. Anything else waits for `settle_step` (`position`, `outcome`: `applied` or `not_applied`), which refuses `not_applied` on a create whose record exists outside the Trash, since applying again would make a second. A set with a step unsettled cannot be withdrawn or undone.

| Action | Does |
|---|---|
| `undo_set` | Undoes the applied rows, newest first, with a dry run and `seen` like applying. It leaves the state, in any state, closed sets included |
| `withdraw_set` | Closes an open set without applying more |
| `rebase_set` | For steps not yet applied, takes the live value as the start of a conflicting field and holds the values again as the author. A set with nothing applied returns to `draft` |

## Cleanup

Each day the feature withdraws `draft` and `broken` sets idle past `lattice.change_set.cleanup_days` (90), journaled `via: cron`, skipping a set with a step unsettled. Sets `applied` or `withdrawn` are deleted, with their steps, a day after `lattice.journal.keep_days` passes, so never before their rows. A step whose row has been pruned is named as not undoable.

## Extending

Proposals registers its own resource under the name `change_set`, extending `Change_Set_Resource`, and the feature's hooks ask for whichever is registered; see [Sets](./PROPOSALS.md#sets). `walk_set($set, $seen, $why, $via, $action)` is the walk with its outcome applied to the set, for an action that writes through the service, under the lock.

**Hooks:** `lattice.change_set.awaiting_review`, `applied_in_part` and `broken` fire with the set. The first two fire once the set's row is written and its lock released, so a listener can read one and act on the set, and only when the walk changed something. `broken` fires where it happens, since a delete holds no lock. See [HOOKS.md](./HOOKS.md).

**Limits:**
- A closed set's masks depend on who closed it: an account who cannot see a field masks it whole, plain cells included. No secret stays raw.
- A start held as one mask against a reviewer's per-cell masks reads as a conflict, which fails closed.
- An action's `input` is not masked, so it should hold no secret.
