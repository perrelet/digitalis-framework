<?php

namespace Lattice;

use Exception;

class Model extends Factory {

    public static function get_auto_instantiation () {
        
        return false;
    
    }

    protected static $instances = [];

    public static function prepare_data (&$data) {}

    public static function extract_id ($data = null) {

        if (is_numeric($data))     return (int) $data;
        if ($data instanceof self) return $data->get_id();
        if (!is_scalar($data))     return null;

        return $data;

    }

    public static function validate_data ($data) {

        return true;

    }

    public static function validate_id ($id) {

        return true;

    }

    public static function get_specificity () {
    
        return 0;
    
    }

    public static function get_auto_resolve () {

        // Resolve to a subclass when one is more precise (higher specificity).
        // Falling through: spec-0 resolves anyway (legacy; nothing more
        // specific possible), else short-circuit — no resolution work to do.

        $spec = static::get_specificity();
        $map  = static::$class_map[static::class] ?? [];

        foreach ($map as $sub_class => $sub_spec) {
            if ($sub_spec > $spec) return true;
        }

        return !$spec;

    }

    public static function get_uuid_prefix () {
    
        return 'new-';
    
    }

    public static function generate_uuid ($data) {

        return static::get_uuid_prefix() . wp_generate_uuid4();

    }

    public static function is_uuid ($data) {

        return is_string($data) && (substr($data, 0, strlen(static::get_uuid_prefix())) == static::get_uuid_prefix());

    }

    public static function get_class_name ($id, $auto_resolve = null) {

        $class_name = static::class;

        if (is_null($auto_resolve)) $auto_resolve = static::get_auto_resolve();

        if ($auto_resolve && (static::$class_map[static::class] ?? 0)) {

            $specificity = static::get_specificity();
            $winners     = [];

            self::strict_enter('loop', $id);

            try {

                foreach (static::$class_map[static::class] as $sub_class => $class_specificity) {

                    if ($class_specificity < $specificity) continue;
                    if (!$sub_class::validate_id($id))     continue;

                    if ($class_specificity > $specificity) {

                        $winners     = [];
                        $specificity = $class_specificity;

                    }

                    $winners[] = $sub_class;

                }

            } finally {

                self::strict_leave('loop', $id);

            }

            if ($winners) {

                if (count($winners) > 1) $winners = self::prune_winners($winners);

                if (count($winners) > 1) Strict::fail(static::class, 'id ' . self::show_id($id) . ' validates as ' . implode(', ', $winners) . " at specificity {$specificity}, so the last registered of these wins arbitrarily.", "Narrow one validate_id() or add a distinguishing static; a subclass already wins over its ancestor, and a consumer model over the framework's.");

                $class_name = end($winners);

            }

        }

        return Call::get_class_name($class_name, [
            'id'   => $id,
        ]);

    }

    // At equal specificity a subclass refines its parent and a consumer model replaces the framework's default, so neither pair is a tie.
    protected static function prune_winners ($winners) {

        $winners = array_filter($winners, function ($winner) use ($winners) {

            foreach ($winners as $other) if (($other !== $winner) && is_subclass_of($other, $winner)) return false;

            return true;

        });

        $consumer = array_filter($winners, fn ($winner) => !str_starts_with($winner, __NAMESPACE__ . '\\'));

        return array_values($consumer ?: $winners);

    }

    protected static function resolve_data (&$data = [], $auto_resolve = null) {
    
        static::prepare_data($data);

        if (static::is_uuid($data)) {

            $id = $data;

        } else {

            $id = (is_null($data) && method_exists(static::class, 'get_global_id')) ?
                static::get_global_id() :
                static::extract_id($data);

        }

        return [$id, static::get_class_name($id, $auto_resolve)];
    
    }

    public static function create ($data = []) {

        static::prepare_data($data);

        $instance = new static($data);
        $instance->init($data);

        return $instance;

    }

    public static function get_instance ($data = null, $auto_resolve = null) {

        [$id, $class_name] = static::resolve_data($data, $auto_resolve);

        if (is_null($id))                 return null;
        if ($class_name != static::class) return $class_name::get_instance($data, false);

        if (isset(self::$instances[$class_name][$id])) return self::$instances[$class_name][$id];
        if (!static::validate_data($data))              return null;

        self::strict_enter('validate', $id);

        try {

            if (!static::validate_id($id)) return null;

        } finally {

            self::strict_leave('validate', $id);

        }

        $instance = new $class_name($id);
        $instance->init($data);
        return $instance;

    }

    public static function get_instances ($ids) {

        $instances = [];

        if ($ids) foreach ($ids as $id) if ($instance = static::get_instance($id)) $instances[] = $instance;

        return $instances;

    }

    public static function get_all_instances () {

        return self::$instances[static::class] ?? [];

    }

    //

    protected static $class_map = [];

    public static function get_class_map () {
    
        return static::$class_map;
    
    }

    public static function static_init () {

        if ((new \ReflectionClass(static::class))->isAbstract()) return; // Never a candidate: resolving to it would reach `new` on an abstract class.

        $specificity = static::get_specificity();
        $parent      = static::class;

        while ($parent = get_parent_class($parent)) {

            if (!property_exists($parent, 'class_map')) break;
            if (!isset(static::$class_map[$parent]))    static::$class_map[$parent] = [];
            
            static::$class_map[$parent][static::class] = $specificity;

        }
    
    }

    // Strict

    private static $resolving = [];

    // validate_id() calling get_instance() recurses without end; the loop and the validate step each guard their own re-entry for one class and id.
    protected static function strict_enter ($site, $id) {

        if (!Strict::enabled()) return;

        $key = "{$site}:" . static::class . '#' . self::show_id($id);

        if (isset(self::$resolving[$key])) Strict::fail(static::class, 'validate_id() re-entered ' . static::class . ' resolution for id ' . self::show_id($id) . ', which recurses without end.', 'Keep validate_id() to cheap checks (get_post_type(), has_term()); resolve models after validation.');

        self::$resolving[$key] = true;

    }

    protected static function strict_leave ($site, $id) {

        unset(self::$resolving["{$site}:" . static::class . '#' . self::show_id($id)]);

    }

    protected static function show_id ($id) {

        return is_scalar($id) ? (string) $id : gettype($id);

    }

    public static function strict_audit (string $class) {

        $reflection = new \ReflectionClass($class);
        $class      = $reflection->getName();

        static $legacy_reported = [];

        // A get_acf_id() override is dead since the field provider derives the id, so a guard it carried is silently gone.
        if (method_exists($class, 'get_acf_id') && !isset($legacy_reported[$culprit = (new \ReflectionMethod($class, 'get_acf_id'))->getDeclaringClass()->name])) {

            $legacy_reported[$culprit] = true;
            Strict::violation($culprit, 'defines get_acf_id(), which nothing calls: the field provider derives the id.', 'Rename it get_field_id() and return parent::get_field_id() where it built the id.');

        }

        if ($reflection->isAbstract()) return;

        $parent = get_parent_class($class);

        if (!$parent || !property_exists($parent, 'class_map'))    return;
        if (isset(static::$class_map[$parent][$class]))             return;

        static $reported = [];

        $culprit = (new \ReflectionMethod($class, 'static_init'))->getDeclaringClass()->name;

        if (isset($reported[$culprit])) return;
        $reported[$culprit] = true;

        $problem = ($culprit === $class)
            ? "static_init() overrides Model::static_init() without calling it, so the class never registers for resolution."
            : "inherits {$culprit}::static_init(), which never calls parent::static_init(), so the class never registers for resolution.";

        Strict::violation($class, $problem, "Call parent::static_init() first in {$culprit}::static_init().");

    }

    //

    protected $id;
    protected $is_new;

    public function __construct ($data = null) {

        if (is_scalar($data)) {

            $this->id     = $data;
            $this->is_new = false; //is_int($data) ? ($data < 0) : false;

            $this->hydrate_instance();

        } else {

            $this->id     = static::generate_uuid($data);
            $this->is_new = true;

            $this->build_instance($data);

        }

        $this->cache_instance();

    }

    protected function build_instance   ($data) {}
    protected function hydrate_instance ()      {}

    public function init ($data) {

        // ...

    }

    public function cache_instance () {
    
        if (!isset(self::$instances[static::class])) self::$instances[static::class] = [];
        self::$instances[static::class][$this->id] = $this;
        return $this;
    
    }

    //

    public function get_id () {

        return $this->id;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(self::$instances[static::get_class_name($this->id)]);

    }

    public function is_new () {
    
        return $this->is_new;
    
    }

    public function get_global_var () {

        return static::class;

    }

}