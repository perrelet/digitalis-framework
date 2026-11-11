# Digitalis Framework: Scoped Credentials

A WordPress application password is its account: whoever holds it may do everything the account may. `lattice/credentials` gives a password a policy that only narrows that. Every request asks one question, may this credential do this verb on this target, answered `allow`, `review` (it may be proposed, and happens if a reviewer approves) or `deny`.

The [resources layer](./RESOURCES.md) loads it, so wherever the layer runs, policies are read. A password with no policy, a cookie session and the CLI are unchanged.

---

## A policy

```json
{
  "version": 1,
  "expires": "2027-03-01",
  "policy": "propose_only",
  "rules": [
    { "verbs": "reads", "on": "resource:*", "answer": "allow" },
    { "verbs": "writes", "on": "resource:note", "answer": "allow" },
    { "verbs": "read", "on": "resource:email", "answer": "deny", "fields": ["audience"] },
    { "verbs": ["GET", "HEAD"], "on": "route:/wp/v2/media*", "answer": "allow" }
  ]
}
```

- **Anything unmatched is denied.** A policy that cannot be read (an unknown key, a malformed rule, a shared policy that is gone) denies everything.
- **The most specific rule wins, then the strictest.** Target first (a wildcard, a wildcard with a condition, a named target, a named target with a condition), then the verb (a group before a named verb). On the same target, a rule narrowing in every way another does (by field, by record, by how the call arrives), and more, is the more specific; rules narrowing in different ways (one by field, one by record) are neither, so the strictest of them decides before any verb is weighed. An `except` rule asked about the whole record (a trash, an action) counts as narrowing no field. Order means nothing. A request touching several fields takes the strictest of their answers.
- **A credential's own rules and its shared policy must both allow.** A shared policy is a baseline the password's own rules narrow.
- **`expires`** is a date (the end of that day in the site's zone) or a time with a zone, never relative.

| Target | Verbs | Conditions |
|---|---|---|
| `resource:<name>`, `resource:*` | `list`, `read`, `view:<name>`, `create`, `update`, `trash`, `untrash`, `undo`, `act:<name>`, `rehearse:<verb>`; `reads` (list, read, views) and `writes` (everything else, every action included) | `fields` or `except`; `records`: `own`, ids or a condition the resource declares; `when: step_of_other_set` |
| `route:<pattern>`, `route:*` | HTTP methods, `*`; `HEAD` follows `GET` | none |
| `ability:<name>`, `ability:<category>/*` | `run` | none |

- `change_set` is reached only by rules naming it, never by `resource:*` or a verb group. A review rule anywhere also allows making, holding, submitting, rebasing and withdrawing the credential's own sets.
- A record condition holds before and after a change, so a drafts rule cannot publish. A trashed record is judged by the status it had. An action that declares no `sets` meets no condition afterwards.
- `review` answers `deny` where nothing can review: Proposals not loaded, or the resource does not take that verb in a set.
- `Model_Resource::get_conditions()` names a resource's conditions; post types have `draft` (with pending), `published` and `private`.

Presets: `read_only` and `propose_only`. A site defines its own with `Credentials::inst()->define($name, $policy)`; one defined twice is unreadable.

## The CLI

```
wp lattice credentials set <user> <uuid> --input=<file> [--policy=<name>] [--expires=<when>]
wp lattice credentials show|clear|explain <user> <uuid>
wp lattice credentials ask <user> <uuid> <on> <verb> [--fields=a,b] [--facts=<json>] [--dry-run]
wp lattice credentials list [<user>]
wp lattice credentials shared [<name>] [--input=<file>] [--delete]
```

`set` refuses a policy naming a resource, field, action, view, role, route or ability that does not exist, unless the rule says `"waiting": true`. A stored shared policy keeps its id while its name is used; deleted, its name may be used again under a new id, which grants nothing to the passwords that held the old one. `list` with no user finds every scoped password, so a way back can revoke them first.

`explain` says what a policy allows on this site, where the promise is only coarse, and flags rules that match nothing, rules covering what is added later, single-record route denies, powers to approve others' sets and fields written but not read. A credential reads its own explanation in the guide, under `credential`.

## Where it holds

| Stage | Decides |
|---|---|
| Authenticating | An expired or unreadable policy, a request outside REST and multisite are refused. A path no rule could reach runs as nobody, before plugin code sees the account |
| Dispatching (`rest_request_before_callbacks`) | Route rules, on the path and every pattern WordPress could match it to. An answer given before dispatch is decided at the end of `rest_pre_dispatch` |
| The layer (`Resources::may()`) | Resource rules: verbs, fields, records, dry runs, undo as the write it makes, every read door (records, lists and their filters, views, the journal, the guide, dry runs, `expected`) |
| Sets | Each held step is asked of its holder; submitting and approving ask the author password's current policy; a password never approves its own set; a reviewer reads every step |
| Abilities | Ability rules, by the ability's real category |
| Capabilities | No account capability at all; `read_post` refused for a record the credential may not read, outside the layer |
| Storage | Before every other listener, and stopping the request: roles, capabilities and level, passwords and reset keys, application passwords, sessions, second factors, accounts made or deleted, role definitions and policies |

A set is its account's and the scoped password's that wrote it; the journal names the password behind every change (`credential`, and `credential_name` as it was named then).

## Limits

- Removing the code makes a scoped password a full one, so a way back revokes them first.
- A route's own code is unseen: one with no capability check, or a `GET` handler that writes, is only as narrow as its route rule.
- Plugin code before the gate, each route's argument callbacks, work scheduled for later and what an allowed verb causes elsewhere run as the account.
- A cache keyed by user can serve one credential's view to another.
- Writing a field the credential cannot read still shows whether the value matched, and a resource's views and consequences may read what is barred.
- `own` is the account's, so agents kept apart use separate accounts.
- A user switch mid-request leaves the capability stage behind (storage still holds), WordPress's REST serves published posts without asking `read_post`, and a stop inside a layer write skips its journal row.
- Account management waits for an `account` resource: until then a scoped password changes no account.
