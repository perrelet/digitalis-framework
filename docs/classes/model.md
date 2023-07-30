# Model Class

[Digitalis/Model](https://github.com/perrelet/digitalis-framework/blob/main/include/objects/model.abstract.php) provides a base class from which all other model classes are derived.

## Optional Static Method Overrides

**`public static function extract_id ($data = null): string`**

Given the models constructor `$data`, return the id of the models instance in the database or other data store.

**`public static function extract_uid ($id, $data = null): string`**

| Parameter | Description |
| - | - |
| `$id: string` | Title |
| `$data: mixed` | Text |

`$id: string`