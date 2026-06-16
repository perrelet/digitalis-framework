<?php

/**
 * Menu_Drawer template — two top-level siblings (toggle <button> + drawer
 * <div>) per SPEC §5.11; no wrapping element, so Component's $tag/$attributes
 * are unused. `inert` is the post-JS closed-state mechanism, `hidden` the
 * no-JS fallback (removed by JS on first init).
 */

?>
<button type="button"
        class="menu-drawer-toggle"
        aria-expanded="false"
        aria-controls="<?= esc_attr($id) ?>"
        aria-label="<?= esc_attr($aria_label_open) ?>"
        data-label-open="<?= esc_attr($aria_label_open) ?>"
        data-label-close="<?= esc_attr($aria_label_close) ?>">
    <span aria-hidden="true" class="menu-drawer-icon menu-drawer-hamburger"></span>
</button>

<div id="<?= esc_attr($id) ?>"
     class="menu-drawer"
     data-state="closed"
     data-position="<?= esc_attr($position) ?>"
     data-trap-focus="<?= $trap_focus ? 'true' : 'false' ?>"
     data-lock-scroll="<?= $lock_scroll ? 'true' : 'false' ?>"
     data-close-on-navigate="<?= $close_on_navigate ? 'true' : 'false' ?>"
     style="--menu-drawer-breakpoint: <?= esc_attr($breakpoint) ?>"
     hidden
     inert>
    <button type="button"
            class="menu-drawer-close"
            aria-label="<?= esc_attr($aria_label_close) ?>">
        <span aria-hidden="true" class="menu-drawer-icon menu-drawer-x"></span>
    </button>
    <?= $menu ?>
</div>
