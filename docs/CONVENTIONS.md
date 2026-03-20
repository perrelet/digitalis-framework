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
