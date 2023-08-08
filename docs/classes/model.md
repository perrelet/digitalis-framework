# Model Class

[Digitalis/Model](https://github.com/perrelet/digitalis-framework/blob/main/include/objects/model.abstract.php) provides a base class from which all other model classes are derived.

## Optional Static Method Overrides

**`public static function extract_id ($data = null): string`**

`$data: mixed` The first parameter of the models constructor.

Return the id of the models instance in the database or other data store.

**`public static function extract_uid ($id, $data = null): string`**

`$id: string` The value returned by `extract_id`.

`$data: mixed` The first parameter of the models constructor.

Return a unique id for the model instance. Useful in situations where a single database model needs to be instantiated with varying configurations. For example, when generating recurring event instances.

**`public static function validate ($data): bool`**

`$data: mixed` The first parameter of the models constructor.

Called before the model is instantiated. Given the models constructor data, is this a valid model to construct?

**`public static function validate_id ($id): bool`**

`$id: string` The value returned by `extract_id`.

Called before the model is instantiated. Is the model's id a valid value?

## Optional Method Overrides

**`public function run ()`**

Add additional functionality to the model. Called immediately after model instantiation.

**`public function get_global_var (): string`**

Return the global variable name for this instance when it is automatically instantiated.

## Other Methods

**`public function is_first_instance (): bool`**

Whether this is the first instance of the model type.

