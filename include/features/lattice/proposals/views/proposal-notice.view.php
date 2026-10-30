<?php

namespace Digitalis;

class Proposal_Notice extends View {

    protected static $defaults = [
        'type'        => 'info',
        'title'       => '',
        'message'     => '',
        'link'        => null,
        'inline'      => false,
        'dismissible' => false,
    ];

    public function condition () {

        return ($this['title'] . $this['message']) !== '';

    }

    public function view () {

        $classes = ['notice', "notice-{$this['type']}", $this['inline'] ? 'inline' : '', $this['dismissible'] ? 'is-dismissible' : ''];

        ?>
        <div class="<?= esc_attr(implode(' ', array_filter($classes))) ?>">
            <p>
                <?php if ($this['title']): ?><strong><?= esc_html($this['title']) ?></strong><?php endif; ?>
                <?= esc_html($this['message']) ?>
                <?php if ($this['link']): ?><a href="<?= esc_url($this['link']['url']) ?>"><?= esc_html($this['link']['label']) ?></a><?php endif; ?>
            </p>
        </div>
        <?php

    }

}
