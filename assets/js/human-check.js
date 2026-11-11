(() => {

    if (window.LatticeHumanCheck) return window.LatticeHumanCheck.scan(document);

    const loaded   = new Map();
    const adapters = {};
    const waiting  = {};
    const states   = new WeakMap();

    const load_script = (src) => {

        if (!loaded.has(src)) loaded.set(src, new Promise((resolve, reject) => {

            const script = document.createElement('script');
            script.src = src; script.async = true; script.onload = resolve; script.onerror = reject;
            document.head.appendChild(script);

        }));

        return loaded.get(src);

    };

    const get_adapter = async (config) => {

        const name = config.adapter;

        if (adapters[name]) return adapters[name];

        const ready = new Promise(resolve => (waiting[name] = waiting[name] || []).push(resolve));
        await load_script(name);

        return adapters[name] || ready;

    };

    const say = (el, text) => {

        let note = el.parentElement.querySelector(':scope > .human-check-status');

        if (!note) {

            note = document.createElement('p');
            note.className = 'human-check-status';
            note.setAttribute('role', 'status');
            el.after(note);

        }

        note.textContent = text;

    };

    const release = (state) => {

        const send = state.pending;
        state.pending = null;
        if (send) send();

    };

    const hold_submit = (form, el, state) => {

        const held = () => !state.token && !state.failed;

        form.addEventListener('submit', (e) => {

            if (form.hasAttribute('hx-post') || !held()) return;

            e.preventDefault();
            state.pending = () => form.requestSubmit(e.submitter || undefined);

        });

        form.addEventListener('htmx:confirm', (e) => {

            if (!held()) return;

            e.preventDefault();
            state.pending = () => e.detail.issueRequest(true);

        });

        form.addEventListener('htmx:afterRequest', () => {

            state.token = '';
            if (state.adapter) state.adapter.reset(el);

        });

        if (form.hasAttribute('hx-post') && !form.hasAttribute('hx-sync')) form.setAttribute('hx-sync', 'this:drop');

    };

    const render = async (el) => {

        if (states.has(el)) return;

        const config = JSON.parse(el.dataset.humanCheck);
        const state  = { token: '', failed: false, pending: null, adapter: null };

        states.set(el, state);

        const form = el.closest('form');
        if (form) hold_submit(form, el, state);

        const fail = (text) => { state.failed = true; say(el, text); release(state); };

        try {

            state.adapter = await get_adapter(config);

            await state.adapter.render(el, config, {
                token:       (token) => { state.token = token; say(el, ''); release(state); },
                expired:     () => { state.token = ''; },
                interactive: () => say(el, 'Please complete the check to send the form.'),
                error:       () => fail('The check could not run. You can still send the form.'),
                unsupported: () => fail('This browser cannot run the check. You can still send the form.'),
            });

        } catch {

            fail('The check could not load. You can still send the form.');

        }

    };

    const scan = (root) => {

        if (!root || !root.querySelectorAll) return;

        [...(root.matches && root.matches('[data-human-check]') ? [root] : []), ...root.querySelectorAll('[data-human-check]')].forEach(render);

    };

    window.LatticeHumanCheck = {

        scan,
        render,

        register (adapter_url, adapter) {

            adapters[adapter_url] = adapter;
            (waiting[adapter_url] || []).forEach(resolve => resolve(adapter));

        },

    };

    document.addEventListener('htmx:load', (e) => scan(e.detail.elt));

    document.addEventListener('htmx:beforeCleanupElement', (e) => {

        const el = e.detail.elt;
        const state = el && states.get(el);
        if (state && state.adapter) state.adapter.remove(el);

    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan(document));
    else scan(document);

})();
