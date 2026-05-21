/**
 * Lattice Menu — disclosure pattern interaction.
 * See lattice/docs/specs/menu/SPEC.md §7 for the JS contract.
 *
 * Owns: click toggle, Escape close, click-outside close, focus management,
 *       Arrow / Home / End keyboard within submenus, single-open enforcement,
 *       MutationObserver for dynamically-added menus.
 *
 * Does NOT own: hover trigger (stage 8), menubar pattern (stage 9),
 *               collision flipping (stage 10), drawer (stage 6).
 */

(function () {
    'use strict';

    if (window.__lattice_menu_loaded__) return;
    window.__lattice_menu_loaded__ = true;

    var INITED   = '__lattice_menu_inited__';
    var ROOT_SEL = 'ul[data-pattern]';
    var BTN_SEL  = 'button[aria-controls]';

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

        root.addEventListener('click',    function (e) { onClick(root, e); });
        root.addEventListener('keydown',  function (e) { onKeydown(root, e); });
        root.addEventListener('focusout', function (e) { onFocusOut(root, e); });

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

        var expanded = button.getAttribute('aria-expanded') === 'true';
        if (expanded) closeButton(button);
        else          openButton(root, button);

        event.preventDefault();

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

        });

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

})();
