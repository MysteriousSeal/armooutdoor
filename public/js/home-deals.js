(function () {
    var INTERVAL = 4000;

    var row = document.querySelector('[data-deals-row]');

    if (!row) {
        return;
    }

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var timer = null;

    // Nothing to advance through: five offers fill the row exactly, and moving
    // a row that has nowhere to go would only jitter.
    function overflows() {
        return row.scrollWidth - row.clientWidth > 4;
    }

    function step() {
        var card = row.querySelector('.home-deals-item');

        if (!card || !overflows()) {
            return;
        }

        var gap = parseFloat(window.getComputedStyle(row).columnGap) || 0;
        var stride = card.getBoundingClientRect().width + gap;

        // Within a stride of the end: go round rather than nudge a few pixels
        // and appear stuck.
        var atEnd = row.scrollLeft + row.clientWidth >= row.scrollWidth - stride / 2;

        row.scrollTo({
            left: atEnd ? 0 : row.scrollLeft + stride,
            behavior: 'smooth',
        });
    }

    function stop() {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        // Reduced motion means no motion: this is decoration, and the row is
        // still there to be scrolled by hand.
        if (timer !== null || reduced.matches || !overflows()) {
            return;
        }

        timer = window.setInterval(step, INTERVAL);
    }

    // Reading a price on something that is about to move is unpleasant, so
    // pointing at the row or tabbing into it stops it.
    row.addEventListener('mouseenter', stop);
    row.addEventListener('mouseleave', start);
    row.addEventListener('focusin', stop);
    row.addEventListener('focusout', start);

    // A hand on the row wins over the timer for as long as it is there.
    row.addEventListener('touchstart', stop, { passive: true });
    row.addEventListener('touchend', start, { passive: true });

    // Nothing moves in a tab nobody is looking at.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    reduced.addEventListener('change', function () {
        stop();
        start();
    });

    start();
})();
