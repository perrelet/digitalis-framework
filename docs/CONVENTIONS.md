# Lattice — Conventions

Preferred patterns where more than one syntax is valid. These won't break anything, but new code should follow the preferred form for consistency.

---

## Code style: read this first, it is not optional

Every agent working in this codebase follows these rules. They are the house style, and code that breaks them gets sent back.

1. **Code is self-documenting.** Names carry the meaning. Name a method for what it does, starting with a verb: `register_fields()`, `build_query()`, `find_account()`, `is_expired()`. Not a noun: `fields()`, `query()`, `account()`, `expiry()`.
2. **Comments are reserved only for genuine gotchas and important references** that are not evident from reading the code: a coupling to another file, behaviour a maintainer would otherwise "fix" wrongly, a section of a spec. A comment never restates what the code, or a method's name, already says.
3. **Less is more.** Where a comment stays, it is one line, as terse and simple as possible. No docblocks, no multi-line explanations, no narrating the design. If it will not fit on one line, cut it.
4. **Very short labelling comments** may split a large class into sections: `// Records`, `// Policy`.

Before you commit, list every comment you added with `git diff | grep -E '^\+\s*//'` and delete any that is not a gotcha or a reference. Reviews do not check this; you do.

Right: `// User ids overlap post ids: a bare number is never an account.`
Wrong: `// Finds the account by its id and returns it, or null when there is none.`

---

## Views

### Instantiate views with `new` — don't call `View::render()` statically

`View::render()` is supported for backwards compatibility, but the preferred form is object instantiation. Views are objects — treat them as such. The `new` form is consistent whether you're echoing, assigning, or passing a view as a value.

```php
// Supported, but not preferred
<?= View::render([...]) ?>
<?= View::render([...], false) ?>

// Preferred — instantiate directly
<?= new My_View([...]) ?>

// When you need a string explicitly
$html = (string) new My_View([...]);
```

The `(string)` cast is optional when echoing (PHP calls `__toString()` automatically), but makes intent clear when assigning to a variable or passing to a function that expects a string.

### Populate `$content` via sub-view instantiation in `Component` subclasses

`Component` subclasses accept pre-rendered content through the `$content` param. Instantiate the sub-view and assign it — `__toString()` returns the rendered string and the sub-view runs its own full lifecycle.

```php
public function params (&$p) {
    $p['content'] .= new Sub_View;
    parent::params($p);
}
```

Buffering markup with `ob_start()` inside `params()` is a lifecycle bypass — see [ANTIPATTERNS — Only render markup from the render phase](./ANTIPATTERNS.md#only-render-markup-from-the-render-phase).

---

## Field_Group Fields

### Instantiate field objects with `new` — don't use the array `'field'` shorthand

`Field_Group` accepts fields as either associative arrays (with a `'field'` key naming the class) or as instantiated objects. The array shorthand is supported for backwards compatibility; the preferred form is direct instantiation.

```php
// Supported, but not preferred
[
    'field'       => \Lattice\Field\Input::class,
    'name'        => 'first_name',
    'label'       => 'First Name',
    'type'        => 'text',
],

// Preferred — instantiate directly
new \Lattice\Field\Input([
    'name'        => 'first_name',
    'label'       => 'First Name',
    'type'        => 'text',
]),
```

Direct instantiation makes the type explicit, is consistent with how views are instantiated, and avoids the indirection of a string class reference inside an array.

---

## Layout & Page_View

### Let auto-specificity handle resolution order — don't set `$priority` unless breaking a tie

`$priority` defaults to `null`, which means specificity is auto-calculated from `$context` and `$post_type` (+10 each). Only set `$priority` when two candidates share the same config and you need one to win (e.g. a condition-narrowed variant).

### Use `$layout` overrides to control shell parts — don't subclass Layout for minor changes

If a Page_View only needs to hide the header or swap the footer, use `$layout` overrides rather than creating a dedicated Layout subclass.

```php
// Preferred — layout override on the Page_View
class Fullscreen_Page extends Page_View {
    protected static $layout = ['header' => false, 'footer' => false];
}

// Reserve Layout subclasses for structurally different shells
class Dashboard_Layout extends Layout {
    protected static $defaults = ['sidebar' => Sidebar::class, 'body' => null];
}
```

---

## Features

### One owner declares a feature; everyone else finds it

A feature's name is its naming property (`id` unless `$cache_property` says otherwise, `'slug'` for admin pages): the name `declare()` was given, else the class's declared default, else its class. A feature is single by default: one instance, found bare. A bare `get_instance()` answers it and builds it with its defaults on first use; `declare($config)` builds it live with a config under its default name. A feature other code reaches by name declares a default (`protected $id = 'proposals';`), so a base class finds it whichever way it was built. A family base declares no default (its placeholder is `null`), so only a concrete class names itself.

A class built several times sets `protected static $named = true`. It is built only by `declare($name, $config)`, a bare find on it answers `null` and a find by name answers the holder or `null`, never building one. `load_feature()` with options declares the feature, taking a named class's name from the options' naming property.

```php
$this->load_feature('lattice/dev-role');
$this->load_feature('acf/acf-focal-point.feature.php', ['group_key' => 'group_event_image']);

class Gallery_Sync extends Feature {
    protected static $named = true;
}

Gallery_Sync::declare('event-images',   ['source' => 'event']);
Gallery_Sync::declare('teacher-photos', ['source' => 'teacher']);

Dev_Role::get_instance();                       // the single feature, built bare on first find
\Lattice\ACF\Focal_Point::get_instance();       // single: one field group, found bare
Gallery_Sync::get_instance('event-images');     // that config, from any class; null if no owner declared it
```

A name is built once. A second `declare()` of a live name is refused aloud, same class included: strict throws, and outside strict one log line names the app that built the holder and the existing instance answers. An owner whose `declare($config)` comes after a bare find has already built the defaults is refused the same way, so the owner declares first and everyone else finds. A single class is refused any name but its default.

Names are unique per family. Every feature shares one family, so a base class finds any feature by name and two classes claiming one name are refused. A family that declares its own `$cache_group` (meta boxes, profile sections, admin pages, shortcodes, blocks, account pages, routes) is a namespace of its own, so its base class finds any member by name: `Meta_Box::get_instance('somm-keywords')` finds the `Keywords_Metabox`. The base class and the holding class find it; a sibling class asking for it gets `null`. `create()` stays what it is everywhere: a new inert instance nothing finds and nothing switches on.

### Declare what a feature needs: `$requires` for features, `instance_condition()` for everything else

A library feature that needs another lists it by path. `load_feature()` loads each requirement before the feature, so a site loads features in any order. A feature that needs a plugin or a function says so in `instance_condition()`, the gate every build path asks (with the `lattice.instantiate` opt-out): refused, it never exists, so there is nothing to half-disable.

```php
class Proposals extends Feature {

    protected static $requires = ['lattice/resources', 'lattice/change-sets'];

    public static function instance_condition ($data = []) {

        return function_exists('acf_get_field') && parent::instance_condition($data);

    }

}
```

A requirement loads with its defaults. A site that configures one loads it first; configuring it after it loaded as a requirement is reported, since its options are ignored. A requirement that can't be found, a circular one or a refused condition stops the feature, and `load_feature()` reports it with `_doing_it_wrong()`, which reaches the debug log in every context. Don't check for another feature with `class_exists()`: the class exists as soon as its file is included, whether or not the feature runs. A feature built by any path, a bare find included, loads its `$requires` first, as through `load_feature()`. `load_feature()`'s options are an array; anything else is refused.
