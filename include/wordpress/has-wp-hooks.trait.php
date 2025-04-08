<?php

namespace Digitalis;

use Closure;
use ReflectionMethod;
use ReflectionFunction;

trait Has_WP_Hooks {

    use Dependency_Injection;

    public function get_default_priority () {

        return 10;

    }

    public function get_wp_hook ($hook_name) {

        global $wp_filter;
        return $wp_filter[$hook_name] ?? null;

    }

    public function add_hook ($hook_name, $callback, $priority = null, $type = 'filter') {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];
        if (is_null($priority)) $priority = $this->get_default_priority();

        if (is_array($callback) && (count($callback) == 2)) {

            $reflection = new ReflectionMethod($callback[0], $callback[1]);
            $params     = $reflection->getNumberOfParameters();

        } else if (is_callable($callback) || ($callback instanceof Closure)) {

            $reflection = new ReflectionFunction($callback);
            $params     = $reflection->getNumberOfParameters();
    
        } else {

            return false;

        }

        call_user_func('add_' . $type, $hook_name, $callback, $priority, $params);
        return true;

    }

    public function add_filter ($hook_name, $callback, $priority = null) {

        return $this->add_hook($hook_name, $callback, $priority, 'filter');

    }

    public function add_action ($hook_name, $callback, $priority = null) {

        return $this->add_hook($hook_name, $callback, $priority, 'action');

    }

    public function add_hooks ($hooks, $type = 'filter') {

        foreach ($hooks as $hook_name => $callback) if (is_array($callback)) {

            $this->add_hook($hook_name, $callback[0] ?? null, $callback[1] ?? null, $callback[2] ?? $type);

        } else {

            $this->add_hook($hook_name, $callback, null, $type);

        }

    }

    public function add_filters ($filters) {

        return $this->add_hooks($filters, 'filter');

    }

    public function add_actions ($actions) {

        return $this->add_hooks($filters, 'action');

    }

    public function remove_hook ($hook_name, $callback, $priority = null, $type = 'filter') {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];
        if (is_null($priority)) $priority = $this->get_default_priority();

        return call_user_func('remove_' . $type, $hook_name, $callback, $priority);

    }

    public function remove_filter ($hook_name, $callback, $priority = null) {

        return $this->remove_hook($hook_name, $callback, $priority, 'filter');

    }

    public function remove_action ($hook_name, $callback, $priority = null) {

        return $this->remove_hook($hook_name, $callback, $priority, 'action');

    }

    public function remove_all_hooks ($hook_name, $priority = false, $type = 'filter') {

        if (is_null($priority)) $priority = $this->get_default_priority();
        return call_user_func("remove_all_{$type}s", $hook_name, $priority);

    }

    public function remove_all_filters ($hook_name, $priority = false) {

        return $this->remove_all_hooks($hook_name, $priority, 'filter');

    }

    public function remove_all_actions ($hook_name, $priority = false) {

        return $this->remove_all_hooks($hook_name, $priority, 'action');

    }

    public function has_hook ($hook_name, $callback = false, $type = 'filter') {

        if (is_string($callback) && method_exists($this, $callback)) $callback = [$this, $callback];

        return call_user_func('has_' . $type, $hook_name, $callback);

    }

    public function has_filter ($hook_name, $callback = false) {

        return $this->has_hook($hook_name, $callback, 'filter');

    }

    public function has_action ($hook_name, $callback = false) {

        return $this->has_hook($hook_name, $callback, 'action');

    }

    public function do_hook ($hook_name, $type = 'filter', ...$args) {

        if (!$wp_hook = $this->get_wp_hook($hook_name)) return;

        foreach ($wp_hook->callbacks as $priority) foreach ($priority as $callback) {

            $args = static::get_inject_args($callback['function'], $args);

        }

        $call = ($type == 'filter') ? 'apply_filters' : 'do_action';

        return static::inject($call, [$hook_name, ...$args]);

    }

    public function apply_filters ($hook_name, ...$args) {

        return $this->do_hook($hook_name, 'filter', ...$args);

    }

    public function do_action ($hook_name, ...$args) {

        $this->do_hook($hook_name, 'action', ...$args);

    }

    public function apply_filters_ref_array ($hook_name, $args) {

        return $this->do_hook($hook_name, 'filter', ...$args);

    }

    public function do_action_ref_array ($hook_name, $args) {

        $this->do_hook($hook_name, 'action', ...$args);

    }

    public function doing_filter ($hook_name = null) {

        return doing_filter($hook_name);

    }

    public function doing_action ($hook_name = null) {

        return doing_action($hook_name);

    }

    public function did_filter ($hook_name) {

        return did_filter($hook_name);

    }

    public function did_action ($hook_name) {

        return did_action($hook_name);

    }

}