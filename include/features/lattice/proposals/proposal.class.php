<?php

namespace Digitalis;

use WP_Error;

class Proposal {

    const META = [
        'FOR'          => '_proposal_for',
        'AUTHOR'       => '_proposal_author',
        'CREATED'      => '_proposal_created',
        'BASE_VERSION' => '_proposal_base_version',
        'NOTE'         => '_proposal_note',
        'SAVED_BY'     => '_proposal_saved_by',
        'HAS_PROPOSAL' => '_has_proposal',
    ];

    // field => [column, kses context]
    const FILTERED = ['title' => ['post_title', 'title_save_pre'], 'excerpt' => ['post_excerpt', 'post'], 'content' => ['post_content', 'post']];

    protected $original = false;
    protected $changes;
    protected $policy;
    protected $held;
    protected $removed = [];

    public function __construct (protected Post $draft) {}

    // Finding

    public static function get_post_types () : array {

        return Proposals::inst('proposals')?->get_post_types() ?? [];

    }

    public static function is_moderated (Model_Resource $resource) : bool {

        $class  = $resource->get_model_class();
        $traits = array_merge(...array_map('class_uses', [$class, ...array_values(class_parents($class))]));

        while ($trait = array_shift($traits)) {

            if ($trait === Has_Proposals::class) return true;

            array_push($traits, ...array_values(class_uses($trait)));

        }

        return false;

    }

    public static function get_resource_for (string $post_type) : ?Model_Resource {

        return in_array($post_type, static::get_post_types(), true) ? Resources::inst()->get_resource_for_post_type($post_type) : null;

    }

    // An options page resource's id is its slug.
    public static function find_model ($post_id) : ?Post {

        return (is_numeric($post_id) && ($post_id > 0)) ? static::get_resource_for((string) get_post_type((int) $post_id))?->find((int) $post_id) : null;

    }

    public static function find (int $draft_id) : ?static {

        return static::find_model($draft_id)?->get_proposal();

    }

    public static function query_open (array $args = []) : array {

        if (!$types = static::get_post_types()) return [];

        $drafts = Post::query(array_merge([
            'post_type'      => $types,
            'post_status'    => ['draft', 'pending'],
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            '_suppress'      => true,
            'meta_query'     => [['key' => static::META['FOR'], 'compare' => 'EXISTS']],
        ], $args));

        return array_values(array_filter(array_map(fn ($draft) => static::find($draft->get_id()), $drafts)));

    }

    // Every draft linked to a record, wherever its status.
    public static function find_drafts_for (int $original_id) : array {

        if (!$types = static::get_post_types()) return [];

        return Post::query([
            'post_type'      => $types,
            'post_status'    => array_keys(get_post_stati()),
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            '_suppress'      => true,
            'meta_query'     => [['key' => static::META['FOR'], 'value' => $original_id]],
        ]);

    }

    public static function count_awaiting_review () : int {

        return static::count_open(['post_status' => 'pending']);

    }

    public static function count_sent_back_for (int $author_id) : int {

        return static::count_open(['post_status' => 'draft', 'author' => $author_id, 'meta_query' => ['relation' => 'AND', ['key' => static::META['FOR'], 'compare' => 'EXISTS'], ['key' => static::META['NOTE'], 'compare' => 'EXISTS']]]);

    }

    protected static function count_open (array $args) : int {

        if (!$types = static::get_post_types()) return 0;

        Post::query(array_merge([
            'post_type'      => $types,
            'posts_per_page' => 1,
            '_suppress'      => true,
            'meta_query'     => [['key' => static::META['FOR'], 'compare' => 'EXISTS']],
        ], $args), $wp_query);

        return (int) $wp_query->found_posts;

    }

    // Here, not on the page: mail built in REST saves and cron needs it.
    public static function get_queue_url () : string {

        return admin_url('admin.php?page=' . (Proposals::inst('proposals')?->get_page_slug() ?? ''));

    }

    // Reading

    public function get_draft () : Post {

        return $this->draft;

    }

    public function get_id () : int {

        return $this->draft->get_id();

    }

    public function get_resource () : ?Model_Resource {

        return static::get_resource_for($this->draft->get_post_type());

    }

    public function get_original () : ?Post {

        if ($this->original === false) $this->original = $this->get_resource()?->find($this->draft->get_proposed_for_id());

        return $this->original;

    }

    public function get_author () : ?User {

        return ($id = $this->draft->get_proposal_author_id()) ? User::get_instance($id) : null;

    }

    public function get_created () : int {

        return $this->draft->get_proposal_created();

    }

    public function get_note () : string {

        return (string) $this->draft->get_proposal_note();

    }

    public function get_saved_by () : int {

        return $this->draft->get_proposal_saved_by();

    }

    public function is_submitted () : bool {

        return $this->draft->get_status() === 'pending';

    }

    public function was_sent_back () : bool {

        return !$this->is_submitted() && !is_null($this->draft->get_proposal_note());

    }

    public function is_stale () : bool {

        return !($original = $this->get_original()) || ($original->get_version() !== $this->draft->get_proposal_base_version());

    }

    // A draft restored from the Trash after another proposal opened is not the record's proposal.
    public function is_current () : bool {

        return $this->get_original()?->get_open_proposal_id() === $this->get_id();

    }

    public function forget () : void {

        $this->original = false;
        $this->changes  = null;
        $this->policy   = null;
        $this->held     = null;
        $this->removed  = [];

        $this->draft->reload();

    }

    // Changes

    // Status stays the record's.
    public function get_changes () : array {

        if (isset($this->changes)) return $this->changes;

        if (!($resource = $this->get_resource()) || !($original = $this->get_original())) return $this->changes = [];

        $fields = $resource->get_fields_for($this->draft);
        $live   = $resource->read($original, false);

        return $this->changes = array_filter(array_diff_key($resource->read($this->draft, false), ['status' => 0]), fn ($value, $name) => !$fields[$name]->computed && ($value !== ($live[$name] ?? null)), ARRAY_FILTER_USE_BOTH);

    }

    public function get_values () : array {

        return array_diff_key($this->get_changes(), $this->get_held_back());

    }

    // name => why approving leaves it out
    public function get_held_back () : array {

        if (isset($this->held)) return $this->held;

        $fields = $this->get_resource()?->get_fields_for($this->draft) ?? [];
        $held   = array_map(fn () => 'it changes only through an action', array_filter(array_intersect_key($fields, $this->get_changes()), fn ($field) => $field->action_only));

        foreach (array_intersect_key($this->get_changes(), array_flip(Resources::CONTROL)) as $name => $value) $held[$name] ??= 'the resources layer reserves its name';

        if ($this->get_markup_policy() === 'exclude') foreach (array_keys($this->find_markup_removed(array_keys($this->get_changes()))) as $name) $held[$name] ??= 'approving would take markup off the live version';

        return $this->held = $held;

    }

    public function label_fields (array $names) : array {

        $fields = $this->get_resource()?->get_fields_for($this->draft) ?? [];

        return array_values(array_map(fn ($name) => ($fields[$name]->acf['label'] ?? '') ?: $name, $names));

    }

    public function get_changed_labels () : array {

        return $this->label_fields(array_keys($this->get_values()));

    }

    // Needs no right to change the record, so its author sees it too.
    public function present () : array {

        if (!($resource = $this->get_resource()) || !($original = $this->get_original())) return [];

        $values = $this->get_values();

        return $resource->present_changes(array_intersect_key($resource->read($original), $values), $resource->mask($values, $resource->get_fields_for($this->draft)), $original);

    }

    // Markup

    // field => [markup => count]
    public static function find_markup_at_risk (Post $original, int $user_id) : array {

        if (user_can($user_id, 'unfiltered_html') || !$resource = static::get_resource_for($original->get_post_type())) return [];

        $at_risk = [];

        foreach (static::read_filtered($resource, $original) as $name => $value) if (($markup = static::count_markup($value)) && ($lost = static::subtract_markup($markup, static::count_markup(static::filter($name, $value))))) $at_risk[$name] = $lost;

        return $at_risk;

    }

    public function get_markup_removed () : array {

        return $this->find_markup_removed(array_keys($this->get_values()));

    }

    protected function find_markup_removed (array $names) : array {

        $key = implode(',', $names);

        if (isset($this->removed[$key])) return $this->removed[$key];

        if (!($resource = $this->get_resource()) || !($original = $this->get_original())) return [];

        $live    = array_intersect_key(static::read_filtered($resource, $original), array_flip($names));
        $draft   = static::read_filtered($resource, $this->draft);
        $removed = [];

        foreach ($live as $name => $value) if (($markup = static::count_markup($value)) && ($lost = static::subtract_markup($markup, static::count_markup($draft[$name] ?? null)))) $removed[$name] = $lost;

        return $this->removed[$key] = $removed;

    }

    public static function decide_markup_policy (Post $original, int $user_id, array $markup) : string {

        if (!$markup) return '';

        $policy = (string) apply_filters('lattice.proposal.markup', 'warn', $original, $user_id, $markup);

        return in_array($policy, ['warn', 'refuse', 'exclude'], true) ? $policy : 'warn';

    }

    public function get_markup_policy () : string {

        if (!$original = $this->get_original()) return '';

        return $this->policy ??= static::decide_markup_policy($original, $this->get_saved_by(), $this->find_markup_removed(array_keys($this->get_changes())));

    }

    public static function refuse_proposer (Post $original, int $user_id) : string {

        $at_risk = static::find_markup_at_risk($original, $user_id);

        if (static::decide_markup_policy($original, $user_id, $at_risk) !== 'refuse') return '';

        return 'This has markup your account can\'t keep (' . static::describe_markup($at_risk, static::get_resource_for($original->get_post_type())) . '), and this site takes no proposals on it from accounts like yours. Ask someone who can edit it directly.';

    }

    public static function describe_markup (array $by_field, ?Model_Resource $resource = null) : string {

        return implode('; ', array_map(fn ($name, $lost) => (($resource?->get_field($name)?->acf['label'] ?? '') ?: ucfirst($name)) . ': ' . implode(', ', array_map(fn ($markup, $count) => $markup . ($count > 1 ? " ×{$count}" : ''), array_keys($lost), $lost)), array_keys($by_field), $by_field));

    }

    protected static function read_filtered (Model_Resource $resource, Post $model) : array {

        $values = [];

        foreach (static::FILTERED as $name => [$column]) if ($resource->get_field($name)?->source === 'model') $values[$name] = (string) $model->get_wp_post()->$column;

        foreach ($resource->get_fields_for($model) as $name => $field) if ($field->source === 'acf') $values[$name] = $model->get_field($name, false);

        return $values;

    }

    protected static function filter (string $name, $value) {

        if (isset(static::FILTERED[$name])) return wp_kses((string) $value, static::FILTERED[$name][1]);

        return map_deep($value, fn ($each) => is_string($each) ? wp_kses_post($each) : $each);

    }

    // Block attributes' strings too.
    protected static function count_markup ($value) : array {

        $counts = [];

        $scan = function (string $html) use (&$counts) {

            $tags = new \WP_HTML_Tag_Processor($html);

            while ($tags->next_tag()) {

                $tag = strtolower($tags->get_tag());

                $counts[$tag] = ($counts[$tag] ?? 0) + 1;

                foreach ((array) $tags->get_attribute_names_with_prefix('') as $attribute) $counts["{$attribute} on {$tag}"] = ($counts["{$attribute} on {$tag}"] ?? 0) + 1;

            }

        };

        $walk = function (array $blocks) use (&$walk, $scan) {

            foreach ($blocks as $block) {

                array_walk_recursive($block['attrs'], function ($value) use ($scan) { if (is_string($value) && str_contains($value, '<')) $scan($value); });

                $walk($block['innerBlocks']);

            }

        };

        $values = (array) $value;

        array_walk_recursive($values, function ($html) use ($scan, $walk) {

            if (!is_string($html) || !str_contains($html, '<')) return;

            $scan($html);

            if (has_blocks($html)) $walk(parse_blocks($html));

        });

        return $counts;

    }

    // An element that goes takes its attributes with it.
    protected static function subtract_markup (array $before, array $after) : array {

        $lost = [];

        foreach ($before as $markup => $count) if (($left = $count - ($after[$markup] ?? 0)) > 0) $lost[$markup] = $left;

        foreach ($lost as $markup => $count) {

            if (!str_contains($markup, ' on ')) continue;

            $left = $count - ($lost[substr($markup, strrpos($markup, ' on ') + 4)] ?? 0);

            if ($left > 0) $lost[$markup] = $left;
            else unset($lost[$markup]);

        }

        return $lost;

    }

    // Approving

    // As the current account.
    public function review () : array|WP_Error {

        return $this->write(['dry_run' => true]);

    }

    public function refuse_approval () : string {

        $original = $this->get_original();

        return match (true) {
            !$original                                     => 'The live version is gone.',
            $original->get_status() !== 'publish'          => 'The live version is not published.',
            !$this->is_submitted()                         => 'It is not submitted for review.',
            !$this->is_current()                           => $original->get_open_proposal_id() ? 'Another proposal is open on the live version. Discard one of them.' : 'It is not linked to the live version. Submit it again to link it.',
            !$this->get_values() && $this->get_held_back() => 'Its only changes are to ' . implode(', ', $this->label_fields(array_keys($this->get_held_back()))) . ', which this site holds back, so approving would apply nothing and lose them.',
            default                                        => '',
        };

    }

    // $confirm is the fingerprint of the dry run the reviewer saw; without it the layer skips that check.
    public function approve (string $confirm) : array|WP_Error {

        if ($reason = $this->refuse_approval()) return new WP_Error('locked', $reason, ['status' => 409]);
        if ($confirm === '')                    return new WP_Error('invalid', 'Approve from its review, which carries what you saw.', ['status' => 400]);

        $original = $this->get_original();
        $result   = $this->write(['confirm' => $confirm]);

        if (is_wp_error($result)) return $result;

        do_action('lattice.proposal.approved', $original, $this, $result);

        $original->release_open_proposal($this->get_id());

        $this->draft->delete(true);

        return $result;

    }

    protected function write (array $input) : array|WP_Error {

        if (!($resource = $this->get_resource()) || !($original = $this->get_original())) return new WP_Error('not_found', 'The live version is gone.', ['status' => 404]);

        return Resources::inst()->update($resource->get_name(), $original->get_id(), $input + ['version' => $this->draft->get_proposal_base_version(), 'why' => $this->get_why()] + $this->get_values(), 'admin');

    }

    protected function get_why () : string {

        $author = $this->get_author();

        return "Approved proposal #{$this->get_id()}" . ($author ? " from {$author->get_display_name()}" : '') . '.';

    }

    // Lifecycle

    // The link goes in with the insert, so the draft's first save is already a proposal's.
    public static function open (Post $original, User $author) : static|WP_Error {

        if (!$original = static::find_model($original->get_id())) return new WP_Error('not_found', 'This type takes no proposals.');

        if ($reason = static::refuse_proposer($original, $author->get_id())) return new WP_Error('locked', $reason);

        $draft = $original->duplicate(['post_status' => 'draft', 'post_author' => $author->get_id(), 'meta_input' => $original::make_proposal_meta($original, $author)]);

        if (is_wp_error($draft)) return $draft;

        $original->set_open_proposal_id($draft->get_id());

        return new static($draft);

    }

    // Sending back is not an edit, so the draft's last saver stands.
    public function reject (string $note) : void {

        $saved_by = $this->get_saved_by();

        $this->draft->set_proposal_note($note);
        $this->draft->save(['post_status' => 'draft']);
        $this->draft->set_proposal_saved_by($saved_by);

        do_action('lattice.proposal.rejected', $this);

    }

    public function mark_submitted () : void {

        $this->draft->clear_proposal_note();

        do_action('lattice.proposal.submitted', $this);

    }

}
