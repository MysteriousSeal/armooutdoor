(function () {
    var toggle = document.getElementById('site-menu-toggle');
    var panel = document.getElementById('site-menu-panel');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
})();
