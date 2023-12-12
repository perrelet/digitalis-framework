## Breaking Changes

|Commit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|Class|Description|Change|
|---|---|---|---|
|[12-12-2023](/../../commit/e059b47aadc84b15da1e6bed35b2c9c114ca74f1)|`Digitalis\Post`|New method signature|`public static function get_admin_query_vars ($args = [])`|
|[12-12-2023](/../../commit/e059b47aadc84b15da1e6bed35b2c9c114ca74f1)|`Digitalis\Post`|New method signature|`public static function get_query_vars ($args = [])`|
|[30-11-2023](/../../commit/9dd6907eb03b4956c85a5ac760295631105c8b8e)|`Digitalis\Model`|New method signature|`public static function validate ($data, $uid, $id)`|
|[22-11-2023](/../../commit/7af0edb85a60bbffbc1cabf9e21b2093726b375e)|`Digitalis\Model`|New method signature|`public function __construct ($data = null, $uid = null, $id = null)`|
|[21-11-2023](/../../commit/2ebb045cd6ec54434e763b39a89a9e43e12586f2)|`Digitalis\Posts_Table`|New method signature|`public function columns (&$columns)`|
|[17-11-2023](/../../commit/7d148430bf9f9306aa9471e6b65a8767ac452e7f)|`Digitalis\Model` `Digitalis\Singleton`|Filter `Digitalis/Class/...` name changed|Namespaced class names now use forward slashes|
|[17-11-2023](/../../commit/7d148430bf9f9306aa9471e6b65a8767ac452e7f)|`Digitalis\Model`|New method signature|`public function init ()`|
|[16-11-2023](/../../commit/9bd3ad7616431c7c34aa2dc799b2fb421eb22789)|`Digitalis\Archive`|New method signature|`protected static function get_items ($query_vars, &$query, $skip_main)`|
