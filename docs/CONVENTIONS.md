# Digitalis Framework — Conventions

Preferred patterns where more than one syntax is valid. These won't break anything, but new code should follow the preferred form for consistency.

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
    'field'       => \Digitalis\Field\Input::class,
    'name'        => 'first_name',
    'label'       => 'First Name',
    'type'        => 'text',
],

// Preferred — instantiate directly
new \Digitalis\Field\Input([
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

### Name a configured feature with `id` when anything needs to reach it

A feature's name is its naming property: from its config, else the class's declared default, else its class. A feature that other code reaches declares a default (`protected $id = 'proposals';`), so it is found whether it was loaded bare or with config. A feature can also be booted from config as many times as needed, each config its own instance; give one an `id` and it is found by that name. A config with no name anywhere runs anonymously: a later bare `inst()` cannot find it and builds a second instance with every hook again.

```php
$this->load_feature('lattice/dev-role');
$this->load_feature('acf/acf-focal-point.feature.php', ['id' => 'event-images', 'group_key' => 'group_event_image']);
$this->load_feature('acf/acf-focal-point.feature.php', ['id' => 'teacher-photos', 'group_key' => 'group_teacher_photo']);

Dev_Role::inst();                                    // the unconfigured feature
\Digitalis\ACF\Focal_Point::inst('event-images');    // that config, from any class
```

The name is whichever property `$cache_property` points to, `id` by default; a feature named otherwise sets its own (`'slug'`), and `null` opts out of names altogether. Loading the same name again returns the instance already running; its new config is ignored. Names are scoped to the feature's class, so unrelated features may share one. A family that declares its own `$cache_group` shares one namespace instead, so its base class finds any member by name: `Meta_Box::inst('somm-keywords')` finds the `Keywords_Metabox`. Within a family a name is one instance, whichever subclass asks, so keep names unique there (as meta box ids already are); two classes declaring the same one both run, each found by its class, with a warning. `create()` stays what it is everywhere: a new instance nothing can find again.

### Declare what a feature needs: `$requires` for features, `instance_condition()` for everything else

A library feature that needs another lists it by path. `load_feature()` loads each requirement before the feature, so a site loads features in any order. A feature that needs a plugin or a function says so in `instance_condition()`, the gate `get_instance()` already asks: refused, it never exists, so there is nothing to half-disable.

```php
class Proposals extends Feature {

    protected static $requires = ['lattice/resources', 'wp/stored-markup'];

    public static function instance_condition ($data = []) {

        return function_exists('acf_get_field') && parent::instance_condition($data);

    }

}
```

A requirement loads with its defaults. A site that configures one loads it first; the later load returns that instance. A requirement that can't be found, a circular one or a refused condition stops the feature, and `load_feature()` reports it with `_doing_it_wrong()`, which reaches the debug log in every context. Don't check for another feature with `class_exists()`: the class exists as soon as its file is included, whether or not the feature runs.
