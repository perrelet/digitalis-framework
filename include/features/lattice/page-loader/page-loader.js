(() => {

    const loader = document.getElementById('page-loader');
    if (!loader) return;

    const body    = document.body;
    const setting = (key, fallback) => (loader.dataset[key] ?? '') === '' ? fallback : Number(loader.dataset[key]);

    const entry    = setting('entry', 1);
    const exit     = setting('exit', 0);
    const failsafe = setting('failsafe', 5);
    const backstop = setting('backstop', 10);

    let leaving       = false;
    let backstopTimer = null;

    // Entry

    let ready = false;

    const onReady = () => {
        if (ready || leaving || !entry) return;
        ready = true;
        body.classList.add('loaded');
        setTimeout(() => { if (!leaving) body.classList.add('loader-done'); }, entry * 1000);
    };

    if (document.readyState !== 'loading') onReady();
    else document.addEventListener('DOMContentLoaded', onReady, { once: true });

    setTimeout(onReady, failsafe * 1000);

    // Vetoes: navigations that fire beforeunload without leaving the page.

    const FILES = /\.(pdf|zip|csv|ics|mp3|mp4)$/i;

    let veto = null;

    const noteVeto = (kind) => {
        const noted = veto = { kind, at: Date.now() };
        // A link's beforeunload fires inside its click, so a link veto ends with the click.
        if (kind === 'link') setTimeout(() => { if (veto === noted) veto = null; }, 0);
    };

    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('a[href]');
        if (!link) return;
        if (link.closest('[data-page-loader="off"]')) return noteVeto('link');
        let url;
        try { url = new URL(link.href, location.href); } catch { return; }
        if (!/^https?:$/.test(url.protocol) || FILES.test(url.pathname)) noteVeto('link');
    }, true);

    document.addEventListener('submit', (e) => {
        if (e.target.closest?.('[data-page-loader="off"]')) noteVeto('form');
    }, true);

    const takeVeto = () => {
        const taken = veto && (veto.kind === 'link' || Date.now() - veto.at < 1000);
        veto = null;
        return taken;
    };

    // Leaving and returning

    const giveBack = () => {
        if (!leaving) return;
        leaving = false;
        clearTimeout(backstopTimer);
        body.classList.remove('unloading');
        if (entry) body.classList.add('loaded', 'loader-done');
        document.dispatchEvent(new CustomEvent('Digitalis/Page_Loader/Return'));
    };

    window.addEventListener('beforeunload', (e) => {
        if (takeVeto()) return;
        leaving = true;
        // body.loaded.loader-done hides the loader with more specificity than body.unloading shows it.
        if (exit) { body.classList.remove('loaded', 'loader-done'); body.classList.add('unloading'); }
        document.dispatchEvent(new CustomEvent('Digitalis/Page_Loader/Leave'));
        clearTimeout(backstopTimer);
        backstopTimer = setTimeout(giveBack, backstop * 1000);
        // Runs once every listener has had the event, and after any prompt closes, whatever the answer.
        setTimeout(() => { if (e.defaultPrevented || e.returnValue) giveBack(); }, 0);
    });

    window.addEventListener('pageshow', (e) => { if (e.persisted) giveBack(); });

})();
