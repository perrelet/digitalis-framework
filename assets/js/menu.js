/**
 * Lattice Menu — disclosure pattern interaction.
 * See lattice/docs/specs/menu/SPEC.md §7 for the JS contract.
 *
 * Owns: click toggle, Escape close, click-outside close, focus management,
 *       Arrow / Home / End keyboard within submenus, single-open enforcement,
 *       MutationObserver for dynamically-added menus, hover trigger.
 *
 * Does NOT own: menubar pattern (stage 9), collision flipping (stage 10),
 *               drawer (stage 6).
 */

(function () {
    'use strict';

    if (window.__lattice_menu_loaded__) return;
    window.__lattice_menu_loaded__ = true;

    var INITED   = '__lattice_menu_inited__';
    var ROOT_SEL = 'ul[data-pattern]';
    var BTN_SEL  = 'button[aria-controls]';
    var LI_SEL   = 'li[data-has-submenu], li[data-has-panel]';

    var HOVER_IN_TIMER  = '__lattice_menu_hover_in_timer__';
    var HOVER_OUT_TIMER = '__lattice_menu_hover_out_timer__';

    // Evaluated once at module load. Pointer-capability shift mid-session
    // (e.g. docking a hybrid device) is out of scope for v1.
    var POINTER_FINE = (typeof matchMedia === 'function')
        && matchMedia('(pointer: fine)').matches;

    var outsideListenerAttached = false;

    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init () {

        document.querySelectorAll(ROOT_SEL).forEach(initRoot);
        observe();

    }

    function initRoot (root) {

        if (root.hasAttribute('data-no-observe')) return;
        if (root[INITED]) return;
        root[INITED] = true;

        // Marker for menu.css to disable the no-JS :focus-within fallback.
        // Without it, focus on a disclosure button after a JS-driven close
        // keeps :focus-within true and re-shows the submenu via that rule
        // (specificity (0,4,1) beats the closed-state rule's (0,2,2)).
        root.setAttribute('data-js-active', '');

        root.addEventListener('click',    function (e) { onClick(root, e); });
        root.addEventListener('keydown',  function (e) { onKeydown(root, e); });
        root.addEventListener('focusout', function (e) { onFocusOut(root, e); });

        hoverInitRoot(root);

    }

    function observe () {

        if (typeof MutationObserver === 'undefined') return;

        var observer = new MutationObserver(function (mutations) {

            mutations.forEach(function (m) {

                m.addedNodes.forEach(function (node) {

                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(ROOT_SEL)) initRoot(node);
                    if (node.querySelectorAll) node.querySelectorAll(ROOT_SEL).forEach(initRoot);

                });

            });

        });

        observer.observe(document.body, { childList: true, subtree: true });

    }

    // ------------------------------------------------------------------
    // Click
    // ------------------------------------------------------------------

    function onClick (root, event) {

        var button = event.target.closest(BTN_SEL);
        if (!button || !root.contains(button)) return;

        // Disclosure buttons live in the menu's own structure, never inside a
        // mega-panel content region. A button[aria-controls] within a
        // [role='region'] panel belongs to that content (possibly a nested
        // Menu with its own handlers) — ignore it rather than hijack it.
        if (button.closest('[role="region"]')) return;

        var expanded = button.getAttribute('aria-expanded') === 'true';
        if (expanded) closeButton(button);
        else          openButton(root, button);

        event.preventDefault();

    }

    // ------------------------------------------------------------------
    // Hover (stage 8)
    // ------------------------------------------------------------------
    //
    // Opt-in via 'trigger' => ['click','hover'] (emits data-trigger='click
    // hover'). Two gates: data-trigger must contain 'hover', and
    // matchMedia('(pointer: fine)') must match. Hybrid devices get listeners;
    // the per-event pointerType check drops synthesized hover from taps so a
    // tap-open doesn't immediately hover-close. Purely additive — click,
    // keyboard, Escape, focusout, click-outside are unchanged. Delays read
    // per-schedule from --menu-hover-{in,out}-delay (150/300ms default);
    // reduced-motion zeros them.

    function hoverInitRoot (root) {

        if (!POINTER_FINE) return;

        var trigger = (root.getAttribute('data-trigger') || '').split(/\s+/);
        if (trigger.indexOf('hover') === -1) return;

        root.addEventListener('pointerover', function (e) {

            if (e.pointerType === 'touch') return;

            var li = e.target.closest(LI_SEL);
            if (!li || !root.contains(li))    return;
            if (li.contains(e.relatedTarget)) return;

            onLiEnter(root, li);

        });

        root.addEventListener('pointerout', function (e) {

            if (e.pointerType === 'touch') return;

            var li = e.target.closest(LI_SEL);
            if (!li || !root.contains(li))    return;
            if (li.contains(e.relatedTarget)) return;

            onLiLeave(root, li);

        });

    }

    function onLiEnter (root, li) {

        clearTimeout(li[HOVER_OUT_TIMER]);
        li[HOVER_OUT_TIMER] = null;

        if (li.dataset.state === 'open') return;

        var button = li.querySelector(':scope > ' + BTN_SEL);
        if (!button) return;

        li[HOVER_IN_TIMER] = setTimeout(function () {
            li[HOVER_IN_TIMER] = null;
            openButton(root, button);
        }, getHoverDelay(root, 'in'));

    }

    function onLiLeave (root, li) {

        clearTimeout(li[HOVER_IN_TIMER]);
        li[HOVER_IN_TIMER] = null;

        if (li.dataset.state !== 'open') return;

        var button = li.querySelector(':scope > ' + BTN_SEL);
        if (!button) return;

        li[HOVER_OUT_TIMER] = setTimeout(function () {
            li[HOVER_OUT_TIMER] = null;
            closeButton(button);
        }, getHoverDelay(root, 'out'));

    }

    // ------------------------------------------------------------------
    // Keydown
    // ------------------------------------------------------------------

    function onKeydown (root, event) {

        switch (event.key) {

            case 'Escape':       return onEscape(root, event);
            case 'ArrowDown':    return onArrow(root, event, 'first');
            case 'ArrowUp':      return onArrow(root, event, 'last');
            case 'Home':         return onHomeEnd(root, event, 'first');
            case 'End':          return onHomeEnd(root, event, 'last');

        }

    }

    function onEscape (root, event) {

        var focused = document.activeElement;
        if (!focused || !root.contains(focused)) return;

        var openLi = focused.closest('li[data-state="open"]');
        if (!openLi) return;

        var trigger = openLi.querySelector(':scope > ' + BTN_SEL);
        if (!trigger) return;

        closeButton(trigger);
        trigger.focus();
        event.preventDefault();
        // Stop here so a containing Menu_Drawer's Escape handler doesn't also
        // fire and close the whole drawer — Escape closes one level per press.
        event.stopPropagation();

    }

    function onArrow (root, event, where) {

        // Only act when the disclosure button itself has focus — not when
        // a link inside an open submenu does (its closest button would be
        // the parent disclosure, which is not what arrow should target).
        var button = (event.target.matches && event.target.matches(BTN_SEL)) ? event.target : null;
        if (!button || !root.contains(button)) return;

        var panel = panelFor(button);
        if (!panel || panel.dataset.orientation !== 'vertical') return;

        var li = button.closest('li');
        if (li.dataset.state !== 'open') openButton(root, button);

        focusIn(panel, where);
        event.preventDefault();

    }

    function onHomeEnd (root, event, where) {

        var focused = document.activeElement;
        if (!focused || !root.contains(focused)) return;

        // Only respond when focus is inside a submenu / panel (not on a root item).
        var panel = focused.closest('ul:not([data-pattern]), [role="region"]');
        if (!panel) return;

        focusIn(panel, where);
        event.preventDefault();

    }

    // ------------------------------------------------------------------
    // Focus-out — close the menu tree when focus leaves it entirely
    // ------------------------------------------------------------------

    function onFocusOut (root, event) {

        var next = event.relatedTarget;
        if (next && root.contains(next)) return;

        // Defer a tick so we don't fight a focus that's still in transit.
        setTimeout(function () {
            if (!root.contains(document.activeElement)) closeAll(root);
        }, 0);

    }

    // ------------------------------------------------------------------
    // Open / close primitives
    // ------------------------------------------------------------------

    function openButton (root, button) {

        if (root.dataset.multiOpen !== 'true') closeSiblings(button);

        var li = button.closest('li');
        cancelHoverTimers(li);
        button.setAttribute('aria-expanded', 'true');
        if (li.dataset.state !== 'static') li.dataset.state = 'open';

        syncOutsideListener();

    }

    function closeButton (button) {

        var li = button.closest('li');

        // Close any open descendants first.
        li.querySelectorAll('[data-state="open"]').forEach(function (descLi) {

            var descBtn = descLi.querySelector(':scope > ' + BTN_SEL);
            if (descBtn) descBtn.setAttribute('aria-expanded', 'false');
            descLi.dataset.state = 'closed';
            cancelHoverTimers(descLi);

        });

        cancelHoverTimers(li);
        button.setAttribute('aria-expanded', 'false');
        if (li.dataset.state !== 'static') li.dataset.state = 'closed';

        syncOutsideListener();

    }

    function closeSiblings (button) {

        var li     = button.closest('li');
        var parent = li.parentElement;
        if (!parent) return;

        parent.querySelectorAll(':scope > li[data-state="open"]').forEach(function (sibling) {

            if (sibling === li) return;
            var sibBtn = sibling.querySelector(':scope > ' + BTN_SEL);
            if (sibBtn) closeButton(sibBtn);

        });

    }

    function closeAll (root) {

        root.querySelectorAll('[data-state="open"]').forEach(function (li) {

            var btn = li.querySelector(':scope > ' + BTN_SEL);
            if (btn) btn.setAttribute('aria-expanded', 'false');
            li.dataset.state = 'closed';

        });

        // Cancel hover timers on every disclosure-bearing <li> in the root —
        // including ones currently closed but with a pending in-timer. Without
        // this an Escape / click-outside / focusout can be silently undone by
        // a hover-in firing milliseconds later.
        root.querySelectorAll(LI_SEL).forEach(cancelHoverTimers);

        syncOutsideListener();

    }

    // ------------------------------------------------------------------
    // Document-level click-outside listener (lazy, single instance)
    // ------------------------------------------------------------------

    function syncOutsideListener () {

        var anyOpen = !!document.querySelector(ROOT_SEL + ' [data-state="open"]');

        if (anyOpen && !outsideListenerAttached) {
            document.addEventListener('click', onDocumentClick);
            outsideListenerAttached = true;
        } else if (!anyOpen && outsideListenerAttached) {
            document.removeEventListener('click', onDocumentClick);
            outsideListenerAttached = false;
        }

    }

    function onDocumentClick (event) {

        document.querySelectorAll(ROOT_SEL).forEach(function (root) {

            if (!root.contains(event.target)) closeAll(root);

        });

    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    function panelFor (button) {

        var id = button.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;

    }

    function focusIn (container, where) {

        var items = container.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
        if (!items.length) return;
        (where === 'first' ? items[0] : items[items.length - 1]).focus();

    }

    function cancelHoverTimers (li) {

        clearTimeout(li[HOVER_IN_TIMER]);
        clearTimeout(li[HOVER_OUT_TIMER]);
        li[HOVER_IN_TIMER]  = null;
        li[HOVER_OUT_TIMER] = null;

    }

    function getHoverDelay (root, kind) {

        if (typeof matchMedia === 'function'
            && matchMedia('(prefers-reduced-motion: reduce)').matches) return 0;

        var prop     = '--menu-hover-' + kind + '-delay';
        var raw      = getComputedStyle(root).getPropertyValue(prop);
        var fallback = (kind === 'in') ? 150 : 300;
        return parseDelayMs(raw, fallback);

    }

    function parseDelayMs (value, fallback) {

        var trimmed = (value || '').trim();
        var n       = parseFloat(trimmed);
        if (isNaN(n)) return fallback;

        // '0.2s' → 200ms. 'ms' suffix or unitless → as-is.
        var isSeconds = /s$/i.test(trimmed) && !/ms$/i.test(trimmed);
        return isSeconds ? n * 1000 : n;

    }

})();
