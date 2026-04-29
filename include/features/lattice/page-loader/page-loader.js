((params) => {

    const loader = document.getElementById('page-loader');
    if (!loader) return;

    const entryMs = (params.entrySpeed || 1) * 1000;

    const onReady = () => {
        document.body.classList.add('loaded');
        setTimeout(() => document.body.classList.add('loader-done'), entryMs);
    };

    if (document.readyState === 'complete') {
        onReady();
    } else {
        window.addEventListener('load', onReady);
    }

    if (params.exit) {
        window.addEventListener('beforeunload', () => {
            document.body.classList.remove('loaded', 'loader-done');
            document.body.classList.add('unloading');
            loader.style.display = 'flex';
        });
    }

})(typeof digitalis_page_loader !== 'undefined' ? digitalis_page_loader : {});
