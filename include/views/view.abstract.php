<?php

namespace Lattice;

abstract class View implements \ArrayAccess {

    use Dependency_Injection, Inherits_Props;

    protected static $defaults      = [];      // Default params. Inherited by all derivative classes. 
    protected static $required      = [];      // Param keys that are required.
    protected static $merge         = [];      // Param keys to be merged (rather than overridden) in derivative classes.
    protected static $skip_inject   = [];      // Param keys that should skip dependency injection.
    protected static $editors       = [];      // Editor slugs to publish elements too.
    protected static $controls      = [];      // Exposed UI Controls when rendering inside a page editor.
    protected static $name          = null;

    protected static $template      = null;    // The name of the template file to load (omit .php extension).
    protected static $template_path = __DIR__; // Absolute path to the template directory.

    protected static $indexes = [];
    protected static $loaded_views = [];

    protected static $inherited_props = [
        'defaults',
        'required',
        'merge',
        'skip_inject',
    ];

    public static function static_init () {

        if ((new \ReflectionClass(static::class))->isAbstract()) return;

        self::$loaded_views[] = static::class;

    }

    public static function get_loaded_views () {

        return self::$loaded_views;

    }

    public static function get_editors () {

        return apply_filters('lattice.view.editors', static::$editors, static::class);

    }

    public static function get_controls () {

        return apply_filters('lattice.view.controls', static::$controls, static::class);

    }

    public static function get_name () {

        if (static::$name) return static::$name;
        return ucwords(str_replace(['\\', '_'], ' ', static::class));

    }

    public static function supports_editor ($editor) {

        $slug    = ($editor instanceof Editor) ? $editor->get_slug() : $editor;
        $editors = static::get_editors();

        return in_array('all', $editor) || in_array($slug, $editor);

    }

    public static function render ($params = [], $print = true) {

        $class_name = Call::get_class_name(static::class);
        return (new $class_name($params))->print(!$print);

    }

    public static function get_defaults () {

        return static::get_inherited_prop('defaults', static::get_merge_keys());

    }

    public static function get_required_keys () {

        return static::get_inherited_prop('required');
    
    }

    public static function get_merge_keys () {

        return static::get_inherited_prop('merge');
    
    }

    public static function get_skip_inject_keys () {

        return static::get_inherited_prop('skip_inject');
    
    }

    //

    protected $params      = [];
    protected $constructed = false;

    public function __construct ($params = []) {
    
        $this->set_params(static::get_defaults());
        $this->merge_params($params);

        $this->constructed = true;

    }

    public function __toString() {

        return $this->print(true);

    }

    public function print ($return = false) {

        if (Strict::enabled() && !$this->constructed) $this->strict_constructor();

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 0;

        $this->set_param('view_index', self::$indexes[static::class]);
        $this->inject_dependencies($this->params, static::get_defaults());

        if (!(Strict::enabled() ? $this->strict_prepare() : $this->prepare())) return '';

        self::$indexes[static::class]++;

        if ($return) ob_start();

        if ($this->is_first()) $this->before_first();
        $this->before();

        if ($template = $this->get_template()) {

            $path = realpath(trailingslashit($this->get_template_path()) . $template . '.php');

            if (file_exists($path)) {

                extract($this->params, EXTR_OVERWRITE);
                require $path;

            }

        } else {

            $this->view();

        }

        if ($this->is_first()) $this->after_first();
        $this->after();

        if ($return) {

            $html = ob_get_contents();
            ob_end_clean();
            return $html;

        }
    
    }

    protected function prepare () {

        if (!$this->pre_validate()) return false;

        $this->params($this->params);

        return $this->validate();

    }

    protected function inject_dependencies (&$params, $defaults) {

        foreach (static::get_skip_inject_keys() as $key) if (isset($defaults[$key])) unset($defaults[$key]);
    
        static::array_inject($params, $defaults);
    
    }

    public function params (&$p) {}

    protected static function get_injected_class ($key, $defaults) {

        $class_name = $defaults[$key] ?? null;

        return (is_string($class_name) && class_exists($class_name)) ? $class_name : null;

    }

    public function pre_validate () {

        $defaults = static::get_defaults();

        foreach (static::get_required_keys() as $key) {

            if (!($class_name = static::get_injected_class($key, $defaults))) continue;
            if (!(($this[$key] ?? null) instanceof $class_name))             return false;

        }

        return true;

    }

    public function validate () {

        if (!$this->required())   return false;
        if (!$this->permission()) return false;
        if (!$this->condition())  return false;

        return true;
    
    }

    public function required () {
    
        $defaults = static::get_defaults();

        foreach (static::get_required_keys() as $key) {

            $value = $this[$key] ?? null;

            if ($class_name = static::get_injected_class($key, $defaults)) {

                if (!($value instanceof $class_name)) return false;

            } else {

                if (is_null($value)) return false;

            }

        }

        return true;
    
    }

    public function permission () {
    
        return true;
    
    }

    public function condition () {
    
        return true;
    
    }

    public function get_template_path () {
        
        return static::$template_path;
    
    }

    public function get_template () {
        
        return static::$template;
    
    }

    public function before_first () {}
    public function before       () {}
    public function view         () {}
    public function after_first  () {}
    public function after        () {}

    // Params

    public function get_params () {
    
        return $this->params;
    
    }

    public function set_params ($params) {
    
        $this->params = $params;
        return $this;
    
    }

    public function merge_params ($params) {
    
        $this->params = static::deep_parse_args($params, $this->params, static::get_merge_keys());
        return $this;
    
    }

    public function get_param ($key) {
    
        return $this->params[$key] ?? null;
    
    }

    public function set_param ($key = null, $value = null) {
    
        if (is_null($key)) {
            $this->params[] = $value;
        } else {
            $this->params[$key] = $value;
        }

        return $this;
    
    }

    public function unset_param ($key) {
    
        unset($this->params[$key]);
        return $this;
    
    }

    public function has_param ($key) {
    
        return isset($this->params[$key]);
    
    }

    public function merge_param ($key, ...$values) {

        $values             = array_map(fn($value) => is_array($value) ? $value : [$value], $values);
        $values             = call_user_func_array('array_merge', $values);
        $this->params[$key] = array_merge($this->params[$key], $values);
        static::make_list_unique($this->params[$key]);
        return $this;
    
    }

    public function get_index () {
    
        return $this->get_param('view_index');
    
    }

    public function is_first () {
    
        return $this->get_param('view_index') === 0;
    
    }

    // Property Overloading

    public function &__get ($key) {

        if (isset($this->params[$key])) { // Terinaries, null coalesce, etc cause `Only variable references should be returned by reference`

            return $this->params[$key];

        } else {

            $null = null;
            return $null;

        }

    }

    public function __set ($key, $value) {

        return $this->set_param($key, $value);

    }

    public function __unset ($key) {

        return $this->unset_param($key);

    }

    public function __isset ($key) {

        return $this->has_param($key);

    }

    // ArrayAccess

    public function &offsetGet (mixed $key): mixed { // Return by reference, see: https://www.php.net/manual/en/arrayaccess.offsetget.php

        return $this->__get($key);

    }

    public function offsetSet (mixed $key, mixed $value): void {

        $this->__set($key, $value);

    }

    public function offsetUnset (mixed $key): void {

        $this->__unset($key);

    }

    public function offsetExists (mixed $key): bool {

        return $this->__isset($key);

    }

    // Strict

    // Runs once per walked subclass at load time, so one boot lists every problem.
    public static function strict_audit (string $class) {

        $reflection = new \ReflectionClass($class);
        $class      = $reflection->getName();
        $abstract   = $reflection->isAbstract();

        if (!$abstract && !in_array($class, self::$loaded_views)) self::strict_audit_static_init($class);

        if (self::declares($class, 'params') && ($victim = self::params_victim($class)) && !self::calls_parent($class, 'params')) {

            Strict::violation($class, "params() overrides {$victim}::params() without calling it, so {$victim}'s work never runs.", "Call parent::params(\$p): last if it reads params you set, first if you use what it builds. If its work is unwanted, extend its parent instead.");

        }

        if (!$abstract) self::strict_audit_required($class);

        if (!is_a($class, Layout::class, true) && !is_a($class, Page_View::class, true)) self::strict_audit_resolvable($class, $reflection);

    }

    protected static function strict_audit_static_init ($class) {

        static $reported = [];

        $culprit = (new \ReflectionMethod($class, 'static_init'))->getDeclaringClass()->name;

        if (isset($reported[$culprit])) return;
        $reported[$culprit] = true;

        $problem = ($culprit === $class)
            ? "static_init() overrides View::static_init() without calling it, so the view never registers in get_loaded_views()."
            : "inherits {$culprit}::static_init(), which never calls parent::static_init(), so the view never registers in get_loaded_views().";

        Strict::violation($class, $problem, "Call parent::static_init() first in {$culprit}::static_init().");

    }

    protected static function strict_audit_required ($class) {

        $defaults = (array) $class::get_defaults(); // Under strict this primes the Inherits_Props cache at load time rather than at first print.

        foreach ((array) $class::get_required_keys() as $key) {

            $default = $defaults[$key] ?? null;

            if (is_null($default))                                                                     continue;
            if (is_string($default) && (str_contains($default, '\\') || class_exists($default))) continue; // A class string, even from a plugin that loads later.

            $shown = json_encode($default);
            if (strlen($shown) > 40) $shown = substr($shown, 0, 37) . '...';

            Strict::violation($class, "\$required lists '{$key}' but \$defaults gives it {$shown}, so required() can never fail.", "Default it to null in the class that requires it, or test emptiness in condition() and drop it from \$required.");

        }

    }

    protected static function strict_audit_resolvable ($class, $reflection) {

        foreach (['context', 'post_type', 'taxonomy', 'term', 'priority'] as $prop) {

            if (!$reflection->hasProperty($prop)) continue;

            $property = $reflection->getProperty($prop);

            if (!$property->isStatic() || ($property->getDeclaringClass()->name !== $class)) continue;

            Strict::violation($class, "declares static \${$prop}, which only Layout and Page_View subclasses resolve.", "Extend Page_View, or rename it if it means something else here.");

        }

    }

    protected function strict_constructor () {

        $culprit = static::class;

        for ($class = static::class; $class && ($class !== self::class); $class = get_parent_class($class)) {

            if (self::declares($class, '__construct') && !self::calls_parent($class, '__construct')) { $culprit = $class; break; }

        }

        Strict::fail(static::class, "{$culprit}::__construct() never calls parent::__construct(), so get_defaults() and merge_params() never ran.", 'Call parent::__construct($params) first.');

    }

    // Anything printed during the prepare phase is markup that left params(); the guard exists only under strict.
    protected function strict_prepare () {

        $level = ob_get_level();

        if (!ob_start()) return $this->prepare(); // No buffer can open inside an output handler.

        try {

            $ok = $this->prepare();

        } finally {

            $now  = ob_get_level();
            $leak = ($now === $level + 1) ? ob_get_contents() : '';

            while ((ob_get_level() > $level) && ob_end_clean());

        }

        $where = 'during the prepare phase (pre_validate/params/validate, own or inherited)';

        if ($now < $level + 1) Strict::fail(static::class, "closed an output buffer it did not open {$where}.", 'Only close buffers params() itself opened.');
        if ($now > $level + 1) Strict::fail(static::class, "opened an output buffer {$where} and never closed it.", 'Balance every ob_start() before params() returns.');

        if ($leak !== '') {

            $snippet = trim(preg_replace('/\s+/', ' ', $leak));
            if (strlen($snippet) > 80) $snippet = substr($snippet, 0, 77) . '...';

            Strict::fail(static::class, "emits output {$where}: \"{$snippet}\". A displayed PHP notice counts.", 'Move markup to view(), a template or before()/after(); render sub-views to a string with (string) new Sub_View([...]).');

        }

        return $ok;

    }

    protected static function params_victim ($class) {

        for ($victim = get_parent_class($class); $victim && ($victim !== self::class); $victim = get_parent_class($victim)) {

            if (self::declares($victim, 'params')) return $victim;

        }

        return null;

    }

    protected static function declares ($class, $method) {

        return method_exists($class, $method) && ((new \ReflectionMethod($class, $method))->getDeclaringClass()->name === $class);

    }

    // Token scan of the declared body for `parent::$method`, so a comment cannot fool it. An early return past the call can; that hole is documented.
    protected static function calls_parent ($class, $method) {

        $reflection = new \ReflectionMethod($class, $method);
        $file       = $reflection->getFileName();

        if (!$file || !is_file($file)) return true;

        $lines  = array_slice(file($file), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1);
        $tokens = array_values(array_filter(token_get_all('<?php ' . implode('', $lines)), fn ($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])));

        foreach ($tokens as $i => $token) {

            if (!is_array($token) || ($token[0] !== T_STRING) || strcasecmp($token[1], 'parent'))   continue;
            if (!is_array($tokens[$i + 1] ?? null) || ($tokens[$i + 1][0] !== T_DOUBLE_COLON))    continue;
            if (is_array($tokens[$i + 2] ?? null) && !strcasecmp($tokens[$i + 2][1], $method))      return true;

        }

        return false;

    }

}
