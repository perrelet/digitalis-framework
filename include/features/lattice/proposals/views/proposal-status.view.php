<?php

namespace Digitalis;

class Proposal_Status extends View {

    protected static $defaults = [
        'proposal' => null,
    ];

    protected static $required = ['proposal'];

    // Also read by the block editor's panel.
    public static function describe (Proposal $proposal) : array {

        $original = $proposal->get_original();
        $reviewer = current_user_can('approve_proposals');

        return [
            'heading' => !$reviewer ? '' : match (true) {
                !$original                 => 'Proposed change: the live version is gone',
                $proposal->is_submitted()  => 'Proposed change awaiting review',
                $proposal->was_sent_back() => 'Proposed change: sent back',
                default                    => 'Proposed change: being written',
            },
            'title'   => ($reviewer && $original) ? $original->get_title() : '',
            'note'    => $proposal->was_sent_back() ? $proposal->get_note() : '',
            'label'   => $reviewer ? 'Your note:' : 'Changes asked for:',
            'text'    => match (true) {
                !$original                              => 'The live version no longer exists, so this can\'t be applied.',
                $reviewer && $proposal->is_submitted()  => '',
                $reviewer && $proposal->was_sent_back() => 'Sent back to its author. Waiting for them to submit it again.',
                $reviewer                               => 'Its author hasn\'t submitted it for review yet.',
                $proposal->is_submitted()               => 'Submitted for review. Waiting for a reviewer.',
                $proposal->was_sent_back()              => 'Sent back for changes. Use "Submit for Review" when it is ready again.',
                default                                 => 'Use "Submit for Review" to send your changes for approval.',
            },
            'review'  => $reviewer && $original && $proposal->is_submitted(),
        ];

    }

    public function params (&$p) {

        $p += static::describe($p['proposal']);

        parent::params($p);

    }

    public function view () {

        ?>
        <div class="lattice-proposal-status">
            <?php if ($this['heading']): ?><p><strong><?= esc_html($this['heading']) ?></strong><?php if ($this['title']): ?><br><span class="lattice-proposal-muted"><?= esc_html($this['title']) ?></span><?php endif; ?></p><?php endif; ?>
            <?php if ($this['note']): ?><p class="lattice-proposal-note"><strong><?= esc_html($this['label']) ?></strong> <?= esc_html($this['note']) ?></p><?php endif; ?>
            <?php if ($this['text']): ?><p><?= esc_html($this['text']) ?></p><?php endif; ?>
            <?php if ($this['review']): ?><a href="#<?= esc_attr(Proposal_Review_Box::ID) ?>" class="button button-primary">Review and approve &darr;</a><?php endif; ?>
        </div>
        <?php

    }

}
