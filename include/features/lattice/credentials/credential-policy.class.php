<?php

namespace Digitalis;

// Rules that only narrow what an account may do. Anything it cannot read leaves problems, and a policy with problems denies everything.
class Credential_Policy {

    const ANSWERS         = ['allow', 'review', 'deny'];
    const KEYS            = ['version', 'rules'];
    const RULE_KEYS       = ['verbs', 'on', 'answer', 'fields', 'except', 'records', 'roles', 'when', 'waiting'];
    const RESOURCE_VERBS  = ['list', 'read', 'create', 'update', 'trash', 'untrash', 'undo'];
    const ACCOUNT_VERBS   = ['list', 'read', 'create', 'update', 'delete'];
    const ACCOUNT_FIELDS  = ['email', 'password', 'second_factor', 'role'];
    const METHODS         = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    const WHEN            = ['step_of_other_set'];
    const SETS            = 'change_set';
    // What a review rule carries: making, holding and offering the credential's own sets.
    const PROPOSING       = ['list', 'read', 'create', 'update', 'view:review', 'act:set_steps', 'act:submit_proposal', 'act:rebase_proposal', 'act:rebase_set', 'act:withdraw_set'];

    protected array $rules    = [];
    protected array $problems = [];

    public function __construct (mixed $policy) {

        if (!is_array($policy)) { $this->problems[] = 'A policy is an object.'; return; }

        if ($unknown = array_diff(array_keys($policy), static::KEYS)) $this->problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';
        if (($policy['version'] ?? null) !== 1)                    $this->problems[] = 'Its version is 1.';
        if (!is_array($policy['rules'] ?? null) || !array_is_list($policy['rules'])) { $this->problems[] = 'Its rules are a list.'; return; }

        foreach ($policy['rules'] as $position => $rule) {

            [$read, $problems] = $this->read_rule($rule);

            foreach ($problems as $problem) $this->problems[] = "Rule {$position}: {$problem}";

            if ($read) $this->rules[] = $read + ['position' => $position];

        }

    }

    // Whether any rule on this surface may grant something.
    public function opens (string $surface) : bool {

        return (bool) array_filter($this->rules, fn ($rule) => ($rule['surface'] === $surface) && ($rule['answer'] !== 'deny'));

    }

    public function proposes () : bool {

        return (bool) array_filter($this->rules, fn ($rule) => $rule['answer'] === 'review');

    }

    // Decided for the whole entry, so a review from a shared policy can be proposed under the credential's own rules too.
    public function allow_proposing () : void {

        foreach (static::PROPOSING as $verb) $this->rules[] = ['surface' => 'resource', 'target' => static::SETS, 'verbs' => [$verb], 'answer' => 'allow', 'rank' => [-1, 0], 'implicit' => true];

    }

    public function get_problems () : array {

        return $this->problems;

    }

    public function get_rules () : array {

        return array_values(array_filter($this->rules, fn ($rule) => empty($rule['implicit'])));

    }

    // $request: surface, target, verb, fields (empty for a whole-record verb), facts, after (the facts once the change is made), dry_run. Answers per field, '' standing for the record; the record before and after a change are two parts of one request.
    public function answer_fields (array $request) : array {

        if ($this->problems) return array_fill_keys(($request['fields'] ?? []) ?: [''], 'deny');

        if (!empty($request['dry_run'])) return $this->rehearse($request);

        $answers = [];

        $states = isset($request['after']) ? [$request['facts'] ?? [], $request['after']] : [$request['facts'] ?? []];

        foreach (($request['fields'] ?? []) ?: [''] as $field) $answers[$field] = static::strictest(array_map(fn ($facts) => $this->answer_one(['facts' => $facts] + $request, $field === '' ? null : $field), $states));

        return $answers;

    }

    public function answer (array $request) : string {

        return static::strictest($this->answer_fields($request));

    }

    public static function strictest (array $answers) : string {

        return $answers ? static::ANSWERS[max(array_map(fn ($answer) => array_search($answer, static::ANSWERS, true), $answers))] : 'deny';

    }

    // A rule answering allow or review on a verb grants its dry run; rehearse:<verb> grants the dry run alone.
    protected function rehearse (array $request) : array {

        $answers = $this->answer_fields(['dry_run' => false] + $request);

        foreach ($answers as $field => $answer) if ($answer === 'deny') $answers[$field] = ($this->answer_one(['verb' => "rehearse:{$request['verb']}"] + $request, $field === '' ? null : $field) === 'allow') ? 'allow' : 'deny';

        return array_map(fn ($answer) => ($answer === 'deny') ? 'deny' : 'allow', $answers);

    }

    protected function answer_one (array $request, ?string $field) : string {

        $best    = null;
        $answers = [];

        foreach ($this->rules as $rule) {

            if (!$rank = $this->match($rule, $request, $field)) continue;

            if (($best === null) || ($rank > $best)) [$best, $answers] = [$rank, []];

            if ($rank === $best) $answers[] = $rule['answer'];

        }

        $answer = static::strictest($answers);

        // Where nothing can review the change, review answers deny (S7).
        return (($answer === 'review') && (($request['reviewable'] ?? true) === false)) ? 'deny' : $answer;

    }

    // The rule's rank when it matches: its target, then the verb, so the verb only decides between rules on the same target.
    protected function match (array $rule, array $request, ?string $field) : ?array {

        if ($rule['surface'] !== $request['surface']) return null;

        if (is_null($verb_rank = $this->match_verb($rule, $request))) return null;

        if (!$this->match_target($rule, $request))               return null;
        if (!$this->match_field($rule, $field))                  return null;
        if (!$this->match_facts($rule, $request['facts'] ?? [])) return null;

        return [...$rule['rank'], $verb_rank];

    }

    protected function match_verb (array $rule, array $request) : ?int {

        $verb = $request['verb'];

        foreach ($rule['verbs'] as $token) {

            if (($token === $verb) || (($verb === 'HEAD') && ($token === 'GET'))) return 1;

            if ($this->in_group($token, $verb, $request['surface'])) return 0;

        }

        return null;

    }

    protected function in_group (string $token, string $verb, string $surface) : bool {

        if (str_starts_with($token, 'rehearse:') && str_starts_with($verb, 'rehearse:')) return ($token !== $verb) && $this->in_group(substr($token, 9), substr($verb, 9), $surface);

        return match ($token) {
            'reads'  => in_array($verb, ['list', 'read'], true) || str_starts_with($verb, 'view:'),
            'writes' => !in_array($verb, ['list', 'read'], true) && !str_starts_with($verb, 'view:') && !str_starts_with($verb, 'rehearse:'),
            'view:*' => str_starts_with($verb, 'view:'),
            'act:*'  => str_starts_with($verb, 'act:'),
            '*'      => ($surface === 'route'),
            default  => false,
        };

    }

    protected function match_target (array $rule, array $request) : bool {

        $target = is_array($request['target']) ? '' : (string) $request['target'];

        return match ($rule['surface']) {
            'resource' => is_null($rule['target']) ? ($target !== static::SETS) : ($rule['target'] === $target),
            'route'    => (bool) array_filter((array) $request['target'], fn ($each) => preg_match('#^' . str_replace('\*', '.*', preg_quote(strtolower($rule['target']), '#')) . '$#', strtolower($each))),
            'ability'  => is_null($rule['target']) || ($rule['target'] === $target) || (str_ends_with($rule['target'], '/*') && (($request['facts']['category'] ?? null) === substr($rule['target'], 0, -2))),
            'accounts' => true,
        };

    }

    // A rule naming fields covers only those; one leaving fields out covers every other, and a verb on the whole record.
    protected function match_field (array $rule, ?string $field) : bool {

        if (isset($rule['fields'])) return !is_null($field) && in_array($field, $rule['fields'], true);
        if (isset($rule['except'])) return is_null($field) || !in_array($field, $rule['except'], true);

        return true;

    }

    protected function match_facts (array $rule, array $facts) : bool {

        if (isset($rule['when']) && empty($facts[$rule['when']])) return false;

        if (isset($rule['roles'])) {

            $held  = (array) ($facts['roles'] ?? []);
            $given = (array) ($facts['gives'] ?? []);

            // An account qualifies only if every role it holds is listed and it holds nothing beyond them; one being made holds nothing yet, and is given at least one.
            if (array_key_exists('roles', $facts) ? (!$held || array_diff($held, $rule['roles']) || !array_key_exists('extra_caps', $facts) || $facts['extra_caps']) : !$given) return false;
            if (array_diff($given, $rule['roles'])) return false;

        }

        if (!isset($rule['records']) || !empty($facts['any_record'])) return true;

        $records = $rule['records'];

        if (is_array($records)) return in_array((string) ($facts['id'] ?? ''), array_map('strval', $records), true);
        if ($records === 'own') return !empty($facts['own']);
        if ($records === 'made_by_credential') return !empty($facts['made_by_credential']);

        return is_callable($facts['conditions'] ?? null) && (bool) ($facts['conditions'])($records);

    }

    protected function read_rule (mixed $rule) : array {

        if (!is_array($rule) || array_is_list($rule)) return [null, ['A rule is an object.']];

        $problems = [];

        if ($unknown = array_diff(array_keys($rule), static::RULE_KEYS)) $problems[] = 'Unknown: ' . implode(', ', $unknown) . '.';

        $answer = $rule['answer'] ?? null;
        $verbs  = is_string($rule['verbs'] ?? null) ? [$rule['verbs']] : ($rule['verbs'] ?? null);

        if (!in_array($answer, static::ANSWERS, true))                                            $problems[] = 'Its answer is allow, review or deny.';
        if (!is_array($verbs) || !$verbs || !array_is_list($verbs) || array_filter($verbs, fn ($verb) => !is_string($verb))) $problems[] = 'Its verbs are a name or a list of names.';
        if (!is_string($rule['on'] ?? null))                                                      $problems[] = 'It says what it is on.';

        if ($problems) return [null, $problems];

        [$surface, $target] = array_pad(explode(':', $rule['on'], 2), 2, null);

        $read = ['surface' => $surface, 'target' => $target, 'verbs' => array_values(array_unique($verbs)), 'answer' => $answer];

        $problems = match ($surface) {
            'resource' => $this->read_resource_rule($read, $rule),
            'route'    => $this->read_route_rule($read, $rule),
            'ability'  => $this->read_ability_rule($read, $rule),
            'accounts' => $this->read_accounts_rule($read, $rule),
            default    => ['It is on a resource, a route, an ability or accounts.'],
        };

        if ($problems) return [null, $problems];

        foreach (['fields', 'except', 'records', 'roles', 'when'] as $key) if (isset($rule[$key])) $read[$key] = $rule[$key];

        $read['waiting'] = !empty($rule['waiting']);
        $read['rank']    = $this->rank($read);

        return [$read, []];

    }

    protected function read_resource_rule (array &$read, array $rule) : array {

        $problems = [];

        if (!is_string($read['target']) || !preg_match('/^([a-z0-9_]+|\*)$/', $read['target'])) $problems[] = 'It is on resource:<name> or resource:*.';
        if ($read['target'] === '*') $read['target'] = null;

        foreach ($read['verbs'] as $verb) if (!$this->is_resource_verb($verb)) $problems[] = "No verb {$verb} on a resource.";

        if (($read['answer'] === 'review') && array_filter($read['verbs'], fn ($verb) => !$this->is_write($verb))) $problems[] = 'A read cannot be reviewed.';

        // Groups never reach the sets, so a rule that would rely on one is refused rather than ignored.
        if (($read['target'] === static::SETS) && array_filter($read['verbs'], fn ($verb) => !preg_match('/^(rehearse:)?(list|read|create|update|trash|untrash|undo|(act|view):[a-z0-9_]+)$/', $verb))) $problems[] = 'On change_set each verb is named.';

        if (($rule['records'] ?? null) === 'made_by_credential') $problems[] = 'made_by_credential is for accounts.';

        return [...$problems, ...$this->read_conditions($rule, ['fields', 'except', 'records', 'when'])];

    }

    protected function read_route_rule (array &$read, array $rule) : array {

        $problems = [];

        if (!is_string($read['target']) || !(str_starts_with($read['target'], '/') || ($read['target'] === '*'))) $problems[] = 'It is on route:/<path>, where * stands for anything, or route:*.';
        if (array_diff($read['verbs'], [...static::METHODS, '*']))                       $problems[] = 'Its verbs are HTTP methods, or *.';
        if ($read['answer'] === 'review')                                                 $problems[] = 'Only the layer\'s verbs can be reviewed.';

        return [...$problems, ...$this->read_conditions($rule, [])];

    }

    protected function read_ability_rule (array &$read, array $rule) : array {

        $problems = [];

        if (!is_string($read['target']) || !preg_match('#^([a-z0-9-]+(/[a-z0-9-]+)*(/\*)?|\*)$#', $read['target'])) $problems[] = 'It is on ability:<name>, ability:<category>/* or ability:*.';
        if ($read['target'] === '*') $read['target'] = null;

        if ($read['verbs'] !== ['run'])   $problems[] = 'Its verb is run.';
        if ($read['answer'] === 'review') $problems[] = 'Only the layer\'s verbs can be reviewed.';

        return [...$problems, ...$this->read_conditions($rule, [])];

    }

    protected function read_accounts_rule (array &$read, array $rule) : array {

        $problems = [];

        if (!is_null($read['target'])) $problems[] = 'It is on accounts.';

        foreach ($read['verbs'] as $verb) if (!in_array($verb, [...static::ACCOUNT_VERBS, 'reads', 'writes'], true)) $problems[] = "No verb {$verb} on accounts.";

        if (!is_array($rule['roles'] ?? null) || !$rule['roles'] || array_filter($rule['roles'], fn ($role) => !is_string($role))) $problems[] = 'It names the roles it may manage.';
        if (array_diff([...(array) ($rule['fields'] ?? []), ...(array) ($rule['except'] ?? [])], static::ACCOUNT_FIELDS)) $problems[] = 'Its fields are among ' . implode(', ', static::ACCOUNT_FIELDS) . '.';
        if (isset($rule['records']) && !is_array($rule['records']) && ($rule['records'] !== 'made_by_credential')) $problems[] = 'Its records are a list of ids or made_by_credential.';

        return [...$problems, ...$this->read_conditions($rule, ['fields', 'except', 'records', 'roles'])];

    }

    protected function read_conditions (array $rule, array $allowed) : array {

        $problems = [];

        if ($refused = array_diff(array_intersect(array_keys($rule), ['fields', 'except', 'records', 'roles', 'when']), $allowed)) $problems[] = 'It cannot hold ' . implode(', ', $refused) . '.';

        if (isset($rule['fields'], $rule['except'])) $problems[] = 'It names fields or leaves them out, not both.';

        foreach (['fields', 'except'] as $key) if (isset($rule[$key]) && (!is_array($rule[$key]) || !$rule[$key] || !array_is_list($rule[$key]) || array_filter($rule[$key], fn ($name) => !is_string($name)))) $problems[] = "Its {$key} are a list of names.";

        $records = $rule['records'] ?? null;

        if (isset($rule['records']) && !(is_string($records) && ($records !== '')) && !(is_array($records) && $records && array_is_list($records) && !array_filter($records, fn ($id) => !is_scalar($id)))) $problems[] = 'Its records are own, a condition\'s name or a list of ids.';

        if (isset($rule['when']) && !in_array($rule['when'], static::WHEN, true)) $problems[] = 'Its when is ' . implode(' or ', static::WHEN) . '.';

        if (isset($rule['waiting']) && !is_bool($rule['waiting'])) $problems[] = 'Its waiting is true or false.';

        return $problems;

    }

    protected function is_resource_verb (string $verb) : bool {

        if (str_starts_with($verb, 'rehearse:')) return $this->is_write(substr($verb, 9));

        return in_array($verb, [...static::RESOURCE_VERBS, 'reads', 'writes', 'view:*', 'act:*'], true) || (bool) preg_match('/^(view|act):[a-z0-9_]+$/', $verb);

    }

    protected function is_write (string $verb) : bool {

        return in_array($verb, ['create', 'update', 'trash', 'untrash', 'undo', 'writes', 'act:*'], true) || (bool) preg_match('/^act:[a-z0-9_]+$/', $verb);

    }

    // A wildcard, then a wildcard with a condition, a named target, a named target with a condition; a route by how much of it is fixed.
    protected function rank (array $rule) : array {

        $conditioned = (int) (isset($rule['fields']) || isset($rule['except']) || isset($rule['records']) || isset($rule['when']));

        return match ($rule['surface']) {
            'route'   => [str_starts_with($rule['target'], '*') ? 0 : 2, strlen(str_replace('*', '', $rule['target']))],
            'ability' => [is_null($rule['target']) ? 0 : (str_ends_with($rule['target'], '/*') ? 1 : 2), 0],
            default   => [(is_null($rule['target']) && ($rule['surface'] !== 'accounts') ? 0 : 2) + $conditioned, 0],
        };

    }

}
