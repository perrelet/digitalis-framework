<?php

namespace Digitalis;

use WP_Application_Passwords;

// Which credential holds which policy, and what it answers. A credential with no entry is unchanged; one whose entry cannot be read denies everything.
class Credentials extends Service {

    const META       = '_lattice_credential_policies';
    const SHARED     = 'lattice_credential_policies';
    const ENTRY_KEYS = ['version', 'expires', 'rules', 'policy'];
    const LAYER      = '/lattice/v1/';

    // Shared policies defined in code, by name; false where two define one name.
    protected array $defined = [];

    // The password this request authenticated with, once WordPress says which: user_id, uuid and its entry, or null for an unscoped one.
    protected ?array $current = null;
    protected bool $noted     = false;

    // The first password a request authenticates with is its credential: one checked later (a route checking a password it was sent) never replaces it.
    public function note_current (int $user_id, string $uuid) : void {

        if ($this->noted) return;

        $this->noted = true;

        $entry = $this->get_entry($user_id, $uuid);

        $this->current = is_null($entry) ? null : ['user_id' => $user_id, 'uuid' => $uuid, 'entry' => $entry];

    }

    public function get_current () : ?array {

        return $this->current;

    }

    // Why a scoped password is refused outright, whatever it asks for, or '' when it is not.
    public function refuse_entry (mixed $entry) : string {

        if ($this->read($entry)[1]) return 'lattice_credential_unreadable';

        return $this->is_expired($entry) ? 'lattice_credential_expired' : '';

    }

    // A route is reachable when a route rule allows it: decided on the path and, once routes are known, every pattern WordPress could match it to, the most specific rule winning across them.
    public function may_reach (mixed $entry, array $targets, string $method) : bool {

        // The layer's own routes are opened by resource rules alone, since the layer decides each request itself; a route rule never opens them. A path some other route also matches is decided by route rules.
        if (!array_filter($targets, fn ($target) => !str_starts_with(strtolower($target) . '/', static::LAYER))) return $this->opens_layer($entry);

        return $this->answer($entry, ['surface' => 'route', 'target' => $targets, 'verb' => $method]) === 'allow';

    }

    public function opens_layer (mixed $entry) : bool {

        [$policies, $problems] = $this->read($entry);

        return $policies && !$problems && !array_filter($policies, fn ($policy) => !$policy->opens('resource'));

    }

    public function define (string $name, array $policy) : void {

        $this->defined[$name] = array_key_exists($name, $this->defined) ? false : $policy;
        $this->read           = [];

    }

    // Entries

    public function get_entries (int $user_id) : array {

        $entries = get_user_meta($user_id, static::META, true);

        return is_array($entries) ? $entries : [];

    }

    // Null for a password with no policy. Storage that cannot be read is an entry the reader refuses, so it never makes a scoped password a full one.
    public function get_entry (int $user_id, string $uuid) : mixed {

        $entries = get_user_meta($user_id, static::META, true);

        if ($entries === '') return null;

        return is_array($entries) ? ($entries[$uuid] ?? null) : false;

    }

    // Storage that cannot be read refuses every password on the account, so nothing writes over it: revoking one password must not make the others full ones.
    protected function is_unreadable (int $user_id) : bool {

        $entries = get_user_meta($user_id, static::META, true);

        return ($entries !== '') && !is_array($entries);

    }

    public function set_entry (int $user_id, string $uuid, array $entry) : bool {

        if ($this->is_unreadable($user_id)) return false;

        // User meta is unslashed as it is stored, so a route rule's \d would arrive as d.
        update_user_meta($user_id, static::META, wp_slash([$uuid => $entry] + $this->get_entries($user_id)));

        return true;

    }

    public function clear_entry (int $user_id, string $uuid) : bool {

        if ($this->is_unreadable($user_id)) return false;

        $entries = $this->get_entries($user_id);

        unset($entries[$uuid]);

        $entries ? update_user_meta($user_id, static::META, wp_slash($entries)) : delete_user_meta($user_id, static::META);

        return true;

    }

    // Every scoped password on the site, so a way back can revoke them first.
    public function get_scoped () : array {

        $scoped = [];

        foreach (get_users(['meta_key' => static::META, 'fields' => 'ID']) as $user_id) foreach ($this->get_entries((int) $user_id) as $uuid => $entry) $scoped[] = ['user_id' => (int) $user_id, 'uuid' => (string) $uuid, 'name' => WP_Application_Passwords::get_user_application_password((int) $user_id, (string) $uuid)['name'] ?? null, 'entry' => $entry];

        return $scoped;

    }

    // Shared policies

    public function get_stored () : array {

        return (array) get_option(static::SHARED, []);

    }

    // A stored policy keeps its id while its name is in use; deleted, the name may be used again under a new id, which grants nothing to credentials that held the old one.
    public function store_shared (string $name, array $policy) : string {

        $stored = $this->get_stored();
        $id     = array_search($name, array_column($stored, 'name', 'id'), true) ?: wp_generate_uuid4();

        $stored[$id] = ['id' => $id, 'name' => $name, 'policy' => $policy];

        update_option(static::SHARED, $stored, false);

        $this->read = [];

        return $id;

    }

    public function delete_shared (string $id) : void {

        $stored = $this->get_stored();

        unset($stored[$id]);

        update_option(static::SHARED, $stored, false);

        $this->read = [];

    }

    // A stored policy's id, or a name defined in code; null for none, and a problem for a name in both places.
    public function find_shared (string $name) : array {

        $stored = array_keys(array_filter($this->get_stored(), fn ($shared) => $shared['name'] === $name));

        if (array_key_exists($name, $this->defined) && $stored) return [null, "{$name} is defined in code and stored."];

        return [$stored[0] ?? (array_key_exists($name, $this->defined) ? $name : null), null];

    }

    public function get_shared (string $ref) : ?Credential_Policy {

        if ($stored = $this->get_stored()[$ref] ?? null) return new Credential_Policy($stored['policy']);

        if (!array_key_exists($ref, $this->defined)) return null;

        if (($this->defined[$ref] === false) || $this->find_shared($ref)[1]) return new Credential_Policy(null);

        return new Credential_Policy($this->defined[$ref]);

    }

    public function get_defined () : array {

        return $this->defined;

    }

    // Reading an entry

    protected array $read = [];

    // [policies, problems, expires]: each policy must allow, as surfaces must. Read once per request.
    public function read (mixed $entry) : array {

        return $this->read[md5(serialize($entry))] ??= $this->read_entry($entry);

    }

    protected function read_entry (mixed $entry) : array {

        if (!is_array($entry)) return [[], ['An entry is an object.'], null];

        $problems = [];
        $policies = [];

        if ($unknown = array_diff(array_keys($entry), static::ENTRY_KEYS)) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';
        if (($entry['version'] ?? null) !== 1)                            $problems[] = 'Its version is 1.';
        if (!isset($entry['rules']) && !isset($entry['policy']))         $problems[] = 'It holds rules, a shared policy or both.';

        $expires = array_key_exists('expires', $entry) ? static::read_expires($entry['expires']) : null;

        if ($expires === false) $problems[] = 'Its expires is a time with a zone, or a date.';

        if (array_key_exists('rules', $entry)) $policies[] = new Credential_Policy(['version' => 1, 'rules' => $entry['rules']]);

        if (isset($entry['policy'])) $policies[] = (is_string($entry['policy']) ? $this->get_shared($entry['policy']) : null) ?? new Credential_Policy(null);

        foreach ($policies as $policy) array_push($problems, ...$policy->get_problems());

        if (array_filter($policies, fn ($policy) => $policy->proposes())) foreach ($policies as $policy) $policy->allow_proposing();

        return [$policies, $problems, ($expires === false) ? null : $expires];

    }

    // A date, or a date and time with a zone; a bare date ends at the end of that day in the site's zone. Nothing relative, so an expiry never moves.
    public static function read_expires (mixed $expires) : int|false {

        if (!is_string($expires) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2}))?$/', $expires, $match) || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) return false;

        try {

            return (new \DateTimeImmutable(isset($match[4]) ? $expires : "{$expires} 23:59:59", wp_timezone()))->getTimestamp();

        } catch (\Exception $e) {

            return false;

        }

    }

    public function is_expired (array $entry, ?int $now = null) : bool {

        [, , $expires] = $this->read($entry);

        return !is_null($expires) && (($now ?? time()) > $expires);

    }

    public function answer_fields (mixed $entry, array $request) : array {

        [$policies, $problems, $expires] = $this->read($entry);

        $deny = array_fill_keys(($request['fields'] ?? []) ?: [''], 'deny');

        if ($problems || !$policies || (!is_null($expires) && (time() > $expires))) return $deny;

        // A request on an ability is decided by its category, so one whose category is unknown is denied.
        if (($request['surface'] === 'ability') && !isset($request['facts']['category'])) return $deny;

        $each = array_map(fn ($policy) => $policy->answer_fields($request), $policies);

        foreach ($deny as $field => $answer) $deny[$field] = Credential_Policy::strictest(array_column($each, $field));

        return $deny;

    }

    public function answer (mixed $entry, array $request) : string {

        return Credential_Policy::strictest($this->answer_fields($entry, $request));

    }

    // Names

    // What a policy names that does not exist on this site now, rule by rule; a rule marked waiting is left alone.
    public function get_unknown (Credential_Policy $policy) : array {

        $unknown = [];

        foreach ($policy->get_rules() as $rule) if (!$rule['waiting'] && ($missing = $this->find_missing($rule))) $unknown[] = "Rule {$rule['position']}: no " . implode(', no ', $missing) . '.';

        return $unknown;

    }

    protected function find_missing (array $rule) : array {

        return match ($rule['surface']) {
            'resource' => $this->find_missing_on_resource($rule),
            'route'    => $this->route_exists($rule['target']) ? [] : ["route {$rule['target']}"],
            'ability'  => $this->ability_exists($rule['target']) ? [] : ["ability {$rule['target']}"],
            'accounts' => array_map(fn ($role) => "role {$role}", array_diff($rule['roles'], array_keys(wp_roles()->roles))),
        };

    }

    protected function find_missing_on_resource (array $rule) : array {

        $resources = class_exists(Resources::class) ? Resources::inst() : null;

        // Under resource:* a field must exist on some resource, or a typo in except would leave the real field open everywhere.
        if (is_null($rule['target'])) return array_map(fn ($field) => "field {$field}", array_diff([...($rule['fields'] ?? []), ...($rule['except'] ?? [])], array_merge([], ...array_values(array_map(fn ($resource) => array_keys($resource->get_fields()), $resources?->get_resources() ?? [])))));

        if (!$resource = $resources?->get_resource($rule['target'])) return ["resource {$rule['target']}"];

        $missing = [];
        $fields  = array_keys($resource->get_fields());

        foreach ([...($rule['fields'] ?? []), ...($rule['except'] ?? [])] as $field) if (!in_array($field, $fields, true)) $missing[] = "field {$field}";

        foreach ($rule['verbs'] as $verb) {

            $named = preg_replace('/^rehearse:/', '', $verb);

            if (preg_match('/^act:([a-z0-9_]+)$/', $named, $match) && !isset($resource->get_actions()[$match[1]])) $missing[] = "action {$match[1]}";
            if (preg_match('/^view:([a-z0-9_]+)$/', $named, $match) && !isset($resource->get_views()[$match[1]]))  $missing[] = "view {$match[1]}";

        }

        $records = $rule['records'] ?? null;

        if (is_string($records) && ($records !== 'own') && !isset((method_exists($resource, 'get_conditions') ? $resource->get_conditions() : [])[$records])) $missing[] = "condition {$records}";

        return $missing;

    }

    // A path with no * names a route that answers it; one with * names the start of one or more.
    protected function route_exists (string $pattern) : bool {

        $fixed = strtolower(strtok($pattern, '*') ?: '');

        foreach (array_keys(rest_get_server()->get_routes()) as $route) {

            if (str_contains($pattern, '*') ? str_starts_with(strtolower($route), $fixed) || str_starts_with($fixed, strtolower($route) . '/') : preg_match('@^' . $route . '$@i', $pattern)) return true;

        }

        return false;

    }

    protected function ability_exists (?string $name) : bool {

        if (is_null($name)) return true;

        if (str_ends_with($name, '/*')) return function_exists('wp_has_ability_category') && wp_has_ability_category(substr($name, 0, -2));

        return function_exists('wp_has_ability') && wp_has_ability($name);

    }

}
