<?php

namespace Digitalis;

// WordPress accounts as a resource (SCOPED_CREDENTIALS.md section 18). A site registers its own subclass, naming any ACF or meta fields it opens and the conditions it declares. Reached only by rules naming it (A0).
abstract class User_Resource extends Model_Resource {

    protected static $model_class = Account::class;

    // Of more than this many listed roles, a list is narrowed row by row rather than in its query.
    const QUERY_ROLES = 5;

    public function get_post_type () : string {

        return '';

    }

    public function is_named_only () : bool {

        return true;

    }

    public function get_model_fields () : array {

        $column = fn (string $column) => fn ($user) => (string) $user->get_wp_user()->$column;
        $meta   = fn (string $key) => fn ($user) => (string) get_user_meta($user->get_id(), $key, true);

        return [
            'login'        => ['type' => 'text',     'label' => 'Login',        'get' => $column('user_login')],
            'email'        => ['type' => 'email',    'label' => 'Email',        'get' => $column('user_email')],
            'display_name' => ['type' => 'text',     'label' => 'Display name', 'get' => $column('display_name')],
            'first_name'   => ['type' => 'text',     'label' => 'First name',   'get' => $meta('first_name')],
            'last_name'    => ['type' => 'text',     'label' => 'Last name',    'get' => $meta('last_name')],
            'nickname'     => ['type' => 'text',     'label' => 'Nickname',     'get' => $meta('nickname')],
            'url'          => ['type' => 'url',      'label' => 'Website',      'get' => $column('user_url')],
            'description'  => ['type' => 'textarea', 'label' => 'Biography',    'get' => $meta('description')],
            'roles'        => ['type' => 'roles',    'label' => 'Roles',        'get' => fn ($user) => array_values((array) $user->get_wp_user()->roles)],
        ];

    }

    public function get_locked_fields ($model) : array {

        return ['login' => 'WordPress never changes a login.'];

    }

    // No ACF group is a field unless the site names it (18.4).
    protected function get_type_groups () : array {

        return [];

    }

    public function get_fields_for ($model, array $values = []) : array {

        return $this->get_fields();

    }

    public function get_summary_fields () : array {

        return ['title' => 'display_name', 'status' => 'roles'];

    }

    // The roles a write gives are a fact before it as after (A1), so two rules each covering one end cannot carry an account between them.
    public function get_before_facts ($model, array $values) : array {

        $facts = $this->get_facts($model);

        return ['gives' => $this->get_facts($model, $values)['gives']] + $facts;

    }

    public function get_search_fields () : array {

        return ['login', 'email', 'display_name'];

    }

    // Records

    public function find ($id) : ?object {

        $id = (int) $id;

        return ($id && get_userdata($id)) ? (static::$model_class)::get_instance($id) : null;

    }

    public function find_by_set_key (string $key) : ?object {

        $ids = get_users(['meta_key' => Resources::SET_KEY, 'meta_value' => $key, 'fields' => 'ID', 'number' => 1]);

        return $ids ? $this->find((int) $ids[0]) : null;

    }

    public function clear_record_cache ($id) : void {

        if (is_numeric($id)) clean_user_cache((int) $id);

    }

    // A bare number is a post's or an order's: an account is never found by one, since user ids and post ids overlap.
    public function owns_record ($id) : bool {

        return false;

    }

    public function forget_set_key ($model) : void {

        delete_user_meta($model->get_id(), Resources::SET_KEY);

    }

    public function get_acf_screen () : array {

        return ['user_form' => 'edit'];

    }

    public function get_required_status () : ?string {

        return null;

    }

    // search, page, per_page. Under a scoped credential the query holds only accounts the credential's rules could cover, so pages and counts stay true; each row is still asked on its own.
    public function query (array $args) : array {

        $query = array_filter([
            'search'         => ($search = trim((string) ($args['search'] ?? ''))) ? "*{$search}*" : '',
            'search_columns' => $search ? ['user_login', 'user_email', 'display_name'] : null,
            'number'         => $args['per_page'] ?? null,
            'paged'          => $args['page'] ?? null,
            'orderby'        => 'registered',
            'order'          => 'DESC',
        ]);

        if (class_exists(Credentials::class) && !is_null($roles = Credentials::inst()->get_roles_reached($this->get_name(), 'list')) && ($covered = $this->covered_query($roles))) $query['meta_query'] = $covered;

        return (static::$model_class)::query(apply_filters('lattice.resources.query', $query, $this));

    }

    // Exact stored roles: every ordering of every set of the listed roles, and under none an empty or missing row. WordPress ANDs role arguments with a meta query, so none are given.
    protected function covered_query (array $roles) : array {

        global $wpdb;

        $key   = $wpdb->get_blog_prefix() . 'capabilities';
        $none  = in_array(Credential_Policy::NO_ROLE, $roles, true);
        $named = array_values(array_diff($roles, [Credential_Policy::NO_ROLE]));

        if (count($named) > static::QUERY_ROLES) return [];

        $values = array_map(fn ($sequence) => serialize(array_fill_keys($sequence, true)), $this->sequences($named));

        if ($none) $values[] = serialize([]);

        $any = ['relation' => 'OR', ['key' => $key, 'value' => $values ?: ['-'], 'compare' => 'IN']];

        if ($none) $any[] = ['key' => $key, 'compare' => 'NOT EXISTS'];

        return [$any];

    }

    protected function sequences (array $roles) : array {

        $sequences = [];

        foreach ($roles as $i => $role) {

            $sequences[] = [$role];

            foreach ($this->sequences(array_values(array_diff_key($roles, [$i => 0]))) as $rest) $sequences[] = [$role, ...$rest];

        }

        return $sequences;

    }

    public function get_status ($model) : string {

        return '';

    }

    public function summarise ($model) : array {

        return ['title' => (string) $model->get_wp_user()->display_name, 'status' => implode(', ', (array) $model->get_wp_user()->roles), 'modified' => null];

    }

    public function get_edit_url ($model) : string {

        return get_edit_user_link($model->get_id());

    }

    public function get_states ($model) : array {

        return ['roles' => array_values((array) $model->get_wp_user()->roles)];

    }

    // Conditions a site declares on its accounts (on somm: teacher, pro, referrer), each fn ($model, array $values) : bool.
    public function get_conditions () : array {

        return [];

    }

    // What a credential's rule asks of an account (A1): its roles as they are, or as $values would leave them, the roles a write gives, whether it holds a stored capability beyond its roles, whether it is the asking account's own, and whether this credential made it. A record being made holds nothing yet: only what it is given.
    public function get_facts ($model, array $values = [], ?int $user_id = null) : array {

        $conditions = $this->get_conditions();
        $held       = $model ? array_values((array) $model->get_wp_user()->roles) : [];
        $after      = array_key_exists('roles', $values) ? array_values((array) $values['roles']) : $held;

        $facts = [
            'id'                 => $model?->get_id(),
            'own'                => false,
            'self'               => $model && ($model->get_id() === ($user_id ?? get_current_user_id())),
            'made_by_credential' => $model && $this->made_by_current($model),
            'conditions'         => fn (string $name) => isset($conditions[$name]) && (bool) $conditions[$name]($model, $values),
            'gives'              => array_values(array_diff($after, $held)),
        ];

        return $model ? $facts + ['roles' => $after, 'extra_caps' => $this->has_extra_caps($model)] : $facts;

    }

    // A stored capability that is not one of the account's roles: a stored false narrows, so it does not count.
    public function has_extra_caps ($model) : bool {

        $roles = array_keys(wp_roles()->roles);

        return (bool) array_filter((array) $model->get_wp_user()->caps, fn ($granted, $cap) => $granted && !in_array($cap, $roles, true), ARRAY_FILTER_USE_BOTH);

    }

    protected function made_by_current ($model) : bool {

        $current = class_exists(Credentials::class) ? Credentials::inst()->get_current() : null;

        return $current && (get_user_meta($model->get_id(), Credentials::MADE_BY, true) === $current['uuid']);

    }

    // Policy

    public function get_capability (string $operation, $model = null) : string {

        return match ($operation) {
            'update'  => 'edit_user',
            'create'  => 'create_users',
            'publish' => 'do_not_allow',
            default   => 'list_users',
        };

    }

    public function can (string $operation, $model = null) : bool {

        $meta = $model && ($operation === 'update');

        return current_user_can($this->get_capability($operation, $model), ...($meta ? [$model->get_id()] : []));

    }

}
