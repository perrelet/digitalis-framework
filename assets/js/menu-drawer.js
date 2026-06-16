/**
 * Lattice Menu_Drawer — off-canvas wrapper interaction (stage 6).
 * See lattice/docs/specs/menu/SPEC.md §3.3 / §5.11 / §7 for the contract.
 *
 * Owns: toggle click, close-button click, Escape close, click-outside
 *       close, focus trap, scroll lock, breakpoint-driven hide-above,
 *       close-on-navigate link-click close.
 *
 * Does NOT own: hover behaviour (drawers are click-triggered per spec),
 *               role='dialog' / aria-modal (the inner Menu provides the
 *               <nav> landmark; drawer stays role-less per spec §5.11),
 *               animation specifics, scrim element (consumer concern).
 *
 * Closed state after init is `inert` (one attribute: removes from AT, blocks
 * focus, still renders so transitions run). The `hidden` attribute is the
 * no-JS fallback — removed on first init, never re-added. Per-subsystem notes
 * live inline at each function.
 */

(function () {
    'use strict';

    if (window.__lattice_menu_drawer_loaded__) return;
    window.__lattice_menu_drawer_loaded__ = true;

    var TOGGLE_SEL  = '.menu-drawer-toggle';
    var INITED      = '__lattice_menu_drawer_inited__';
    var OUTSIDE_KEY = '__lattice_menu_drawer_outside_listener__';
    var SCROLL_Y    = '__lattice_menu_drawer_scroll_y__';
    var BP_KEY      = '__lattice_menu_drawer_bp_handle__';


    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init () {

        document.querySelectorAll(TOGGLE_SEL).forEach(initToggle);
        observe();

    }

    function initToggle (toggle) {

        if (toggle[INITED]) return;

        var drawer = drawerForToggle(toggle);
        if (!drawer) return;

        toggle[INITED] = true;

        // First-init: remove the no-JS `hidden` attribute. `inert` stays as
        // the runtime closed-state mechanism.
        drawer.removeAttribute('hidden');

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            // True disclosure toggle: re-clicking while open closes. Honors the
            // aria-expanded contract the toggle advertises. Consumers that hide
            // the toggle while open (and close via the in-drawer button) never
            // reach the close branch — behaviour is unchanged for them.
            if (drawer.dataset.state === 'open') closeDrawer(drawer, toggle);
            else                                 openDrawer(drawer, toggle);
        });

        var closeBtn = drawer.querySelector('.menu-drawer-close');
        if (closeBtn) closeBtn.addEventListener('click', function () {
            closeDrawer(drawer, toggle);
        });

        drawer.addEventListener('keydown', function (e) {
            onDrawerKeydown(drawer, toggle, e);
        });

        if (drawer.dataset.closeOnNavigate === 'true') {
            drawer.addEventListener('click', function (e) {
                if (e.target.closest('a')) closeDrawer(drawer, toggle);
            });
        }

        setupBreakpoint(drawer, toggle);

    }

    function observe () {

        if (typeof MutationObserver === 'undefined') return;

        var observer = new MutationObserver(function (mutations) {

            mutations.forEach(function (m) {

                m.addedNodes.forEach(function (node) {

                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(TOGGLE_SEL)) initToggle(node);
                    if (node.querySelectorAll) node.querySelectorAll(TOGGLE_SEL).forEach(initToggle);

                });

            });

        });

        observer.observe(document.body, { childList: true, subtree: true });

    }


    // ------------------------------------------------------------------
    // Open / close
    // ------------------------------------------------------------------

    function openDrawer (drawer, toggle) {

        drawer.removeAttribute('inert');
        drawer.dataset.state = 'open';

        toggle.setAttribute('aria-expanded', 'true');
        if (toggle.dataset.labelClose) {
            toggle.setAttribute('aria-label', toggle.dataset.labelClose);
        }

        if (drawer.dataset.lockScroll === 'true') lockScroll();

        // Focus the close button. Deferred a tick so any open transition
        // gets started before the focus shift.
        var closeBtn = drawer.querySelector('.menu-drawer-close');
        if (closeBtn) setTimeout(function () { closeBtn.focus(); }, 0);

        // Lazy-attach so the open click itself doesn't trigger click-outside.
        setTimeout(function () { attachOutsideClick(drawer, toggle); }, 0);

    }

    function closeDrawer (drawer, toggle, skipFocus) {

        drawer.dataset.state = 'closed';
        drawer.setAttribute('inert', '');

        toggle.setAttribute('aria-expanded', 'false');
        if (toggle.dataset.labelOpen) {
            toggle.setAttribute('aria-label', toggle.dataset.labelOpen);
        }

        if (drawer.dataset.lockScroll === 'true') unlockScroll();

        detachOutsideClick(drawer);

        // skipFocus: the breakpoint path hides the toggle immediately after
        // closing, so returning focus to it would land focus on a display:none
        // element (lost to <body>). All other close paths restore focus.
        if (!skipFocus) toggle.focus();

    }


    // ------------------------------------------------------------------
    // Keyboard — Escape close + focus trap
    // ------------------------------------------------------------------

    function onDrawerKeydown (drawer, toggle, event) {

        if (event.key === 'Escape') {
            if (drawer.dataset.state === 'open') {
                closeDrawer(drawer, toggle);
                event.preventDefault();
            }
            return;
        }

        if (event.key === 'Tab' && drawer.dataset.trapFocus === 'true') {
            handleFocusTrap(drawer, event);
        }

    }

    function handleFocusTrap (drawer, event) {

        // Query fresh on each Tab so dynamically-revealed focusables
        // (e.g. inside an open submenu) are included. Filter to rendered
        // elements: collapsed submenus (display:none) still match the query
        // but the browser skips them on Tab, so counting them would place the
        // trap boundaries on unreachable nodes and leak focus out of the drawer.
        var focusables = Array.prototype.filter.call(
            drawer.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]),' +
                ' select:not([disabled]), textarea:not([disabled]),' +
                ' [tabindex]:not([tabindex="-1"])'
            ),
            function (el) { return el.getClientRects().length > 0; }
        );

        if (!focusables.length) {
            event.preventDefault();
            return;
        }

        var first = focusables[0];
        var last  = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            last.focus();
            event.preventDefault();
        } else if (!event.shiftKey && document.activeElement === last) {
            first.focus();
            event.preventDefault();
        }

    }


    // ------------------------------------------------------------------
    // Scroll lock (iOS-safe)
    // ------------------------------------------------------------------

    function lockScroll () {

        if (document.body[SCROLL_Y] !== undefined) return;  // already locked

        var scrollY = window.scrollY || window.pageYOffset || 0;
        document.body[SCROLL_Y]      = scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top      = '-' + scrollY + 'px';
        document.body.style.left     = '0';
        document.body.style.right    = '0';

    }

    function unlockScroll () {

        if (document.body[SCROLL_Y] === undefined) return;

        var scrollY = document.body[SCROLL_Y];
        document.body.style.position = '';
        document.body.style.top      = '';
        document.body.style.left     = '';
        document.body.style.right    = '';
        window.scrollTo(0, scrollY);
        delete document.body[SCROLL_Y];

    }


    // ------------------------------------------------------------------
    // Click-outside (per-drawer, lazy-attached)
    // ------------------------------------------------------------------

    function attachOutsideClick (drawer, toggle) {

        if (drawer[OUTSIDE_KEY]) return;

        var handler = function (event) {
            if (drawer.contains(event.target)) return;
            if (toggle.contains(event.target)) return;
            closeDrawer(drawer, toggle);
        };

        drawer[OUTSIDE_KEY] = handler;
        document.addEventListener('click', handler);

    }

    function detachOutsideClick (drawer) {

        if (!drawer[OUTSIDE_KEY]) return;

        document.removeEventListener('click', drawer[OUTSIDE_KEY]);
        drawer[OUTSIDE_KEY] = null;

    }


    // ------------------------------------------------------------------
    // Breakpoint hide-above
    // ------------------------------------------------------------------

    function setupBreakpoint (drawer, toggle) {

        var raw = getComputedStyle(drawer).getPropertyValue('--menu-drawer-breakpoint').trim();
        if (!raw) return;
        if (typeof matchMedia !== 'function') return;

        var mq = matchMedia('(min-width: ' + raw + ')');

        var apply = function () {

            if (mq.matches) {

                if (drawer.dataset.state === 'open') closeDrawer(drawer, toggle, true);
                drawer.setAttribute('data-above-breakpoint', 'true');
                toggle.setAttribute('data-above-breakpoint', 'true');

            } else {

                drawer.removeAttribute('data-above-breakpoint');
                toggle.removeAttribute('data-above-breakpoint');

            }

        };

        apply();
        mq.addEventListener('change', apply);
        drawer[BP_KEY] = { mq: mq, apply: apply };

    }


    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    function drawerForToggle (toggle) {

        var id = toggle.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;

    }

})();
