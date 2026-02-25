<?php

namespace Digitalis;

class Query_Profile extends Factory {

    protected $mode = 'selectable'; // selectable (default off, only runs when selected in _profiles) | ambient (default on, skipped when _profiles is provided) | baseline (almost always on, only skipped if provided in _suppress)
    protected $priority = 10;
    protected $post_type;
    protected $post_status;
    protected $role;
    protected $context;

    public function __construct () {

        $this->post_type   = (array) $this->post_type;
        $this->post_status = (array) $this->post_status;
        $this->role        = (array) $this->role;
        $this->context     = (array) $this->context;

        Query_Manager::get_instance()->register($this);

    }

    public function get_priority () {
    
        return $this->priority;
    
    }

    public function should_apply ($wp_query) {
    
        if (!$this->matches_profile($wp_query)) return false;
        if ($this->is_suppressed($wp_query))    return false;
        if (!$this->check_mode($wp_query))      return false;

        return $this->condition($wp_query);
    
    }

    public function condition ($wp_query) {

        return true;

    }

    public function apply ($query_vars, $wp_query, &$mods) {

        /* $query_vars->merge([
            'meta_query' => [
                'starred_clause' => [
                    'key'     => 'starred',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
            'orderby' => [
                'starred_clause' => 'DESC',
                'date'           => $wp_query->get('order') ?: 'DESC',
            ],
        ], true); */

        /* $mods[] = function($clauses, $wp_query) {

            global $wpdb;

            $clauses['join'] .= "
                LEFT JOIN {$wpdb->postmeta} AS pm_priority
                ON pm_priority.post_id = {$wpdb->posts}.ID
                AND pm_priority.meta_key = 'priority'
            ";

            $clauses['orderby'] = "CAST(pm_priority.meta_value AS UNSIGNED) DESC, " . $clauses['orderby'];

            return $clauses;

        }; */

    }

    protected function check_mode ($wp_query) {
    
        $selection_mode = $this->get_stamp($wp_query)['selection_mode'] ?? 'implicit';

        switch ($this->mode) {

            case 'baseline':
                return true;

            case 'ambient':
                return ($selection_mode === 'implicit');

            case 'selectable':
                return $this->is_selected($wp_query);

        }

        return false;
    
    }

    protected function matches_profile ($wp_query) {

        if ($this->post_type) {

            $found = false;
            foreach ($this->post_type as $type) if ($this->has_post_type($wp_query, $type)) {
                $found = true;
                break;
            }
            if (!$found) return false;

        }

        if ($this->post_status) {

            $post_status = (array) $wp_query->get('post_status');
            if ($post_status && !array_intersect($post_status, $this->post_status)) return false;

        }

        if ($this->role    && !in_array($this->get_role($wp_query),    $this->role,    true)) return false;
        if ($this->context && !in_array($this->get_context($wp_query), $this->context, true)) return false;

        return true;
    
    }

    protected function is_selected ($wp_query) {

        if (!$this->can_be_selected($wp_query)) return false;

        return $this->in_profiles($wp_query);

    }

    protected function is_suppressed ($wp_query) {

        if (!$this->can_be_selected($wp_query)) return false;

        return in_array(static::class, (array) $wp_query->get('_suppress'));

    }


    protected function can_be_selected ($wp_query) {

        return (bool) ($this->get_stamp($wp_query)['allow_profile_select'] ?? false);

    }

    protected function in_profiles ($wp_query) {

        return in_array(static::class, (array) $wp_query->get('_profiles'));

    }

    protected function get_stamp ($wp_query) {

        return (array) $wp_query->get('digitalis');

    }

    protected function get_role ($wp_query) {

        return $this->get_stamp($wp_query)['role'] ?? null;

    }

    protected function get_context ($wp_query) {

        return $this->get_stamp($wp_query)['context'] ?? null;

    }

    protected function is_multiple ($wp_query) {

        return (bool) $this->get_stamp($wp_query)['multiple'] ?? null;

    }

    protected function has_post_type ($wp_query, $post_type) {

        return Query_Vars::compare_post_type($wp_query, $post_type);

    }

}