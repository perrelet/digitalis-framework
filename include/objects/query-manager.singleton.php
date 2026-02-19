<?php

namespace Digitalis;

use WP_Query;

class Query_Manager extends Singleton {

    protected $context;
    protected $profiles   = [];
    protected $sorted     = true;
    protected $mods_by_id = [];
    protected $next_id    = 1;

    public function __construct (array $profiles = []) {

        $this->context = $this->detect_context();

        foreach ($profiles as $profile) $this->register($profile);

        add_action('pre_get_posts', [$this, 'pre_get_posts'], 10);
        add_filter('posts_clauses', [$this, 'posts_clauses'], 10, 2);
        add_filter('posts_results', [$this, 'posts_results'], 10, 2);

    }

    public function get_context () {

        return $this->context;

    }

    public function register (Query_Profile $profile) {

        $this->profiles[] = $profile;
        $this->sorted     = false;

        return $this;

    }

    protected function sort () {

        if ($this->sorted) return;

        usort($this->profiles, function(Query_Profile $a, Query_Profile $b) {
            return $b->get_priority() <=> $a->get_priority();
        });

        $this->sorted = true;

    }

    public function pre_get_posts ($wp_query) {

        if (!$this->is_main($wp_query)) return;

        $wp_query->set('_profiles', null);
        $wp_query->set('_suppress', null);

        if (is_admin() && !wp_doing_ajax()) {

            global $pagenow;
            if ($pagenow !== 'edit.php') return;

            $this->stamp_context($wp_query, 'admin_main');

        } else {

            $this->stamp_context($wp_query, 'front_main');

        }

        $this->merge_stamp($wp_query, [
            'selection_mode' => 'implicit'
        ]);

        $id = $this->get_query_id($wp_query);

        [, $mods] = $this->apply($wp_query);

        if ($mods) $this->mods_by_id[$id] = $mods;

    }

    public function posts_clauses ($clauses, $wp_query) {

        // Permanent posts_clauses hook: applies only when query has digitalis_query_id AND mods have been registered for that id.

        if (!$id   = $this->get_query_id($wp_query)) return $clauses;
        if (!$mods = $this->mods_by_id[$id] ?? null) return $clauses;

        foreach ($mods as $mod) $clauses = $mod($clauses, $wp_query);

        return $clauses;

    }

    public function posts_results ($posts, $wp_query) {

        // Cleanup so we don't retain mods for the rest of the request. posts_results runs after SQL, before objects are returned.

        $id = $this->get_query_id($wp_query);
        if ($id && isset($this->mods_by_id[$id])) unset($this->mods_by_id[$id]);

        return $posts;

    }

    public function apply (WP_Query $wp_query) {

        if ($this->is_applied($wp_query)) return [new Query_Vars($wp_query->query_vars), []];
        $this->mark_applied($wp_query);
        $this->sort();

        $vars = new Query_Vars($wp_query->query_vars);
        $mods = [];

        foreach ($this->profiles as $profile) {

            if (!$profile->should_apply($wp_query)) continue;

            $profile->apply($vars, $wp_query, $mods);

        }

        foreach ($vars->to_array() as $key => $value) $wp_query->set($key, $value);

        return [$vars, $mods];

    }

    public function execute (WP_Query $wp_query, $stamp_merge = []) {

        $id       = $this->ensure_query_id($wp_query);
        $explicit = (bool) $wp_query->get('_profiles') || (bool) $wp_query->get('_suppress');

        $this->stamp_context($wp_query, 'programmatic', array_merge([
            'allow_profile_select' => true,
            'selection_mode'       => $explicit ? 'explicit' : 'implicit',
        ], $stamp_merge));

        [, $mods] = $this->apply($wp_query);

        if ($mods) $this->mods_by_id[$id] = $mods;

        return $wp_query->query($wp_query->query_vars);

    }

    //

    protected function stamp_context ($wp_query, $query_role, $merge = []) {

        $existing = $wp_query->get('digitalis');
        if (!is_array($existing)) $existing = [];

        $base = [
            'id'       => $this->ensure_query_id($wp_query),
            'role'     => $query_role,
            'context'  => $this->get_context(),
            'multiple' => $this->is_multiple($wp_query),
        ];

        $wp_query->set('digitalis', array_merge($existing, $merge, $base));

    }

    protected function get_stamp ($wp_query) {

        return (array) $wp_query->get('digitalis');

    }

    protected function set_stamp ($wp_query, $stamp) {

        $wp_query->set('digitalis', $stamp);

    }

    protected function merge_stamp ($wp_query, $merge) {

        $this->set_stamp($wp_query, array_merge($this->get_stamp($wp_query), $merge));

    }

    protected function get_query_id ($wp_query) {

        return $this->get_stamp($wp_query)['id'] ?? null;

    }

    protected function ensure_query_id ($wp_query) {

        $stamp = $this->get_stamp($wp_query);

        if (!empty($stamp['id'])) return (string) $stamp['id'];

        $id = (string) $this->next_id++;
        $this->merge_stamp($wp_query, ['id' => $id]);

        return $id;

    }

    protected function is_applied ($wp_query) {

        $stamp = $this->get_stamp($wp_query);
        return !empty($stamp['applied']);

    }

    protected function mark_applied ($wp_query) {

        $this->merge_stamp($wp_query, ['applied' => true]);

    }

    protected function detect_context () {

        if (defined('WP_CLI') && WP_CLI)                         return 'cli';
        if (function_exists('wp_doing_cron') && wp_doing_cron()) return 'cron';
        if (defined('REST_REQUEST') && REST_REQUEST)             return 'rest';
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return 'ajax';

        return is_admin() ? 'admin' : 'front';

    }

    protected function is_multiple ($wp_query) {

        if ($this->is_digitalis_ajax($wp_query)) return true;
    
        return $wp_query->is_archive() || $wp_query->is_search() || $wp_query->is_posts_page;
    
    }

    protected function is_main ($wp_query) {

        return $wp_query->is_main_query();
    
    }

    protected function is_digitalis_ajax ($wp_query) {

        return (bool) $wp_query->get(Post_Type::AJAX_Flag);
    
    }

}