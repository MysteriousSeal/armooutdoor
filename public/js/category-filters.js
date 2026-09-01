(function () {
    var sidebar = document.querySelector('[data-filters]');

    if (!sidebar) {
        return;
    }

    // The data-js marker gating the collapses is set in the head, before
    // first paint — not here, where it would land late and snap shut
    // filters that were born open.
    var toggle = sidebar.querySelector('[data-filters-toggle]');

    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    sidebar.querySelectorAll('[data-filter-group-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var group = button.closest('.category-filter-group');
            var open = group.classList.toggle('is-open');

            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
