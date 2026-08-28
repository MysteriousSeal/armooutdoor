(function () {
    var toggle = document.getElementById('site-menu-toggle');
    var panel = document.getElementById('site-cat-menu');

    if (!toggle || !panel) {
        return;
    }

    function setOpen(isOpen) {
        panel.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
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
