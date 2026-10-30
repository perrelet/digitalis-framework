<?php

namespace Digitalis;

require_once __DIR__ . '/has-proposals.trait.php';
require_once __DIR__ . '/proposal.class.php';
require_once __DIR__ . '/proposal-cleanup.cron-scheduler.php';
require_once __DIR__ . '/views/proposal-notice.view.php';
require_once __DIR__ . '/views/proposal-status.view.php';
require_once __DIR__ . '/views/proposal-review.view.php';
require_once __DIR__ . '/views/proposals-queue.view.php';
require_once __DIR__ . '/proposals-admin.feature.php';
require_once __DIR__ . '/proposals.posts-table.php';
require_once __DIR__ . '/proposal-review.meta-box.php';
require_once __DIR__ . '/proposals.admin-page.php';

class Proposals extends Feature {

    protected $id             = 'proposals';
    protected $page_slug      = 'lattice-proposals';
    protected $post_types     = null;
    protected $admins_approve = true;

    protected static $requires = ['lattice/resources', 'wp/stored-markup'];

    protected array $types = [];

    public static function instance_condition ($data = []) {

        return function_exists('acf_get_field') && parent::instance_condition($data);

    }

    public function get_hooks () {

        $hooks = [
            'user_has_cap'                        => 'grant_admin_caps',
            'wp_insert_post_data'                 => 'block_publish',
            'transition_post_status'              => 'note_submitted',
            'save_post'                           => 'note_saver',
            'before_delete_post'                  => 'forget_record',
            'wp_trash_post'                       => 'release_record',
            'untrashed_post'                      => 'relink',
            'lattice.relationship.sync'           => 'skip_draft_sync',
            'lattice.post.duplicate.exclude_meta' => 'exclude_meta',
            'lattice.resources.query'             => 'hide_drafts',
            'lattice.resources.refuse'            => 'refuse_drafts',
            'lattice.resources.journal'           => 'journal_drafts',
        ];

        // Resources register as the app loads, after this.
        if (is_admin()) $hooks['init'] = 'load_admin';

        return $hooks;

    }

    public function run () {

        Proposal_Cleanup::get_instance();

    }

    public function load_admin () {

        Proposals_Admin::get_instance();
        Proposals_Table::get_instance(['post_type' => $this->get_post_types()]);
        Proposal_Review_Box::get_instance();
        Proposals_Page::get_instance(['slug' => $this->page_slug]);

    }

    public function get_page_slug () : string {

        return $this->page_slug;

    }

    public function get_post_types () : array {

        $resources = Resources::inst()->get_resources();
        $key       = implode(',', array_keys($resources));

        if (!isset($this->types[$key])) {

            $moderated = array_values(array_filter(array_map(fn ($resource) => Proposal::is_moderated($resource) ? $resource->get_post_type() : '', $resources)));

            $this->types[$key] = is_null($this->post_types) ? $moderated : array_values(array_intersect((array) $this->post_types, $moderated));

        }

        return $this->types[$key];

    }

    public function grant_admin_caps ($allcaps) {

        if ($this->admins_approve && !empty($allcaps['manage_options'])) $allcaps['approve_proposals'] = true;

        return $allcaps;

    }

    // Guards

    public function block_publish ($data, $postarr) {

        if (!in_array($data['post_status'] ?? '', ['publish', 'future', 'private'], true) || !Proposal::find_model((int) ($postarr['ID'] ?? 0))?->is_proposal()) return $data;

        $prior = get_post_status($postarr['ID']);

        $data['post_status'] = in_array($prior, ['draft', 'pending'], true) ? $prior : 'draft';

        Proposals_Admin::note_blocked();

        return $data;

    }

    public function note_submitted ($new_status, $old_status, $wp_post) {

        if (($new_status !== 'pending') || ($old_status === 'pending') || !($proposal = Proposal::find($wp_post->ID))) return;

        $this->relink($wp_post->ID);

        $proposal->mark_submitted();

    }

    // The first insert is the live record's copy, unfiltered: nobody has saved it yet.
    public function note_saver ($post_id, $wp_post, $update) {

        if ($update && !wp_is_post_revision($post_id) && ($draft = Proposal::find_model($post_id))?->is_proposal()) $draft->set_proposal_saved_by(get_current_user_id());

    }

    public function forget_record ($post_id) {

        if (!$model = Proposal::find_model($post_id)) return;

        if ($model->is_proposal()) $this->release_record($post_id);
        else foreach (Proposal::find_drafts_for($model->get_id()) as $draft) $draft->delete(true);

    }

    public function release_record ($post_id) {

        if (($draft = Proposal::find_model($post_id))?->is_proposal()) Proposal::find_model($draft->get_proposed_for_id())?->release_open_proposal($draft->get_id());

    }

    public function relink ($post_id) {

        if (($draft = Proposal::find_model($post_id))?->is_proposal() && ($original = Proposal::find_model($draft->get_proposed_for_id())) && !$original->get_open_proposal()) $original->set_open_proposal_id($draft->get_id());

    }

    public function skip_draft_sync ($sync, $type, $id) {

        return $sync && !(($type === 'post') && Proposal::find_model((int) $id)?->is_proposal());

    }

    public function exclude_meta ($keys) {

        return [...(array) $keys, ...array_values(Proposal::META)];

    }

    // Resources: a draft is not a record

    public function hide_drafts ($args, $resource) {

        if (!in_array($resource->get_post_type(), $this->get_post_types(), true)) return $args;

        $not_draft = ['key' => Proposal::META['FOR'], 'compare' => 'NOT EXISTS'];

        $args['meta_query'] = empty($args['meta_query']) ? [$not_draft] : ['relation' => 'AND', $args['meta_query'], $not_draft];

        return $args;

    }

    public function refuse_drafts ($reason, $resource, $model) {

        if ($reason || !Proposal::find_model($model->get_id())?->is_proposal()) return $reason;

        $original_id = $model->get_proposed_for_id();

        return "This is a proposed change to #{$original_id}. It changes only in wp-admin, and reaches #{$original_id} when a reviewer approves it.";

    }

    public function journal_drafts ($journal, $resource, $model) {

        return $journal && !Proposal::find_model($model->get_id())?->is_proposal();

    }

}
