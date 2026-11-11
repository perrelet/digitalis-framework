<?php

namespace Digitalis;

// A site subclasses and registers it, naming its own fields and conditions (SCOPED_CREDENTIALS.md 18).
abstract class User_Resource extends Model_Resource {

    protected static $model_class = Account::class;

    // Not delete: its reassign would have to be judged for the author.
    const PROPOSABLE = ['send_password_reset' => []];

    // Beyond this, rows are narrowed one by one.
    const QUERY_ROLES = 5;

    protected array $outside = [];

    // Outside changes raise the version and are journaled without values (A13).
    public function init () {

        parent::init();

        $columns = ['user_email' => 'email', 'display_name' => 'display_name', 'user_url' => 'url'];

        add_action('profile_update', function ($user_id, $old) use ($columns) { $new = get_userdata((int) $user_id); foreach ($columns as $column => $field) if ($old && $new && ((string) $old->$column !== (string) $new->$column)) $this->note_outside_change((int) $user_id, $field); }, 10, 2);

        foreach (['set_user_role', 'add_user_role', 'remove_user_role'] as $hook) add_action($hook, fn ($user_id) => $this->note_outside_change((int) $user_id, 'roles'));

        foreach (['added_user_meta', 'updated_user_meta', 'deleted_user_meta'] as $hook) add_action($hook, function ($meta_id, $user_id, $key) { if (in_array($key, Account::META, true)) foreach ((array) $user_id as $id) $this->note_outside_change((int) $id, $key); }, 10, 3);

    }

    protected function note_outside_change (int $user_id, string $field) : void {

        if (!$user_id || Resources::inst()->get_account_write($user_id) || !($model = $this->find($user_id))) return;

        if (!isset($this->outside[$user_id])) {

            $model->raise_version();

            if (!$this->outside) add_action('shutdown', fn () => $this->journal_outside_changes(), 0);

        }

        // Without its step, so undo_set never counts it as the set's own.
        $this->outside[$user_id]['_marks'] ??= array_diff_key(Resources::inst()->get_held_marks([]), ['set_step' => 0]);
        $this->outside[$user_id][$field] = true;

    }

    protected function journal_outside_changes () : void {

        foreach ($this->outside as $user_id => $fields) {

            $marks = $fields['_marks'];

            unset($fields['_marks']);

            Journal::inst()->write([
                'via'       => is_admin() ? 'admin' : 'other',
                'resource'  => $this->get_name(),
                'record_id' => $user_id,
                'action'    => 'changed',
                'why'       => '',
                'before'    => null,
                'after'     => ['_fields' => array_keys($fields)],
                'set_key'   => $marks['set_key'] ?? null,
            ] + $marks);

        }

        $this->outside = [];

    }

    public function get_post_type () : string {

        return '';

    }

    public function is_named_only () : bool {

        return true;

    }

    public function get_model_fields () : array {

        $column = fn (string $column) => ['get' => fn ($user) => $user->get_column($column), 'set' => fn ($user, $value) => $user->stage($column, (string) $value)];
        $meta   = fn (string $key) => ['get' => fn ($user) => $user->get_meta_value($key), 'set' => fn ($user, $value) => $user->stage($key, (string) $value)];

        return [
            'login'        => ['type' => 'text',     'label' => 'Login',        'validate' => fn ($login, $path) => $this->check_login($login, $path)] + $column('user_login'),
            'email'        => ['type' => 'email',    'label' => 'Email',        'validate' => fn ($email, $path) => is_email((string) $email) ? [] : ["{$path} is an email address."]] + $column('user_email'),
            'display_name' => ['type' => 'text',     'label' => 'Display name'] + $column('display_name'),
            'first_name'   => ['type' => 'text',     'label' => 'First name'] + $meta('first_name'),
            'last_name'    => ['type' => 'text',     'label' => 'Last name'] + $meta('last_name'),
            'nickname'     => ['type' => 'text',     'label' => 'Nickname'] + $meta('nickname'),
            'url'          => ['type' => 'url',      'label' => 'Website',      'validate' => fn ($url, $path) => (($url === '') || (esc_url_raw((string) $url) === $url)) ? [] : ["{$path} is a web address."]] + $column('user_url'),
            'description'  => ['type' => 'textarea', 'label' => 'Biography'] + $meta('description'),
            'roles'        => ['type' => 'roles',    'label' => 'Roles',        'validate' => fn ($roles, $path) => $this->check_roles($roles, $path),
                               'get' => fn ($user) => $user->get_account_roles(), 'set' => fn ($user, $roles) => $user->stage('roles', array_values(array_unique((array) $roles)))],
            // Create only (E12).
            'notify'       => ['type' => 'true_false', 'label' => 'Send the new-user mail', 'get' => fn ($user) => (bool) $user->get_staged('notify', false), 'set' => fn ($user, $notify) => $user->stage('notify', (bool) $notify)],
        ];

    }

    protected function check_login ($login, string $path) : array {

        if (!is_string($login) || !validate_username($login)) return ["{$path} is a valid login."];

        return username_exists($login) ? ["{$path} is taken."] : [];

    }

    protected function check_roles ($roles, string $path) : array {

        if (!is_array($roles) || !array_is_list($roles)) return ["{$path} is a list of roles."];

        return ($unknown = array_diff($roles, array_keys(wp_roles()->roles))) ? ["{$path} has no role " . implode(', ', $unknown) . '.'] : [];

    }

    public function get_locked_fields ($model) : array {

        return $model?->get_id() ? ['login' => 'WordPress never changes a login.', 'notify' => 'The new-user mail is sent when an account is made.'] : [];

    }

    public function make_model (array $values) : object {

        $account = (static::$model_class)::create([]);

        foreach (['login' => 'user_login', 'email' => 'user_email'] as $field => $key) if (isset($values[$field])) $account->stage($key, (string) $values[$field]);

        $account->stage('roles', array_values(array_unique((array) ($values['roles'] ?? []))));

        if (!empty($values['notify'])) $account->stage('notify', true);

        return $account;

    }

    // Marks go in with the insert, so user_register's listeners see them.
    public function insert_model ($model, array $meta = []) : object {

        $roles   = $model->get_account_roles();
        $current = class_exists(Credentials::class) ? Credentials::inst()->get_current() : null;

        if ($current) $meta[Credentials::MADE_BY] = $current['uuid'];

        $id = wp_insert_user([
            'user_login' => $model->get_column('user_login'),
            'user_email' => $model->get_column('user_email'),
            'user_pass'  => wp_generate_password(32, true, true),
            'role'       => $roles[0] ?? '',
        ] + ($meta ? ['meta_input' => $meta] : []));

        if (is_wp_error($id)) return $id;

        if ($model->get_staged('notify')) wp_new_user_notification($id, null, 'user');

        return (static::$model_class)::get_instance($id);

    }

    // No ACF group unless the site names it (18.4).
    protected function get_type_groups () : array {

        return [];

    }

    public function get_fields_for ($model, array $values = []) : array {

        return $this->get_fields();

    }

    public function get_summary_fields () : array {

        return ['title' => 'display_name', 'status' => 'roles'];

    }

    // Given roles count before as after (A1).
    public function get_before_facts ($model, array $values, ?int $user_id = null) : array {

        $facts = $this->get_facts($model, [], $user_id);

        return ['gives' => $this->get_facts($model, $values, $user_id)['gives']] + $facts;

    }

    public function find_made ($model) : ?object {

        return ($id = username_exists($model->get_column('user_login'))) ? $this->find((int) $id) : null;

    }

    public function get_actions () : array {

        return [
            // No field changes: declared, so a roles rule can judge them (section 4).
            'send_password_reset' => ['label' => 'Send a password reset', 'capability' => 'edit_user', 'changes_record' => false, 'sets' => []],
            'delete'              => ['label' => 'Delete', 'capability' => 'delete_user', 'input' => ['reassign' => 'int'], 'changes_record' => false, 'sets' => [], 'confirm' => true, 'validate' => fn ($model, array $input) => $this->check_reassign($model, (int) $input['reassign'])],
        ];

    }

    protected function check_reassign ($model, int $reassign) : array {

        if (($reassign === (int) $model->get_id()) || !($target = $this->find($reassign))) return ['reassign is another account.'];

        return Resources::inst()->credential_allows($this, 'act:delete', $target) ? [] : ['reassign is an account this password may hand content to.'];

    }

    public function act_send_password_reset ($model, array $input, Resource_Change $change) {

        $model->stage('reset', true);

        try {

            $sent = retrieve_password($model->get_column('user_login'));

        } finally {

            $model->stage('reset', false);

        }

        return is_wp_error($sent) ? $sent : $model;

    }

    public function act_delete ($model, array $input, Resource_Change $change) {

        if (!function_exists('wp_delete_user')) require_once ABSPATH . 'wp-admin/includes/user.php';

        $model->stage('delete', true);
        $model->stage('reassign', (int) $input['reassign']);

        try {

            return wp_delete_user($model->get_id(), (int) $input['reassign']) ? $model : new \WP_Error('not_saved', 'WordPress did not delete the account.');

        } finally {

            $model->stage('delete', false);

        }

    }

    public function get_consequences (Resource_Change $change) : array {

        if (!$change->before) return $this->build_mail_consequences($change);

        $id    = $change->before->get_id();
        $holds = array_keys(array_filter($this->get_conditions(), fn ($condition) => (bool) $condition($change->before, [])));
        $named = $holds ? [['kind' => 'conditions', 'label' => 'This account is: ' . implode(', ', $holds) . '.', 'items' => $holds]] : [];

        if ($change->operation !== 'delete') return [...$named, ...$this->build_mail_consequences($change)];

        global $wpdb;

        $posts = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type <> 'revision'", $id));

        return [...$named, ['kind' => 'deleted', 'label' => "Account {$id} is deleted and nothing undoes it.", 'items' => [$id]], ['kind' => 'content', 'label' => "{$posts} posts and other records move to account " . (int) ($change->input['reassign'] ?? 0) . '.', 'items' => []]];

    }

    protected function build_mail_consequences (Resource_Change $change) : array {

        $mail = fn (string $label, string $address) => [['kind' => 'mail', 'label' => $label, 'items' => $this->list_readable_address($change, $address)]];

        if (!$change->before) return $change->after->get_staged('notify') ? $mail("WordPress's new-user mail goes to the new address, with a link to set a password.", $change->after->get_column('user_email')) : [];

        $wp_user = $change->before->get_wp_user();

        if ($change->operation === 'send_password_reset') return apply_filters('send_retrieve_password_email', true, $wp_user->user_login, $wp_user) ? $mail("The site's reset mail goes to account {$wp_user->ID}'s own address.", $wp_user->user_email) : [];

        $userdata = $change->after->build_update_data();
        $user     = $this->build_core_user($wp_user);

        if (!isset($userdata['user_email']) || ($user['user_email'] === $userdata['user_email'])) return [];

        return apply_filters('send_email_change_email', true, $user, $userdata) ? $mail('WordPress mails the old address to say the email changed.', $wp_user->user_email) : [];

    }

    // As wp_update_user() builds it for its filters (E14).
    protected function build_core_user (\WP_User $wp_user) : array {

        $user = $wp_user->to_array();

        foreach (_get_additional_user_keys($wp_user) as $key) $user[$key] = get_user_meta($wp_user->ID, $key, true);

        return add_magic_quotes($user);

    }

    protected function list_readable_address (Resource_Change $change, string $address) : array {

        return in_array('email', $change->barred, true) ? [] : [$address];

    }

    public function get_search_fields () : array {

        return ['login', 'email', 'display_name'];

    }

    // Records

    public function find ($id) : ?object {

        $id = (int) $id;

        if (!$id || !($wp_user = get_userdata($id)) || !($model = (static::$model_class)::get_instance($id))) return null;

        // Models are cached per id: take the account as it is now.
        $model->set_wp_user($wp_user);

        return $model;

    }

    public function find_by_set_key (string $key) : ?object {

        $ids = get_users(['meta_key' => Resources::SET_KEY, 'meta_value' => $key, 'fields' => 'ID', 'number' => 1]);

        return $ids ? $this->find((int) $ids[0]) : null;

    }

    public function clear_record_cache ($id) : void {

        if (is_numeric($id)) clean_user_cache((int) $id);

    }

    // User ids overlap post ids: a bare number is never an account.
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

    // search, page, per_page
    public function query (array $args) : array {

        $query = array_filter([
            'search'         => ($search = trim((string) ($args['search'] ?? ''))) ? "*{$search}*" : '',
            'search_columns' => $search ? ['user_login', 'user_email', 'display_name'] : null,
            'number'         => $args['per_page'] ?? null,
            'paged'          => $args['page'] ?? null,
            'orderby'        => 'registered',
            'order'          => 'DESC',
        ]);

        if (class_exists(Credentials::class) && !is_null($roles = Credentials::inst()->get_roles_reached($this->get_name(), 'list')) && ($covered = $this->build_covered_query($roles))) $query['meta_query'] = $covered;

        return (static::$model_class)::query(apply_filters('lattice.resources.query', $query, $this));

    }

    // WordPress ANDs role arguments with a meta query, so none are given.
    protected function build_covered_query (array $roles) : array {

        global $wpdb;

        $key   = $wpdb->get_blog_prefix() . 'capabilities';
        $none  = in_array(Credential_Policy::NO_ROLE, $roles, true);
        $named = array_values(array_diff($roles, [Credential_Policy::NO_ROLE]));

        if (count($named) > static::QUERY_ROLES) return [];

        $values = array_map(fn ($sequence) => serialize(array_fill_keys($sequence, true)), $this->list_role_orders($named));

        if ($none) $values[] = serialize([]);

        $any = ['relation' => 'OR', ['key' => $key, 'value' => $values ?: ['-'], 'compare' => 'IN']];

        if ($none) $any[] = ['key' => $key, 'compare' => 'NOT EXISTS'];

        return [$any];

    }

    protected function list_role_orders (array $roles) : array {

        $sequences = [];

        foreach ($roles as $i => $role) {

            $sequences[] = [$role];

            foreach ($this->list_role_orders(array_values(array_diff_key($roles, [$i => 0]))) as $rest) $sequences[] = [$role, ...$rest];

        }

        return $sequences;

    }

    public function count_conditions (array $roles) : array {

        if (!$conditions = $this->get_conditions()) return [];

        // Beyond QUERY_ROLES the query does not narrow, so the count is unknown.
        if (!$meta = $this->build_covered_query($roles)) return array_fill_keys(array_keys($conditions), $roles ? null : 0);

        $counts = array_fill_keys(array_keys($conditions), 0);

        for ($page = 1; $ids = (new \WP_User_Query(['fields' => 'ID', 'number' => 500, 'paged' => $page, 'meta_query' => $meta, 'orderby' => 'ID']))->get_results(); $page++) {

            cache_users($ids);

            foreach ($ids as $id) if ($model = $this->find((int) $id)) foreach ($conditions as $name => $condition) $counts[$name] += (int) (bool) $condition($model, []);

        }

        return $counts;

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

    // name => fn ($model, array $values) : bool
    public function get_conditions () : array {

        return [];

    }

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

    // A stored false narrows, so it does not count.
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
            'roles'   => 'promote_user',
            'publish' => 'do_not_allow',
            default   => 'list_users',
        };

    }

    public function get_value_needs ($model, array $values) : ?string {

        if (!array_key_exists('roles', $values)) return null;

        $roles = array_values((array) $values['roles']);

        if ($model && ($roles === array_values((array) $model->get_wp_user()->roles))) return null;

        if (!($model ? current_user_can('promote_user', $model->get_id()) : current_user_can('promote_users'))) return 'promote_user';

        if (!function_exists('get_editable_roles')) require_once ABSPATH . 'wp-admin/includes/user.php';

        return array_diff($roles, array_keys(get_editable_roles())) ? 'promote_user' : null;

    }

    public function can (string $operation, $model = null) : bool {

        $meta = $model && in_array($operation, ['update', 'roles'], true);

        return current_user_can($this->get_capability($operation, $model), ...($meta ? [$model->get_id()] : []));

    }

}
