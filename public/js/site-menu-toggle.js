(function () {
    var toggle = document.getElementById('site-menu-toggle');
    var panel = document.getElementById('site-cat-menu');
    var subheader = document.getElementById('site-subheader');

    if (!toggle || !panel) {
        return;
    }

    function setOpen(isOpen) {
        if (isOpen && subheader) {
            // Pins the panel to where the subheader actually sits right now
            // rather than assuming it — that bottom edge moves depending on
            // whether the page is scrolled and the sticky bar has caught up.
            panel.style.setProperty('--cat-menu-top', subheader.getBoundingClientRect().bottom + 'px');
        }

        panel.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('has-menu-open', isOpen);
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        setOpen(panel.hidden);
    });

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
