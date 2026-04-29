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
