# Post Type Class

The `Digitalis\Taxonomy` class provides scaffolding to rapidly deploy custom taxonomies in WordPress.

## Required Member Variables

**`protected $slug: string`**

The permanent identifier of the taxonomy.

---

**`protected $post_types: array`**

Array of post type slugs associated with the taxonomy.

---

**`protected $singular: string`**

The label of the taxonomy when referencing a single instance (e.g 'Grade').

---

**`protected $plural: string`**

The label of the taxonomy when referencing multiple instances (e.g 'Grades').

## Optional Member Variables

**`protected $text_domain: string`**

The text domain to use when setting the post type labels.

## Optional Method Overrides

**`public function run ()`**

Use to add additional functionality to the post type. Called immediately after class initialisation.

## Optional Methods

Define these methods within your derived class to use these features.

**`public function columns ($columns: array): array`**

Return an array of columns (slug, label pairs) to appear in the admin taxonomy table.

---

**`public function column ($output: string, $column: string, $term_id: int): string`**

Return the content for a given term_id and column slug in the admin taxonomy table.