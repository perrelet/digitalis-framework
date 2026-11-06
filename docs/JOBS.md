# Digitalis Framework: Jobs

Durable background work that runs on any WordPress host: a kind of work is a class, each piece of work is a row.

**A step is one call of `handle()`; returning commits it and everything it held; a throw commits nothing.**

---

## Table of Contents

- [Loading](#loading)
- [Writing a Job](#writing-a-job)
- [The Surface](#the-surface)
- [Injection](#injection)
- [Dispatching](#dispatching)
- [States](#states)
- [Cancelling](#cancelling)
- [The Worker](#the-worker)
- [Closing: on_end](#closing-on_end)
- [Pausing and Copied Sites](#pausing-and-copied-sites)
- [Seeing It](#seeing-it)
- [What It Spans](#what-it-spans)
- [Limits](#limits)

---

## Loading

Load the feature before your own classes, since your jobs extend `Job`:

```php
public function load () {

    $this->load_feature('lattice/jobs');

    parent::load();

}
```

It creates `{prefix}lattice_jobs`, schedules a one-minute tick and a daily prune, and registers `wp lattice jobs` and Tools → Jobs. It needs only WordPress cron.

---

## Writing a Job

One class per `*.job.php` file, under `include/`. The autoloader runs `static_init()` only for a file's first class, and `App::boot()` returns under WP-CLI before anything loads `_cron` or `_admin`, so a job kept elsewhere reads as unknown under some entries.

```php
// One step.
class Notify_Owner extends \Digitalis\Job {

    protected static $type    = 'example/notify-owner';
    protected static $tries   = 5;
    protected static $backoff = [MINUTE_IN_SECONDS, HOUR_IN_SECONDS];

    public function handle (User $user, Project $project) {

        if (!$project->is_live())      $this->cancel('The project closed.');
        if ($this->provider_is_down()) $this->snooze(10 * MINUTE_IN_SECONDS);

        $user->notify($project);

    }

}

Notify_Owner::dispatch(['user' => 5, 'project' => 9], at: $when, key: 'project:9', expires: $deadline);

// Many steps.
class Reindex_Posts extends \Digitalis\Job {

    protected static $type = 'search/reindex';

    public function handle () {

        if (!$this->get_cursor()) $this->set_total(Post::count_all());

        $post = $this->post_after($this->get_cursor());

        if (!$post) return;

        $this->next($post->get_id());
        $post->reindex();

    }

}
```

**Call `next()` first.** It takes effect only when `handle()` returns, so its order against the work does not matter, and placing it first means a later `return` cannot end the job early. A `return` before `next()` finishes the job: this is the form's one trap. A job finishing short of `set_total()` is logged.

A step can hold a batch, which costs fewer statements but repeats more and stops less often. A cursor can hold any JSON, so phases, nested iteration and state machines are all a cursor: `[$phase, $after] = $this->get_cursor() ?? ['export', null];`. It is a position, never a list: it is rewritten at each step.

**A step can repeat.** A death before its commit runs it again, so a job guards its own effects.

---

## The Surface

| Part | Meaning |
|---|---|
| `handle()` | One step. Returning commits it, and with no `next()` finishes the job. Throwing is a failed attempt. Called again on one instance through a run, with its arguments bound once; instance state is only a cache. Not declared on the base class, since differing signatures would be a compile fatal |
| `next($cursor, $done = 1)` | Another step follows, from this non-null cursor. Twice in a step, or to the cursor it already has, is a failed attempt |
| `get_cursor()` | The saved place, or null |
| `snooze($seconds)` | Not now. Drops this step, keeps the cursor and latest time, spends no try. A minimum of a minute |
| `cancel($why = '')` | Never. From inside the job's own `handle()` it ends the run; from anywhere else see [Cancelling](#cancelling) |
| `set_total($total)` | Written with the step |
| `on_end($state)` | Closes what the job opened. See [Closing](#closing-on_end) |
| `dispatch($args, at, key, expires, now)` | Makes the row. See [Dispatching](#dispatching) |
| `find($key)` | The open job of this class with that key, or null |
| `reschedule($at, $expires = null)` | Moves a pending job and clears any dead lease. A past `$at` means due now. Keeps the latest time unless given a new one, `false` meaning never; refused if `$at` falls past it. False only for a live lease, an ended job or that latest time |
| `retry($expires = null)` | Moves a `failed` job back to pending, its cursor kept. A dispatch that arrived during the run still restarts it when it next finishes. Refuses any other state, a row whose class has gone, a key since reopened, or a latest time passed with no later `$expires` |
| `run_one()` | One whole run now, from PHP or `--id`, waiving "due". Restores the current user. It issues a `ROLLBACK`, so do not call it inside a transaction of your own. This is how a job is tested |
| `get_state()`, `is_running()`, `get_progress()`, `get_args()`, `get_error()` | Reading, always from the row. `get_progress()` answers `done` and `total`, with `total` null if never set. `get_error()` answers the last failure or the reason for a cancel |

| Setting | Default | Meaning |
|---|---|---|
| `$type` | Required | The row's type, up to 64 characters. Explicit, so renaming a class orphans nothing |
| `$tries` | 5 | Claims without a committed step before failing. A committed step earns a fresh set, so `$tries = 1` does not mean never retried |
| `$backoff` | 1, 5, 15, 60 minutes | The delays, the last repeating |
| `$lease` | 5 minutes, floor one minute | A step must fit it, or two workers can overlap |
| `$expires_in` | 30 days, null for never | How long after its due time a job stops being worth doing |

`snooze()` and `cancel()` throw, and record their outcome first, so a `catch` cannot lose one, though code that catches it runs on until `handle()` returns. Calling `next()` or `snooze()` outside the job's own `handle()`, an instance method on an unsaved job, `find()` on the base class or `run_one()` inside `handle()` throws a `LogicException`.

---

## Injection

`handle()`'s parameters are bound by name from the row's arguments.

- A parameter typed as a class with `get_instance()` is resolved from its id. An id that does not resolve is an ordinary failed attempt.
- A plain deleted record may still resolve, since `WP_Model::validate_id()` only asks for a positive integer. A job that needs the record to exist checks that itself, and calls `cancel()` if its absence is normal.
- Any other parameter receives its value as stored.
- A missing or null argument with a default takes the default, never a global. A missing or null required argument, or a missing or non-public `handle()`, fails the job at once with no retries.

---

## Dispatching

`at` and `expires` take a timestamp or date object. `expires` falls back to `$expires_in`, and `false` means never. Dispatch returns null for objects or non-JSON values in `$args`, an empty key, one over 191 characters or with trailing spaces, or an `expires` at or before `at`. Arguments hold ids rather than personal data.

- **Outside `handle()`** it writes the row and returns the job, or null on failure. A deadlock is retried.
- **Inside a transaction of your own,** a plain insert joins it. Anything that needs its own transaction commits yours, as `START TRANSACTION` does: ending an about-to-end row, or a `cancel()` writing a follow-up.
- **Inside `handle()` it is held:** written in one transaction with its step, after the step's update, and dropped by a throw or snooze. It returns an unsaved job, with no id until written. So a chain or fan-out cannot repeat a hand-off.
- **`key`** means one open job per key per type, enforced by the database. Dispatching a key already open returns that job and keeps its arguments and due time, since the same key means the same work. If it has a lease or a saved cursor, the dispatch also makes it run again from the start when it finishes, so a change made during a run is not missed. If the open row is about to end, because it carries a cancel, is past its latest time or, unleased, is out of tries, then unleased it is ended and a new job opened, and leased the dispatch returns null. A key says nothing about jobs already ended: for "once a day", put the date in the key.
- **A job's own key** needs no rule of its own. In a finishing step the row has just closed, so a new job opens, which is how a job moves itself past its latest time; it ends as `done`, and any `on_end` runs. In a step that calls `next()`, it sets `again` like any other.
- **`now: true`** runs the job on `shutdown` after `fastcgi_finish_request()` or `litespeed_finish_request()`. It applies only to a job due now, unleased and on a site not paused. Anything else, a host with neither function or WP-CLI waits for the next tick. The row comes first, so a death loses nothing.

A job with `now` starts after the response. Any other starts at the next tick after it is due: about a minute at best on a site with real cron, and at the pace of traffic on one without.

---

## States

`pending`, `done`, `failed`, `cancelled` and `expired`. Running is a pending job with a live lease, and snoozed or backing off is a pending job with a future `run_after`.

| How a run ended | Result |
|---|---|
| Returned with no `next()` | `done`. If a dispatch set `again`, back to pending from the start instead, unless a cancel is waiting, which wins |
| Returned having called `next()` | Saved; the next step runs, unless a cancel, the latest time, the budget, memory or a pause says otherwise |
| Threw | With a cancel waiting, `cancelled`. At `$tries`, `failed`. Otherwise it backs off |
| `snooze()` | With a cancel waiting, `cancelled`. Otherwise it waits, spending no try |
| `cancel()` | `cancelled` |
| Fatal, `exit` or `wp_die()` | As a throw. In a `now` job, or for a process killed outright, the lease lapses instead and the job runs again |
| Could not be called | `failed` at once |
| Lost its lease | Nothing: it stops and writes nothing |

Times are `DATETIME` in UTC, compared with the database's `UTC_TIMESTAMP()`, so servers and session time zones may differ.

---

## Cancelling

| The job is | `cancel($why)` from outside answers |
|---|---|
| Pending, with no live lease | `cancelled`, ended at once |
| Held by a live worker | `requested`. Honoured before its next step, or by how the run ends. A step that finishes the job ends it as `done` |
| Already ended | The state it is in |

`$other->cancel()` inside a `handle()` is "from outside": it writes at once, so it is outside "a throw commits nothing".

---

## The Worker

`Jobs_Tick` is a `Cron_Scheduler` with its own one-minute schedule. `wp lattice jobs run` is the same worker, with no budget unless `--budget` is given. Leases make several workers safe.

**The gate.** Every entry first checks that the schema is current, the site is not a copy and it is not paused.

**One scan decides everything.** Due, pending rows of known types with no live lease, ordered by `run_after` then `id`, are ended (carrying a cancel, past their latest time, or at `$tries`) or claimed by compare-and-set. Round-robin comes from one rule: a job stopped by the budget has its `run_after` set to now, so it goes behind what is already due. There is no priority.

**Unknown types** are moved on by an hour each time the worker starts, so they never sit at the head of the scan, and end 7 days after they were first parked. A job claimed again forgets its parking.

**The budget** is about 45 seconds or what remains of three quarters of `max_execution_time` in the request, whichever is lower, with `lattice.jobs.budget` for hosts that hide a wall-clock limit. No new job starts past half of it. Near a memory limit (80%) the worker stops before the next step.

**Each run** starts with no current user: the framework impersonates nobody. Between jobs the worker forgets model instances and flushes the runtime cache, though a class's own statics survive.

**A step costs** three statements with nothing held, and five plus one per held dispatch otherwise.

---

## Closing: on_end

Closing is itself a job. For a class that overrides `on_end()`, every end writes a `Job_End` row in the same transaction, so retries, a lease and a place on the screen all come from the machinery, and it runs at least once. It can run more than once, so it guards itself. `Job_End` never expires, and cancels itself if the job is gone, its class is gone or the job has moved on.

`lattice.jobs.ended` (`$job`, `$state`) fires after an end is written, in that process, for anything that wants to watch. It is not durable: durable work belongs in `on_end()`.

---

## Pausing and Copied Sites

One option holds why the worker is paused, and since when: `copied`, `by a person` or `uninstalled`. While paused, the worker, named entries and the prune do nothing; dispatch, cancel, retry and reschedule still write. A running job stops before its next step. Resuming is the one way back from any reason.

**A copied site pauses itself.** An option holds a hash of the `home` option without its scheme, so a migration tool's search and replace cannot rewrite it to match and a move to https does not trip it. `--discard` cancels what was created at or before the pause, with no follow-up, so no `on_end` runs on the copy; plain resuming runs what was copied. A copy on the same domain goes unseen, and so does a restored backup, which will rerun work.

**Uninstalling** clears the events, and the stored reason stops them being scheduled, or the table recreated, on the next boot. `--drop` also removes the table and every option but the reason. Removing the feature without it leaves its events firing to no effect.

---

## Seeing It

- **Tools → Jobs:** jobs by state, with Cancel, Retry, Pause, Resume and Uninstall, on `manage_options`.
- **`wp lattice jobs list|get|run|cancel|retry|pause|resume|uninstall`.** `jobs` is not a resource name.
- **Notices:** paused, a stale schema, or the tick over 15 minutes past on two admin visits apart, since on a site with no real cron the first visit in a quiet spell is itself what wakes it.
- **`jobs.log`:** each failure, beside the site's error log. Where that sits inside the web root, or is not a file, failures go to the error log itself.
- **The prune:** daily, `done` after 30 days and other ended rows after 90, through `lattice.jobs.keep_days`, leaving any with a follow-up still open.

---

## What It Spans

| Kind of work | How |
|---|---|
| Deferred | `dispatch()` |
| Someone is waiting | `now: true`, on a host that can finish a response early |
| At a date, and moved | `at`, `reschedule()` |
| Moved by the job itself | `snooze()` within its latest time, or a held dispatch of its own key, which ends this job as `done` |
| Retried | `$tries`, `$backoff`, a throw |
| One open per subject | `key` |
| Long, resumable, watched | `next()`, `set_total()` |
| In phases, nested, or a state machine | A cursor holding the state |
| Remote, paginated | The API's token as the cursor |
| Stoppable | `cancel()` |
| Worthless after a date | `expires` |
| Waiting on an outside service | `snooze()` |
| Chained | Dispatch the next in the finishing step |
| Fan-out | Dispatch many in a step |
| Fan-in | Dispatch children keyed with the parent's id and `next('spawned', 0)`; then snooze while `Child::find($key)` answers for any. It sees finished, not whether each succeeded |
| Recurring | A `Cron_Scheduler` task that dispatches with a key |
| Throttled, lightly | The job checks and snoozes. Bulk work should be one job of many steps, not many throttled rows |

---

## Limits

- **Exactly once** is not promised: a step can repeat.
- **With no traffic and no real cron nothing runs,** which cannot be fixed from inside PHP.
- **An FPM reload kills a step mid-run,** with `process_control_timeout` at its default. The lease recovers it, up to `$lease` later.
- **A cursor that cycles** cannot be detected, and is bounded only by its latest time. `next()` to the same cursor is caught.
- **A `catch (\Throwable)` around `snooze()` or `cancel()`** cannot lose the outcome, but the code after it runs until `handle()` returns.
- **A job that switches blogs** does so at its own risk: the model cache ignores the blog id. On multisite, each subsite has its own table, tick and pause, and needs its own traffic or `--url`.
- **Not included, and additive later:** a `parent` column for a fan-out that counts its children's failures, priorities, named queues, concurrency caps, job results and a call renewing the lease within a long step.
