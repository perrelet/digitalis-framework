<?php

declare(strict_types=1);

namespace Lattice;

use Exception;

/** @phpstan-consistent-constructor */
class Model extends Factory {

    public static function get_auto_instantiation () {
        
        return false;
    
    }

    const INSTANCE_KIND = 'entity';

    public static function prepare_data (mixed &$data) {}

    public static function extract_id (mixed $data = null) {

        if (is_numeric($data))     return (int) $data;
        if ($data instanceof self) return $data->get_id();
        if (!is_scalar($data))     return null;

        return $data;

    }

    public static function validate_data (mixed $data) {

        return true;

    }

    public static function validate_id (mixed $id) {

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

    public static function generate_uuid (mixed $data) {

        return static::get_uuid_prefix() . wp_generate_uuid4();

    }

    public static function is_uuid (mixed $data) {

        return is_string($data) && (substr($data, 0, strlen(static::get_uuid_prefix())) == static::get_uuid_prefix());

    }

    public static function get_class_name (mixed $id, ?bool $auto_resolve = null) {

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
    protected static function prune_winners (array $winners) {

        $winners = array_filter($winners, function ($winner) use ($winners) {

            foreach ($winners as $other) if (($other !== $winner) && is_subclass_of($other, $winner)) return false;

            return true;

        });

        $consumer = array_filter($winners, fn ($winner) => !str_starts_with($winner, __NAMESPACE__ . '\\'));

        return array_values($consumer ?: $winners);

    }

    protected static function resolve_data (mixed &$data = [], ?bool $auto_resolve = null) {
    
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

    // A stored model is found by its id; create() builds a new one and never replaces what the map holds.
    public static function create (mixed $data = []) {

        if (is_scalar($data)) return static::refuse_declare('creates from an id', 'find a stored model with get_instance($id); create() builds a new, unsaved one');

        static::prepare_data($data);

        $instance = new static($data);
        $instance->init($data);

        return $instance;

    }

    // A model is found by its id.
    public static function declare (string|array $name = [], array $config = []) {

        return static::refuse_declare('declares ' . (is_string($name) ? "'{$name}'" : 'a config'), 'a model is found by its id: use get_instance()');

    }

    public static function get_instance (mixed $data = null, ?bool $auto_resolve = null) {

        [$id, $class_name] = static::resolve_data($data, $auto_resolve);

        if (is_null($id))                 return null;
        if ($class_name != static::class) return $class_name::get_instance($data, false);

        if (($held = Registry::get(self::INSTANCE_KIND, $class_name, $id)) && ($held->get_record()->site === get_current_blog_id())) return $held->is_deleted() ? null : $held;
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

        if (is_iterable($ids)) foreach ($ids as $id) if ($instance = static::get_instance($id)) $instances[] = $instance;

        return $instances;

    }

    public static function get_all_instances () {

        return Registry::get_group(self::INSTANCE_KIND, static::class);

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
    protected static function strict_enter (string $site, mixed $id) {

        if (!Strict::enabled()) return;

        $key = "{$site}:" . static::class . '#' . self::show_id($id);

        if (isset(self::$resolving[$key])) Strict::fail(static::class, 'validate_id() re-entered ' . static::class . ' resolution for id ' . self::show_id($id) . ', which recurses without end.', 'Keep validate_id() to cheap checks (get_post_type(), has_term()); resolve models after validation.');

        self::$resolving[$key] = true;

    }

    protected static function strict_leave (string $site, mixed $id) {

        unset(self::$resolving["{$site}:" . static::class . '#' . self::show_id($id)]);

    }

    protected static function show_id (mixed $id) {

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

    protected ?Model_Record $model_record = null;

    // The storage a record is kept under: views of one storage and id share it. Unshared by default.
    protected static function get_record_storage () : string {

        return static::class;

    }

    // The properties every view of a record shares, bound by reference.
    protected static function get_record_properties () : array {

        return ['id', 'is_new'];

    }

    public function get_record () : Model_Record {

        return $this->model_record ??= new Model_Record(static::get_record_storage(), get_current_blog_id());

    }

    protected function remember (string $key, callable $callback) : mixed {

        $memo = &$this->get_record()->memo[static::class];

        if (!is_array($memo) || !array_key_exists($key, $memo)) $memo[$key] = $callback();

        return $memo[$key];

    }

    public function is_deleted () : bool {

        return (bool) ($this->get_record()->state['deleted'] ?? false);

    }

    // Drops the request's hold on every record: one still held elsewhere keeps its identity and is read again, the rest are freed. Long runs call it per unit of work.
    public static function release () : void {

        $records = [];
        $views   = [];

        foreach (Registry::get_kind('record') as $group => $held) foreach ($held as $id => $record) $records[] = [$group, $id, \WeakReference::create($record), $record->is_dirty(), implode(', ', array_keys($record->views))];
        foreach (Registry::get_kind(self::INSTANCE_KIND) as $class => $held) foreach ($held as $id => $view) $views[] = [$class, $id, \WeakReference::create($view)];

        unset($held, $record, $view);

        Registry::forget_kind('record');
        Registry::forget_kind(self::INSTANCE_KIND);

        gc_collect_cycles();

        foreach ($views as [$class, $id, $weak]) if ($view = $weak->get()) Registry::set(self::INSTANCE_KIND, $class, $id, $view);

        foreach ($records as [$group, $id, $weak, $dirty, $classes]) {

            if ($record = $weak->get()) {

                Registry::set('record', $group, $id, $record);

                if (!$dirty) $record->reload_guarded();

            } elseif ($dirty) {

                _doing_it_wrong(esc_html($classes), esc_html("An unsaved model ({$group} {$id}) was released with its edits; save it before the unit of work ends."), '0.14.0');

            }

        }

        if (function_exists('wp_cache_supports') && wp_cache_supports('flush_runtime')) wp_cache_flush_runtime();

    }

    protected function touch_record () : void {

        $this->get_record()->touch();

    }

    protected function bind_record (Model_Record $record) : void {

        foreach (static::get_record_properties() as $property) {

            if (!property_exists($this, $property)) continue;

            if (!array_key_exists($property, $record->state)) $record->state[$property] = $this->$property;

            $this->$property = &$record->state[$property];

        }

        $record->views[static::class] = $this;
        $this->model_record           = $record;

    }

    // A clone leaves the record: its properties stop being references, so a clone's reload never reaches the original.
    public function __clone () {

        foreach (static::get_record_properties() as $property) {

            if (!property_exists($this, $property)) continue;

            $value = $this->$property;
            unset($this->$property);
            $this->$property = $value;

        }

        // A clone stays out of the map: its saves are followed into the mapped record, never registered over it.
        $this->model_record       = new Model_Record(static::get_record_storage(), get_current_blog_id());
        $this->model_record->kept = false;

        $this->bind_record($this->model_record);
        $this->read_clone();

    }

    // A saved clone is read from storage without the original's pending edits; an unsaved one copies its objects.
    protected function read_clone () : void {

        $defaults = (new \ReflectionClass($this))->getDefaultProperties();

        foreach (static::get_record_properties() as $property) {

            if (!property_exists($this, $property) || in_array($property, ['id', 'is_new'], true)) continue;

            if ($this->is_new)                                $this->$property = is_object($this->$property) ? clone $this->$property : $this->$property;
            elseif (array_key_exists($property, $defaults))   $this->$property = $defaults[$property];

        }

        if (!$this->is_new) $this->hydrate_instance();

    }

    // The WordPress rows a record of this class stands on; a save reloads every other record over the rows it wrote.
    protected static function get_record_rows (int|string $id) : array {

        return [];

    }

    protected function follow_save () : void {

        Model_Record::follow_rows(static::get_record_rows($this->id), $this->get_record());

    }

    public function __construct (mixed $data = null) {

        if (is_scalar($data) && ($record = Model_Record::find(static::get_record_storage(), $data))) {

            $this->bind_record($record);

        } elseif (is_scalar($data)) {

            $this->id     = $data;
            $this->is_new = false; //is_int($data) ? ($data < 0) : false;

            $this->hydrate_instance();

            $record       = $this->get_record();
            $record->kept = !Model_Record::is_unbound(static::get_record_storage());

            $this->bind_record($record);

        } else {

            $this->id     = static::generate_uuid($data);
            $this->is_new = true;

            $this->build_instance($data);
            $this->bind_record($this->get_record());

        }

        $this->cache_instance();

    }

    protected function build_instance   (mixed $data) {}
    protected function hydrate_instance ()      {}

    public function init (mixed $data) {

        // ...

    }

    public function cache_instance () {
    
        if ($this->is_new || !$this->get_record()->kept) return $this;

        Registry::set(self::INSTANCE_KIND, static::class, $this->id, $this);

        $this->get_record()->rows = static::get_record_rows($this->id);
        $this->get_record()->keep($this->id);
        return $this;
    
    }

    //

    public function get_id () {

        return $this->id;

    }

    public function is_first_instance () {

        return $this->id == array_key_first(Registry::get_group(self::INSTANCE_KIND, static::get_class_name($this->id)));

    }

    public function is_new () {
    
        return $this->is_new;
    
    }

    public function get_global_var () {

        return static::class;

    }

}