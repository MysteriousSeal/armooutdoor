(function () {
    var toggle = document.getElementById('site-menu-toggle');
    var panel = document.getElementById('site-cat-menu');
    var subheader = document.getElementById('site-subheader');
    var closeBtn = document.getElementById('site-cat-menu-close');

    if (!toggle || !panel) {
        return;
    }

    function measureTop() {
        if (subheader) {
            panel.style.setProperty('--cat-menu-top', subheader.getBoundingClientRect().bottom + 'px');
        }
    }

    function setOpen(isOpen) {
        if (isOpen) {
            // iOS Safari's address bar can still be mid-transition right
            // when this fires, which throws the measurement off — a second
            // pass next frame, once that's settled, corrects it in
            // practice. The in-panel close button is the guaranteed
            // fallback for whatever this doesn't catch.
            measureTop();
            requestAnimationFrame(measureTop);
        }

        panel.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('has-menu-open', isOpen);
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        setOpen(panel.hidden);
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            setOpen(false);
            toggle.focus();
        });
    }

    document.addEventListener('click', function (event) {
        if (panel.hidden) {
            return;
        }

        if (panel.contains(event.target) || toggle.contains(event.target)) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
})();
