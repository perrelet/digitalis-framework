((params) => {

    const loader = document.getElementById('page-loader');
    if (!loader) return;

    const entryMs    = (params.entrySpeed || 1) * 1000;
    const failsafeMs = params.failsafeMs || 5000;

    let fired         = false;
    let failsafeTimer = null;

    const onReady = () => {
        if (fired) return;
        fired = true;
        if (failsafeTimer) clearTimeout(failsafeTimer);
        document.body.classList.add('loaded');
        setTimeout(() => document.body.classList.add('loader-done'), entryMs);
    };

    // Primary trigger — DOM parsed and interactive (fires well before
    // window.load, which waits for every sub-resource).
    if (document.readyState !== 'loading') {
        onReady();
    } else {
        document.addEventListener('DOMContentLoaded', onReady, { once: true });
    }

    // JS-side safety net — hard cap if DOMContentLoaded never fires (rare but
    // possible with hung sync scripts). Matches the CSS @keyframes failsafe
    // timing in page-loader.feature.php so both safety nets agree.
    failsafeTimer = setTimeout(onReady, failsafeMs);

    // bfcache (back/forward) restore. Safari and Firefox restore the entire
    // page DOM including current classes. If the user navigated away with
    // exit_speed > 0, body has "unloading" and the loader's inline display is
    // "flex"; without this reset, the restored page reappears with the loader
    // still showing.
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        document.body.classList.remove('unloading');
        document.body.classList.add('loaded', 'loader-done');
        loader.style.display = '';
    });

    if (params.exit) {
        window.addEventListener('beforeunload', () => {
            document.body.classList.remove('loaded', 'loader-done');
            document.body.classList.add('unloading');
            loader.style.display = 'flex';
        });
    }

})(typeof digitalis_page_loader !== 'undefined' ? digitalis_page_loader : {});
