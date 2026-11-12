(() => {

    const adapter_url = document.currentScript.src;

    // Reuses Turnstile if another plugin loaded it; its scan finds only `.cf-turnstile`.
    const get_turnstile = (script_url) => new Promise((resolve, reject) => {

        if (window.turnstile) return resolve(window.turnstile);

        const wait = () => {

            let waited = 0;

            const timer = setInterval(() => {

                if (window.turnstile) { clearInterval(timer); resolve(window.turnstile); }
                else if ((waited += 100) > 15000) { clearInterval(timer); reject(); }

            }, 100);

        };

        if (document.querySelector('script[src*="challenges.cloudflare.com/turnstile/"]')) return wait();

        const script = document.createElement('script');
        script.src = script_url; script.async = true; script.onload = wait; script.onerror = reject;
        document.head.appendChild(script);

    });

    window.LatticeHumanCheck.register(adapter_url, {

        async render (el, config, on) {

            const turnstile = await get_turnstile(config.script);

            el.dataset.widgetId = turnstile.render(el, Object.assign({}, config.params, {
                'callback':                    on.token,
                'expired-callback':            on.expired,
                'error-callback':              on.error,
                'unsupported-callback':        on.unsupported,
                'before-interactive-callback': on.interactive,
            }));

        },

        reset (el) { if (window.turnstile && el.dataset.widgetId) window.turnstile.reset(el.dataset.widgetId); },

        remove (el) { if (window.turnstile && el.dataset.widgetId) window.turnstile.remove(el.dataset.widgetId); },

    });

})();
