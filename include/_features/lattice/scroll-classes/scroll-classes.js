((params) => {

    let atTop     = true;
    let lastY     = window.scrollY;
    let lastDelta = 0;
    let ticking   = false;

    const H         = Math.max(0, params.hysteresis ?? 0);
    const getOffset = () => params.offset || 0;

    const applyState = (y) => {

        const delta  = lastY - y;
        const offset = getOffset();

        if (atTop) {
            if (y > offset + H) {
                atTop = false;
                document.body.classList.add('scrolled');
            }
        } else if (y <= Math.max(0, offset - H)) {
            atTop = true;
            document.body.classList.remove('scrolled');
        }

        if (y > 0 && Math.abs(delta) > 0) {
            if (Math.sign(delta) !== Math.sign(lastDelta)) {
                document.body.classList.toggle('scroll-up',   delta > 0);
                document.body.classList.toggle('scroll-down', delta < 0);
            }
            lastDelta = delta;
        }

        lastY = y;

    };

    const onScroll = () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            ticking = false;
            applyState(window.scrollY);
        });
    };

    requestAnimationFrame(() => applyState(window.scrollY));

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });

})(typeof digitalis_scroll_style !== 'undefined' ? digitalis_scroll_style : {});
