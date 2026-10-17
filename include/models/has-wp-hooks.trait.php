<?php

namespace Lattice;

use Closure;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;

trait Has_WP_Hooks {

    use Dependency_Injection;

    public function get_default_priority () {

        return 10;

    }

    public function get_wp_hook (string $hook_name) {

        global $wp_filter;
        return $wp_filter[$hook_name] ?? null;

    }

    protected function get_hook_namespace () {

        return static::class;

    }

    protected function sanitize_hook_name (string $hook_name) {

        return Hook::sanitize($hook_name);

    }

    public function build_hook_name (&$hook_name) {

        if (!is_null($hook_name)) $hook_name = Hook::name($hook_name);

    }

    public function add_hook (string|array $hook_name, mixed $callback, ?int $priority = null, string $type = 'filter', ?int $accepted_args = null) {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];
        if (is_null($priority)) $priority = $this->get_default_priority();

        if (!is_callable($callback)) return false;

        if (is_null($accepted_args)) {

            try {

                $reflection = match (true) {
                    $callback instanceof Closure  => new ReflectionFunction($callback),
                    is_object($callback)          => new ReflectionMethod($callback, '__invoke'),
                    is_array($callback)           => new ReflectionMethod($callback[0], $callback[1]),
                    str_contains($callback, '::') => new ReflectionMethod(...explode('::', $callback, 2)),
                    default                       => new ReflectionFunction($callback),
                };

                $accepted_args = $reflection->isVariadic() ? PHP_INT_MAX : $reflection->getNumberOfParameters(); // WordPress hands over every argument when the count exceeds what the hook passes

            } catch (ReflectionException) {

                $accepted_args = 1; // a name reflection cannot see (__call, __callStatic, the deprecated 'parent::m' form): callable, so it registers with WordPress's default count; pass $accepted_args for more

            }

        }

        $this->build_hook_name($hook_name);
        call_user_func('add_' . $type, $hook_name, $callback, $priority, $accepted_args);
        return true;

    }

    public function add_filter (string|array $hook_name, mixed $callback, ?int $priority = null, ?int $accepted_args = null) {

        return $this->add_hook($hook_name, $callback, $priority, 'filter', $accepted_args);

    }

    public function add_action (string|array $hook_name, mixed $callback, ?int $priority = null, ?int $accepted_args = null) {

        return $this->add_hook($hook_name, $callback, $priority, 'action', $accepted_args);

    }

    public function add_hooks (array $hooks, string $type = 'filter') {

        foreach ($hooks as $hook_name => $callback) if (is_array($callback) && !is_callable($callback)) {

            [$cb, $priority, $third, $fourth] = $callback + [null, null, null, null];
            if (is_int($third)) [$third, $fourth] = [null, $third]; // WordPress's own order: callback, priority, accepted args

            $this->add_hook($hook_name, $cb, $priority, $third ?? $type, $fourth);

        } else {

            $this->add_hook($hook_name, $callback, null, $type);

        }

    }

    public function add_filters (array $filters) {

        return $this->add_hooks($filters, 'filter');

    }

    public function add_actions (array $actions) {

        return $this->add_hooks($actions, 'action');

    }

    public function remove_hook (string|array $hook_name, mixed $callback, ?int $priority = null, string $type = 'filter') {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];
        if (is_null($priority)) $priority = $this->get_default_priority();

        $this->build_hook_name($hook_name);
        return call_user_func('remove_' . $type, $hook_name, $callback, $priority);

    }

    public function remove_filter (string|array $hook_name, mixed $callback, ?int $priority = null) {

        return $this->remove_hook($hook_name, $callback, $priority, 'filter');

    }

    public function remove_action (string|array $hook_name, mixed $callback, ?int $priority = null) {

        return $this->remove_hook($hook_name, $callback, $priority, 'action');

    }

    public function remove_all_hooks (string|array $hook_name, int|false|null $priority = false, string $type = 'filter') {

        if (is_null($priority)) $priority = $this->get_default_priority();
        $this->build_hook_name($hook_name);
        return call_user_func("remove_all_{$type}s", $hook_name, $priority);

    }

    public function remove_all_filters (string|array $hook_name, int|false|null $priority = false) {

        return $this->remove_all_hooks($hook_name, $priority, 'filter');

    }

    public function remove_all_actions (string|array $hook_name, int|false|null $priority = false) {

        return $this->remove_all_hooks($hook_name, $priority, 'action');

    }

    public function has_hook (string|array $hook_name, mixed $callback = false, string $type = 'filter') {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];
        $this->build_hook_name($hook_name);
        return call_user_func('has_' . $type, $hook_name, $callback);

    }

    public function has_filter (string|array $hook_name, mixed $callback = false) {

        return $this->has_hook($hook_name, $callback, 'filter');

    }

    public function has_action (string|array $hook_name, mixed $callback = false) {

        return $this->has_hook($hook_name, $callback, 'action');

    }

    public function do_hook (string|array $hook_name, string $type = 'filter', mixed ...$args) {

        $this->build_hook_name($hook_name);

        if (!$wp_hook = $this->get_wp_hook($hook_name)) return $args[0] ?? null;

        foreach ($wp_hook->callbacks as $priority) foreach ($priority as $callback) {

            $args = static::get_inject_args($callback['function'], $args);

        }

        $call = ($type == 'filter') ? 'apply_filters' : 'do_action';

        return static::inject($call, [$hook_name, ...$args]);

    }

    public function apply_filters (string|array $hook_name, mixed ...$args) {

        return $this->do_hook($hook_name, 'filter', ...$args);

    }

    public function filter_value (mixed $value, mixed ...$args) {

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null;
        $method    = $backtrace['function'] ?? 'unknown';

        return $this->apply_filters([
            $this->get_hook_namespace(),
            $method
        ], $value, $this, ...$args);

    }

    public function do_action (string|array $hook_name, mixed ...$args) {

        $this->do_hook($hook_name, 'action', ...$args);

    }

    public function apply_filters_ref_array (string|array $hook_name, array $args) {

        return $this->do_hook($hook_name, 'filter', ...$args);

    }

    public function do_action_ref_array (string|array $hook_name, array $args) {

        $this->do_hook($hook_name, 'action', ...$args);

    }

    public function doing_filter (string|array|null $hook_name = null) {

        $this->build_hook_name($hook_name);
        return doing_filter($hook_name);

    }

    public function doing_action (string|array|null $hook_name = null) {

        $this->build_hook_name($hook_name);
        return doing_action($hook_name);

    }

    public function did_filter (string|array $hook_name) {

        $this->build_hook_name($hook_name);
        return did_filter($hook_name);

    }

    public function did_action (string|array $hook_name) {

        $this->build_hook_name($hook_name);
        return did_action($hook_name);

    }

}