(function () {
    // More than one toggle can be on the page at once (e.g. the mobile
    // subheader carries its own copy) — every one of them flips the same
    // global theme and stays in sync with it.
    var toggles = document.querySelectorAll('.theme-toggle-btn');

    if (!toggles.length) {
        return;
    }

    function currentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        toggles.forEach(function (toggle) {
            toggle.setAttribute('data-theme', theme);
        });
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';

            applyTheme(nextTheme);

            fetch('/preferences/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ theme: nextTheme }),
                credentials: 'same-origin',
            }).catch(function () {});
        });
    });
})();
