# Post Type Class

The `Digitalis\Post_Type` class provides scaffolding to rapidly deploy custom post types in WordPress.

## Required Member Variables

**`protected $slug: string`**

The post types permanent identifier.

---

**`protected $archive: string|false`**

The post types archive url. Set `false` to disable the archive.

---

**`protected $icon: string`**

Icon to be displayed in wp-admin. See [dashicons](https://developer.wordpress.org/resource/dashicons/#chart-area).

---

**`protected $singular: string`**

The label of the post type when referencing a single instance (e.g 'Book').

---

**`protected $plural: string`**

The label of the post type when referencing multiple instances (e.g 'Books').

## Optional Member Variables

**`protected $model_class: string|false`**

Accepts a fully qualified class name of a class that extends `Digitalis\Model`. When this parameter is set, the corresponding model will automatically be instantiate models upon `template_redirect`.

---

**`protected $text_domain: string`**

The text domain to use when setting the post type labels.

---

**`protected $filters: array`**

Array of filters to appear in the admin ui for the post type. See the [Admin Filters](#admin-filters) section for more information.

## Optional Method Overrides

**`public function run ()`**

Use to add additional functionality to the post type. Called immediately after class initialisation.

---

**`public function get_args ($args: array): array`**

Return an array of post type args as detailed in [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/). __Note:__ The `labels`, `supports` and `rewrite` keys can be defined by the `get_labels`, `get_supports` and `get_rewrite` methods respectively.

---

**`public function get_labels ($args: array): array`**

Return an array of post type labels as detailed in [get_post_type_labels](https://developer.wordpress.org/reference/functions/get_post_type_labels/).

---

**`public function get_supports ($args: array): array`**

Return an array of post type supports. These include `'title'`, `'editor'`, `'comments'`, `'revisions'`, `'trackbacks'`, `'author'`, `'excerpt'`, `'page-attributes'`, `'thumbnail'`, `'custom-fields'`, and `'post-formats'`.

**Note:** The `$this->remove_support($support)` method can be used to remove the default supports. See the [Other Methods](#other-methods) section for more information.

---

**`public function get_rewrite ($args: array): array`**

Return an array of post type rewrite options as detailed in [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/).

## Optional Methods

Define these methods within your derived class to use these features.

**`public function columns ($columns: array): array`**

Return an array of columns (slug, label pairs) to appear in the admin post table.

---

**`public function columns ($column: string, $post_id: int)`**

Echo the content for a given post_id and column slug in the admin post table.

---

**`public function after_insert ($post_id: int, $post: WP_Post, $update: bool, $post_before: null|WP_Post)`**

Method will fire on action [`wp_after_insert_post`](https://developer.wordpress.org/reference/hooks/wp_after_insert_post/) for the given post type.

---

**`public function ajax_query()`**

When this method is defined an ajax action with key `query_{$post_type_slug}` will be registered. __Note:__ Unlike regular WordPress ajax actions, query parameters will automatically be parsed and loaded into the main `$wp_query` global query. This allows post types to be easily queried from the frontend using the regular WordPress query syntax.

## Optional Static Method Overrides

**`public static function get_query_vars (): array`**

Return an array of query_vars as defined in [WP_Query Parameters](https://developer.wordpress.org/reference/classes/wp_query/#parameters). These parameters will be used as the default query args when performing a main query on the post type.

---

**`public static function get_admin_query_vars (): array`**

These parameters will be used as the default query args when performing a main __admin__ query on the post type.

## Other Methods

**`public function remove_support ($support: string)`**

Call within the `get_supports` method to remove a support.

```php
protected function get_supports ($supports) {

    $this->remove_support('comments');

    return $supports;

}
```

## Admin Filters

The member variable `protected $filters`, defines an array of filters to appear in the admin ui for the post type. The following array element formats are accepted:

- `key => $type`
- `key => ['type' => $type, 'args' => [ ... ]]`
- `$inbuilt_type`

### Filter Types

#### Taxonomy Filters

Filters posts by a given taxonomy. See [wp_dropdown_categories](https://developer.wordpress.org/reference/functions/wp_dropdown_categories/) for a list of the accepted options in the `args` array.

##### Basic Example:

```php
protected $filters = [
    'event-category' => 'taxonomy',
];
```

##### Example with Arguments:

```php
protected $filters = [
    'event-category' => [
        'type' => 'taxonomy',
        'args' => [
            'show_option_none' => 'Select a Category',
            'class' => 'ui-filter',
        ]
    ],
];
```

#### Advanced Custom Field Filters

Filters posts by a given advanced custom field. Accepted options in the `args` array:

- `hide_falsy` Whether to hide falsy values (Default `true`)
- `compare` The operator to use when filtering posts (Default `'='`)

##### Basic Example:

```php
protected $filters = [
    'book-tag' => 'acf',
];
```

#### Inbuilt WordPress Filters

By default the inbuilt months dropdown filter is disabled. To enable it add an item to the `$filters` array with a value of `'publish_month'`:

```php
protected $filters = [
    'publish_month',
];
```
