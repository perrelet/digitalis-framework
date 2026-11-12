<?php

declare(strict_types=1);

namespace Lattice;

class Proposals_Admin extends Feature {

    const BLOCKED = 'lattice_proposal_blocked_';
    const REFUSED = 'lattice_proposal_refused_';
    const NOTICE  = 'lattice_proposal_notice';
    const NOTE    = 'lattice_proposal_note';

    const NOTICES = [
        'approved' => ['success', 'The proposal was applied to the live version, and its draft removed.'],
        'rejected' => ['info',    'Sent back to its author to revise and submit again.'],
        'deleted'  => ['info',    'Proposal discarded. The live version can take a new proposal.'],
        'stale'    => ['error',   'The live version changed after this proposal was made, so it can\'t be applied. Discard it, then propose again on the current version.'],
        'refused'  => ['error',   'The proposal was not applied:'],
        'set_done'    => ['success', ''],
        'set_stopped' => ['warning', ''],
        'set_refused' => ['error',   ''],
    ];

    const SET_ACTIONS = ['submit_proposal', 'rebase_proposal', 'approve_proposal', 'reject_proposal', 'withdraw_set', 'undo_set', 'settle_step'];

    public function get_hooks () {

        return [
            'admin_enqueue_scripts'               => 'enqueue_assets',
            'enqueue_block_editor_assets'         => 'enqueue_status',
            'post_submitbox_misc_actions'         => 'print_submitbox',
            'admin_notices'                       => 'print_notices',
            'admin_post_lattice_proposal_propose' => 'handle_propose',
            'admin_post_lattice_proposal_approve' => 'handle_approve',
            'admin_post_lattice_proposal_reject'  => 'handle_reject',
            'admin_post_lattice_proposal_delete'  => 'handle_delete',
            'admin_post_lattice_set_act'          => 'handle_set_act',
        ];

    }

    public static function get_action_url (string $action, int $post_id, array $args = []) : string {

        return wp_nonce_url(add_query_arg(['action' => "lattice_proposal_{$action}", 'post_id' => $post_id] + $args, admin_url('admin-post.php')), "lattice_proposal_{$action}_{$post_id}");

    }

    public static function get_set_action_url (string $action, Change_Set $set) : string {

        return wp_nonce_url(add_query_arg(['action' => 'lattice_set_act', 'act' => $action, 'set_id' => $set->get_id(), 'version' => $set->get_version()], admin_url('admin-post.php')), "lattice_set_{$action}_{$set->get_id()}");

    }

    public static function get_review_url (int $draft_id) : string {

        return ($url = get_edit_post_link($draft_id)) ? $url . '#' . Proposal_Review_Box::ID : '';

    }

    public static function note_blocked () : void {

        set_transient(static::BLOCKED . get_current_user_id(), 1, 30);

    }

    // Assets

    public function enqueue_assets ($hook) {

        $screen = get_current_screen();

        if (!in_array($screen?->post_type, Proposal::get_post_types(), true) && !str_ends_with((string) $hook, Proposals::inst('proposals')->get_page_slug())) return;

        wp_enqueue_style('lattice-proposals', plugin_dir_url(__FILE__) . 'proposals.css', [], LATTICE_VERSION);

        // wp_text_diff() on a set's review.
        if (!empty($_GET['set'])) wp_enqueue_style('revisions');
        wp_enqueue_script('lattice-proposals', plugin_dir_url(__FILE__) . 'proposals.js', [], LATTICE_VERSION, true);
        wp_localize_script('lattice-proposals', 'latticeProposals', ['box' => Proposal_Review_Box::ID, 'notePrompt' => 'Why does it need changes? (optional)', 'noteField' => static::NOTE]);

    }

    public function enqueue_status () {

        if (!($wp_post = get_post()) || !($proposal = Proposal::find($wp_post->ID))) return;

        wp_enqueue_script('lattice-proposal-status', plugin_dir_url(__FILE__) . 'proposal-status.js', ['wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components'], LATTICE_VERSION, true);
        wp_localize_script('lattice-proposal-status', 'latticeProposalStatus', Proposal_Status::describe($proposal) + ['box' => Proposal_Review_Box::ID, 'reviewLabel' => 'Review and approve ↓']);

    }

    // Editors

    public function print_submitbox ($wp_post) {

        if (!$proposal = Proposal::find($wp_post->ID)) return;

        echo new Proposal_Status(['proposal' => $proposal]);

    }

    // Handlers

    public function handle_propose () {

        $post_id = (int) ($_GET['post_id'] ?? 0);

        check_admin_referer("lattice_proposal_propose_{$post_id}");

        if (!current_user_can('propose_changes')) wp_die('You may not propose changes.', '', ['response' => 403]);

        $original = Proposal::find_model($post_id);

        if (!$original || ($original->get_status() !== 'publish')) wp_die('This can\'t take a proposal.', '', ['response' => 400]);

        if ($reason = Proposal::refuse_proposer($original, get_current_user_id())) wp_die(esc_html($reason), '', ['response' => 403, 'back_link' => true]);

        $proposal = $original->get_open_proposal();

        if ($proposal && !current_user_can('edit_post', $proposal->get_id())) wp_die(esc_html(($proposal->get_author()?->get_display_name() ?? 'Someone') . ' is already proposing a change to this. It takes one proposal at a time.'), '', ['response' => 409, 'back_link' => true]);

        $proposal ??= Proposal::open($original, User::get_instance(get_current_user_id()));

        if (is_wp_error($proposal)) wp_die(esc_html('The proposal could not be made: ' . $proposal->get_error_message()), '', ['response' => 500]);

        wp_safe_redirect($proposal->get_draft()->get_edit_url());
        exit;

    }

    public function handle_approve () {

        $proposal = $this->take_proposal('approve', 'approve_proposals');

        if (!$original = $proposal->get_original()) $this->redirect(Proposal::get_queue_url());

        $result = $proposal->approve(sanitize_text_field(wp_unslash($_REQUEST['confirm'] ?? '')));

        if (!is_wp_error($result)) $this->redirect(get_edit_post_link($original->get_id(), 'raw') ?: Proposal::get_queue_url(), 'approved');

        // Resources answers stale for a moved version and for a dry run that no longer matches.
        $proposal->forget();

        $stale = $proposal->is_stale();

        set_transient(static::REFUSED . get_current_user_id(), (($result->get_error_code() === 'stale') && !$stale) ? 'It changed after you opened its review. Look at the changes again, then approve.' : $result->get_error_message(), 60);

        $this->redirect($proposal->get_draft()->get_edit_url(), $stale ? 'stale' : 'refused');

    }

    public function handle_reject () {

        $proposal = $this->take_proposal('reject', 'approve_proposals');

        $proposal->reject(sanitize_textarea_field(wp_unslash($_POST[static::NOTE] ?? '')));

        $this->redirect((sanitize_key($_REQUEST['return'] ?? '') === 'queue') ? Proposal::get_queue_url() : $proposal->get_draft()->get_edit_url(), 'rejected');

    }

    // delete_post decides: reviewers discard any, authors withdraw their own.
    public function handle_delete () {

        $proposal = $this->take_proposal('delete');

        if (!current_user_can('delete_post', $proposal->get_id())) wp_die('You may not delete this proposal.', '', ['response' => 403]);

        wp_trash_post($proposal->get_id());

        $this->redirect(Proposal::get_queue_url(), 'deleted');

    }

    public function handle_set_act () {

        $set_id = absint($_REQUEST['set_id'] ?? 0);
        $action = sanitize_key($_REQUEST['act'] ?? '');

        check_admin_referer("lattice_set_{$action}_{$set_id}");

        [$notice, $message] = $this->run_set_act($set_id, $action, wp_unslash($_REQUEST));

        set_transient(static::REFUSED . get_current_user_id(), $message, 60);

        $this->redirect(add_query_arg('set', $set_id, Proposal::get_queue_url()), $notice);

    }

    // The layer asks who may and when: this only carries what the screen sent.
    public function run_set_act (int $set_id, string $action, array $sent) : array {

        if (!in_array($action, static::SET_ACTIONS, true)) return ['set_refused', 'No such action.'];

        $input = ['version' => (int) ($sent['version'] ?? 0)] + array_filter(['seen' => $sent['seen'] ?? null]);

        if ($action === 'settle_step') $input += ['position' => (int) ($sent['position'] ?? -1), 'outcome' => (string) ($sent['outcome'] ?? '')];

        if ($action === 'reject_proposal') $input['note'] = sanitize_textarea_field($sent[static::NOTE] ?? '');

        $result = Resources::inst()->act('change_set', $set_id, $action, $input, 'admin');

        if (is_wp_error($result)) return ['set_refused', $result->get_error_message()];

        if (!isset($result['outcome'])) return ['set_done', 'Done.'];

        if ($result['outcome'] === 'applied') return ['set_done', 'Done: every change is live.'];
        if ($result['outcome'] === 'undone')  return ['set_done', 'Done: every change it made is undone.'];

        if ($result['outcome'] === 'awaiting_look') return ['set_stopped', ($action === 'undo_set')
            ? 'Paused: part of it is undone. What is left could only be checked once that landed: look below, then press Undo what it applied again.'
            : 'Paused: part of it is live. The next change could only be checked once that landed: look below, then approve again.'];

        // The walk counts steps from 0; the page counts changes from 1.
        $reason = preg_replace_callback('/\bStep (\d+)/', fn ($match) => 'Change ' . ((int) $match[1] + 1), (string) ($result['reason'] ?: $result['outcome']));

        return ['set_stopped', "It stopped: {$reason}"];

    }

    protected function take_proposal (string $action, ?string $capability = null) : Proposal {

        $draft_id = (int) ($_REQUEST['post_id'] ?? 0);

        check_admin_referer("lattice_proposal_{$action}_{$draft_id}");

        if ($capability && !current_user_can($capability)) wp_die('You may not review proposals.', '', ['response' => 403]);

        return Proposal::find($draft_id) ?? wp_die('This is not a proposal.', '', ['response' => 400]);

    }

    protected function redirect (string $url, string $notice = '') /* : never */ {

        wp_safe_redirect($notice ? add_query_arg(static::NOTICE, $notice, $url) : $url);
        exit;

    }

    // Notices

    public function print_notices () {

        $user = get_current_user_id();

        if (get_transient(static::BLOCKED . $user)) {

            delete_transient(static::BLOCKED . $user);

            echo new Proposal_Notice(['type' => 'warning', 'dismissible' => true, 'message' => 'This is a proposed change, so it can\'t be published itself. "Approve and apply" in its review box applies it to the live version.']);

        }

        $code = sanitize_key($_GET[static::NOTICE] ?? '');

        if ($notice = static::NOTICES[$code] ?? null) {

            [$type, $message] = $notice;

            $refusal = get_transient(static::REFUSED . $user);

            delete_transient(static::REFUSED . $user);

            echo new Proposal_Notice(['type' => $type, 'dismissible' => true, 'message' => trim($message . ((($code === 'refused') || str_starts_with($code, 'set_')) ? ' ' . $refusal : ''))]);

        }

        if ((get_current_screen()?->base !== 'post') || !($wp_post = get_post()) || !($model = Proposal::find_model($wp_post->ID))) return;

        if ($proposal = $model->get_proposal()) $this->print_draft_notices($proposal, $user);
        elseif (($model->get_status() === 'publish') && ($open = $model->get_open_proposal())) $this->print_live_notice($open);

    }

    protected function print_draft_notices (Proposal $proposal, int $user) : void {

        if ($proposal->was_sent_back()) echo new Proposal_Notice(['type' => 'warning', 'title' => 'A reviewer asked for changes.', 'message' => $proposal->get_note()]);

        if (!($original = $proposal->get_original()) || !($at_risk = Proposal::find_markup_at_risk($original, $user))) return;

        $outcome = (Proposal::decide_markup_policy($original, $user, $at_risk) === 'exclude') ? 'then leave your edit to that field out.' : 'then take it off the live version too.';

        echo new Proposal_Notice(['type' => 'warning', 'title' => 'The live version has markup your account can\'t keep:', 'message' => Proposal::describe_markup($at_risk, $proposal->get_resource()) . ". Fields you leave alone keep it. Editing one of them removes it there, and approving would {$outcome}"]);

    }

    protected function print_live_notice (Proposal $open) : void {

        echo new Proposal_Notice([
            'type'    => 'warning',
            'message' => $open->is_submitted() ? 'A proposed change to this is awaiting review. Editing it now means that proposal can\'t be applied: it would have to be discarded and proposed again.' : 'A proposed change to this is being written. Editing it now may mean that proposal can\'t be applied.',
            'link'    => ['url' => get_edit_post_link($open->get_id(), 'raw'), 'label' => 'See the proposal →'],
        ]);

    }

}
