# Model Class

[Digitalis/Model](https://github.com/perrelet/digitalis-framework/blob/main/include/objects/model.abstract.php) provides a base class from which all other model classes are derived.

## Optional Static Method Overrides

**`public static function extract_id ($data = null): string`**

`$data: mixed` The first parameter of the models constructor.

Return the id of the models instance in the database or other data store.

**`public static function extract_uid ($id, $data = null): string`**

`$id: string` The value returned from `extract_id`
`$data: mixed` The first parameter of the models constructor.


