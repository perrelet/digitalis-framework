# Contributing to Digitalis Framework

Welcome, brave soul. You've decided to contribute to the framework. Before you do, there are sacred traditions to uphold, ancient rites to perform, and exactly three emojis to choose.

---

## The Sacred Art of the Commit Message

Every commit in this repository follows a precise format, passed down through generations of developers who understood that code is temporary, but emoji puns are forever.

### The Format

```
type(scope): Brief description in imperative mood 🎭🎪🎠
```

Or without scope:

```
type: Brief description in imperative mood 🎭🎪🎠
```

### Commit Types

| Type | When to Use | Emoji Vibe |
|------|-------------|------------|
| `feat` | New features, methods, classes | Celebration, creation |
| `fix` | Bug fixes, corrections | Tools, repair |
| `break` | Breaking changes (use sparingly!) | Explosions, transformation |
| `refactor` | Code restructuring, no behavior change | Movement, reorganization |
| `style` | Formatting, whitespace, CSS | Aesthetics, cleaning |
| `docs` | Documentation only | Writing, books |
| `tidy` | Minor cleanup, removing old code | Brooms, trash |
| `chore` | Housekeeping: tooling, config, analysis plumbing, dead guards; nothing a consumer notices | Gears, ladders, brooms |
| `tweak` | Small adjustments | Tiny hands, precision |
| `a11y` | Accessibility improvements | Wheelchairs, inclusion |
| `wip` | Work in progress (avoid on main) | Construction |
| `version` | Version bumps | Numbers, celebration |
| `delete` | Removing features/code | Death, farewell |
| `rename` | Renaming things | Arrows, transformation |
| `load` | Autoloader changes | Trucks, delivery |

### The Three Emoji Rule

**Every commit ends with exactly three emojis.** Not two. Not four. Three.

These emojis must tell a story. They are not decoration—they are *documentation*.

### The Rebus

The three emojis spell the subject. Two ways to do it, and the picture wins:

1. **Word by word, in the subject's order.** Take the three content words (the thing, the action, the outcome; drop the type and the class path) and give each one emoji. `fix: `Has_WP_Hooks::add_hook` takes any callable and counts variadics 🪝🧮♾️` is hook, counts, every. Order matters here: `1️⃣🪵🩳` is "one log reader, short log"; `1️⃣🩳🪵` is not.
2. **A picture of the phrase.** The three together depict the sentence rather than one word each: `fix: `Iterator::iterate` you cannot fall through the floor 🧱👣🧱` (footsteps held between two floors), `fix: `Posts_Table::sort_columns` order by 📶🛎️👋` (a bell to order, a wave for bye), `fix: `CSV_Iterator` multiple issues 🐛🐜🪲`.

Extra points when the emoji is the *other* sense of the word, and anchor at least one literal when two are puns. Read the three back as the sentence before you commit. A word keeps the same emoji across a batch, so the glossary below is the house dialect; pick from it first and add to it when you coin one.

| Word | Emoji | Why |
|------|-------|-----|
| hook | 🪝 | literal |
| log | 🪵 | the other log |
| order (item) | 🧾 | a receipt |
| iterator | 🔁 | literal |
| file | 📄 | literal |
| route | 🛣️ | literal |
| level | 🪜 | a ladder, one rung at a time |
| class | 🎓 | the other class |
| date | 💑 | the other date |
| content | 😌 | content, as in satisfied |
| arguments | 🥊 | the other argument |
| reflection | 🪞 | literal, and the API |
| map | 🗺️ | a map value |
| BOM | 💣 | the other bomb |
| keeps | 🏰 | the castle keep |
| short | 🩳 | shorts |
| locals | 🍻 | the local |
| blank | 🕳️ | a hole, never a white square |
| get | 🫳 | a grabbing hand, not a page |
| counts | 🧮 | an abacus |
| every / variadic | ♾️ | all of them |
| calls | 📞 | the other call |
| clamps | 🗜️ | literal |
| refuses / none | 🚫 | literal |

Never a filler that merely decorates: 🧹 ✅ 🔧 🔢 on their own say nothing about *this* commit.

---

## The Emoji Pun Hall of Fame

Study these. Learn from them. Let them guide your spirit.

### Wordplay Legends

| Commit | Why It's Art |
|--------|--------------|
| `feat: Hidden field 🙈🚜` | "Hidden" + "field" = see-no-evil monkey + tractor |
| `feat: Table element 🪑➖🎾` | A chair, because table... ping pong table... |
| `feat: Menu 🍽️📜🛎️` | Restaurant menu! Plate, scroll, service bell |
| `feat: Date_Range field 📅🔫🏁` | Date + range (gun) + start/finish |
| `feat: Element class 🌍🌫️🔥🌊` | Earth, Air, Fire, Water—the classical elements |
| `feat: Log service 🌳🔮🧙‍♂️` | A log... that's magical... get it? |
| `feat: Range Field 🤠🔫` | Home on the range |
| `fix: soft-shadow mixin 🍦💧🕶️` | Soft serve + shadow + it's cool |

### Narrative Genius

| Commit | The Story |
|--------|-----------|
| `break: The great return of Has_WP_Post ⚰️🧛‍♂️💌` | Death → vampire resurrection → love letter |
| `feat: Iterator class 🤺🤺🤺🤺🤺` | A line of fencers, iterating through opponents |
| `feat: Factory pattern 🏭🏭🏭` | It's factories all the way down |
| `feat: Singleton pattern 🧙‍♂️🌌💠` | One wizard in the universe with a gem |
| `fix: Model overhaul 💃💃💃` | Models dancing—because they're models |
| `delete: Retire Deprecated_Component ☠️⚰️🕊️` | Death, burial, soul ascending |

### Technical Poetry

| Commit | Translation |
|--------|-------------|
| `feat: Dependency_Injection trait 📦👶💉` | Package delivers baby with injection |
| `feat: Bidirectional_Relationship 👩‍❤️‍👨🔗🌲` | Couple linked by a tree (family tree?) |
| `fix: Merge with Query_Vars 🧜‍♀️🧜‍♀️🧜‍♀️` | Mermaids merging |
| `feat: array_unique handling 🦄🦄🦄` | Unicorns. Unique. Obviously. |
| `fix: Bail early 🌊🛶🌅` | Bailing water from canoe at sunset |
| `feat: Is_Stashable trait 👉🍁🗄️` | Point to leaf in cabinet (stash) |

### Philosophical Depth

| Commit | Meaning |
|--------|---------|
| `feat: One model to rule them all ⚔️` | The Lord of the Models |
| `fix: Straggler 👨‍🔧🚶` | Just a lonely straggler walking |
| `fix: radios arn't arrays 📻🚫☀️` | Radio, forbidden, ray (array sounds like...) |
| `feat: A rather abstract user 👤` | Sometimes the commit IS the joke |

---

## Choosing Your Emojis

### Step 1: Find the Pun

Every change has a hidden pun. Your job is to find it.

- Is it a `Table`? Consider furniture: 🪑🏓
- Is it a `Field`? Consider agriculture: 🚜🌾👨‍🌾
- Is it a `View`? Consider seeing: 👁️🏞️🖼️
- Is it a `Post`? Consider mail: 📬📫✉️
- Is it a `Model`? Consider fashion: 💃👗🎭
- Is it a `Term`? Consider school: 📚🎓
- Is it a `Route`? Consider roads: 🛣️🚦
- Is it a `Hook`? Consider fishing: 🎣🐟
- Is it `abstract`? Consider art: 🎭🖼️👩‍🎨

### Step 2: Tell the Story

Your three emojis should have narrative flow:

```
Beginning → Middle → End
Subject → Action → Result
Object → Transformation → Outcome
```

**Good:** `feat: User authentication 🙍🏻‍♂️🔐✅` (user + lock + success)

**Bad:** `feat: User authentication 🎉🌈🦋` (random party vibes)

### Step 3: Consider the Vibe

| Vibe | Emojis |
|------|--------|
| Creation | ✨ 🐣 🌱 🎨 🏗️ |
| Destruction | 💥 🗑️ ☠️ ⚰️ 🔥 |
| Movement | 🚚 ➡️ 🏃 🚀 |
| Connection | 🔗 🤝 👫 🔌 |
| Data | 📊 🗃️ 💾 📦 |
| Success | ✅ 👍 🎉 💪 |
| Failure/Fix | 🔧 🛠️ 🩹 🔨 |
| Magic | 🧙‍♂️ 🔮 ✨ 🪄 |

---

## The Imperative Mood

Commit messages must be written as commands, not descriptions.

| Wrong | Right |
|-------|-------|
| `Added new feature` | `Add new feature` |
| `Fixed the bug` | `Fix the bug` |
| `Updating styles` | `Update styles` |
| `Changes to model` | `Change model` |

Think: "This commit will..." then write what comes after.

---

## Scope Guidelines

Scope is optional but helpful for larger changes:

```
feat(view): Add merge_param method 🏞️🔗⚙️
fix(query): Handle empty meta_query 🔍🕳️✅
break(model): Change validate signature ✅❌📊
```

Common scopes:
- `view`, `model`, `query`, `admin`, `field`
- `woo`, `acf`, `route`, `auth`
- Class names: `Post`, `User`, `Table`

---

## Examples from the Wild

```bash
# A feature for getting first and last names
feat: `Is_Woo_Customer::get_first_name` and `Is_Woo_Customer::get_last_name` 🙋🏻‍♂️💬💬

# Fixing order status positioning
fix: `Order_Status` position with `$priority` property 🛒📌🚩

# A meta box feature (box + vision goggles + package)
feat: `Meta_Box` 🥽📦

# Auto-resolving model class names (robot + sparkle + teacher)
feat: Auto resolve model class names by specificity 🤖✨👨‍🏫

# The debugger (bug + hammer + detective)
feat: Digitalis Debugger 👾🔨🕵🏻‍♂️

# When things get deprecated
delete: Retire `Deprecated_Component` and `Base` entirely ☠️⚰️🕊️

# Magic method implementation (phone + wizard + witch)
feat: `__call` magic method fallback 📞🔮🧙🏻‍♀️

# The creational pattern (egg + art + gem)
feat: `Creational` pattern 🐣🎨💠
```

---

## Commit Message Length

- **Body:** Optional, for complex changes
- **Emojis:** Always exactly 3

If you need more context, add a body:

```
feat: Overhaul View instantiation 💥🤯👀

Complete rewrite of View class to support full instantiation.
- Static properties now properly inherited
- ArrayAccess implemented for param access
- Lifecycle hooks added (before/after)
```

---

## The Fractal Nature of Good Code

Like a certain mathematical teapot that contains infinite complexity within a finite form, good code exhibits self-similarity at every scale. A well-structured class mirrors the architecture of the entire system. A clean method reflects the philosophy of the framework.

Your commits, too, are fractals—small units that, when viewed together, tell the complete story of the codebase's evolution. Each emoji triplet is a tiny pot of tea, steaming with meaning, infinitely divisible yet perfectly whole.

Some say if you zoom in far enough on the commit history, you'll see the pattern repeating. Class within class. Trait within trait. Emoji within emoji.

But that's probably just the caffeine talking.

---

## Pull Request Guidelines

1. **Branch naming:** `feature/descriptive-name` or `fix/issue-description`
2. **Commits:** Squash if messy, keep if they tell a story
3. **Description:** Explain the why, not just the what
4. **Tests:** If it breaks, you bought it
5. **Strings:** after adding a framework-authored string, regenerate the template with `wp i18n make-pot . languages/lattice.pot --domain=lattice --exclude=docs,scss,assets,compat,templates,bin,stubs,tmp --skip-js --skip-audit --headers='{"Project-Id-Version":"Lattice"}'` (`--skip-audit` because the `%s` scaffold strings need no translator comments)
6. **Boundaries:** run `php bin/boundaries.php` before opening a PR; CI runs it on pushes to `main` and `v1` and on every pull request. `include/{core,models,views,routing,query,registration}` never name an ACF, WooCommerce or page-builder symbol
7. **PHPStan:** `composer install` once, then `vendor/bin/phpstan analyse` over the whole tree, or `include/<dir>` for a quick look at the directories you touched (level 0 must stay clean everywhere), and `vendor/bin/phpstan analyse -c phpstan.level5.neon` over the whole tree (level 5 must stay clean; about three minutes within 1.2 GB; never one directory with that config, which reports traits used elsewhere as unused; CI runs both through `phpstan.ci.neon` and `phpstan.level5.ci.neon`, the same configs plus workers). Level 5 covers everything but `include/deprecated` (level 0 until the deprecation pass removes it) and `bin/` (a development tool, level 0 only; never loaded by a consumer); never add a baseline or an `ignoreErrors` entry. Optional subclass hooks are declared with `@method` on the class, never with an ignore. A View's params read as properties (and a `Query_Vars`'s vars) are a crate for PHPStan (`universalObjectCratesClasses`): declare nothing for them, and know that a typo of a declared instance property on a View subclass is not caught either (static properties still are). A trait the framework ships for consumers and uses nowhere itself gets a user in `stubs/trait-users.stub.php` (analysed at both levels, never loaded), so PHPStan analyses it instead of reporting it unused. A `__call` forwarder carries `@mixin` naming the class it forwards to (`Element`, `Is_Woo_Customer`), or `@method` rows where the stub's return type is wrong (`Order_Item::get_order()`). A third-party signature the stubs get wrong is corrected in a `stubFiles` entry under stubs/ (WP_Comment's constructor, `stubs/wp-comment.stub.php`), never at the call site
8. **Variance:** after changing the signature of any non-final method, or a property a subclass may redeclare, run `php bin/variance.php ~/lattice-consumers/{courses,d-pace,eventropy,mycelium,somm,study-hub}` (all six in one run: mycelium and somm extend eventropy, courses extends somm; mesla is retired). Exit 1 names each consumer member that would fatal at class-declaration time against the tree as it stands, with the rule PHP applies; `CHECK` lines are relations to WordPress, WooCommerce or ACF classes it cannot judge and never fail; `--matrix` prints every override of every framework member with its signature shapes, which is the migration list for a method gaining a return type. Not in CI because it needs the consumers
9. **Typing a parameter:** follow the rules in CODING_STANDARDS (`### Parameter types`). Before the type is written, read every caller of the method across the framework and the consumers for a `null`, a literal of another type, or a `?? fallback` in that position; a by-reference parameter also needs each caller's variable checked, since an unassigned one arrives as `null`. Then run the variance audit (item 8) before and after. A hit means the type is widened to what callers pass, or the parameter stays bare; a type is never written narrower than its callers

---

## Code Style

- **Self-documenting code** over comments
- **Descriptive names** over explanatory comments
- **PSR-4 autoloading** with Digitalis conventions
- **Fluent interfaces** where sensible
- **Traits** for shared behavior
- **Static properties** for class configuration

See [AUTOLOADER.md](./AUTOLOADER.md) for file naming conventions.

---

## Final Wisdom

Before you commit, ask yourself:

1. Would this commit message make sense in 6 months?
2. Do the emojis tell a story?
3. Is there a pun I'm missing?
4. Have I achieved emoji enlightenment?

If you've answered yes to all four, you're ready.

Welcome to the framework. May your commits be atomic and your emojis be dank.

```
🎭 Happy Contributing 🎪
        🎠
```
